<?php

namespace App\Services\Gdp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\GdpCiclo;
use App\Models\GdpEixo;
use App\Models\GdpIndicador;
use App\Models\GdpPenalizacao;
use App\Models\GdpRemuneracaoFaixa;
use Carbon\Carbon;

class GdpApuracaoService
{
    private string $logPrefix = '[GDP-Apuracao]';

    // Cap maximo de penalizacao por eixo/mes
    const CAP_PENALIZACAO_EIXO = 30;

    /**
     * Apuracao completa: scores positivos + penalizacoes + score final
     * Chamado pelo cron (gdp:apurar) ou pelo botao manual
     */
    public function apurarMes(?int $mes = null, ?int $ano = null): array
    {
        $mes = $mes ?? Carbon::now()->month;
        $ano = $ano ?? Carbon::now()->year;

        Log::info("{$this->logPrefix} Iniciando apuracao {$mes}/{$ano}");

        // 1. Obter ciclo ativo
        $ciclo = GdpCiclo::where('status', 'aberto')
            ->where('data_inicio', '<=', Carbon::now())
            ->where('data_fim', '>=', Carbon::now())
            ->first();

        if (!$ciclo) {
            Log::warning("{$this->logPrefix} Nenhum ciclo ativo encontrado.");
            return ['success' => false, 'message' => 'Nenhum ciclo GDP ativo.'];
        }

        // 2. Obter usuarios elegiveis GDP
        // Criterio: ativo=true + datajuri_proprietario_id preenchido (exclui contas teste)
        $usuarios = DB::table('users')
            ->where('ativo', true)
            ->whereNotNull('datajuri_proprietario_id')
            ->where('datajuri_proprietario_id', '>', 0)
            ->pluck('id')
            ->toArray();

        Log::info("{$this->logPrefix} Usuarios elegiveis: " . implode(',', $usuarios));

        // 3. Carregar eixos e indicadores
        $eixos = GdpEixo::with('indicadores')->get();

        // 4. Para cada usuario: calcular scores + penalizacoes
        $resultados = [];

        foreach ($usuarios as $userId) {
            try {
                $resultado = $this->apurarUsuario($ciclo, $userId, $mes, $ano, $eixos);
                $resultados[$userId] = $resultado;
            } catch (\Exception $e) {
                Log::error("{$this->logPrefix} Erro user {$userId}: " . $e->getMessage());
                $resultados[$userId] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        // 5. Calcular ranking
        $this->calcularRanking($ciclo->id, $mes, $ano, $resultados);

        // 6. Limpar cache
        Cache::flush();

        Log::info("{$this->logPrefix} Apuracao {$mes}/{$ano} concluida. Usuarios: " . count($resultados));

        return [
            'success'    => true,
            'ciclo'      => $ciclo->nome,
            'mes'        => $mes,
            'ano'        => $ano,
            'resultados' => $resultados,
        ];
    }

    /**
     * Apura um usuario especifico
     */
    private function apurarUsuario($ciclo, int $userId, int $mes, int $ano, $eixos): array
    {
        $adapter = new GdpDataAdapter();
        $scanner = new GdpPenalizacaoScanner($ciclo->id, $mes, $ano);

        $scoresPorEixo = [];
        $penalizacoesPorEixo = [];
        $scoresFinaisPorEixo = [];
        $scoreFinalGlobal = 0;

        // ETAPA 1: Calcular scores positivos por indicador (logica existente)
        foreach ($eixos as $eixo) {
            $scoreEixo = 0;

            foreach ($eixo->indicadores as $indicador) {
                if (!$indicador->ativo) continue;

                // Valor apurado pelo adapter
                // Valor apurado pelo adapter generico
                $valorApurado = $adapter->calcular($indicador->codigo, $userId, $mes, $ano) ?? 0;

                // Meta individual
                $meta = DB::table('gdp_metas_individuais')
                    ->where('ciclo_id', $ciclo->id)
                    ->where('user_id', $userId)
                    ->where('indicador_id', $indicador->id)
                    ->where('mes', $mes)
                    ->where('ano', $ano)
                    ->value('valor_meta');

                $percentual = 0;
                if ($meta && $meta > 0) {
                    // Para indicadores onde menor=melhor (ex: A1 tempo resposta)
                    if ($indicador->menor_melhor) {
                        $percentual = $meta > 0 ? ($meta / max($valorApurado, 0.01)) * 100 : 0;
                    } else {
                        $percentual = ($valorApurado / $meta) * 100;
                    }
                }

                // Cap no percentual (default 120%)
                $capPct = $indicador->cap_percentual ?? 120;
                $percentual = min($percentual, $capPct);

                // Score do indicador = percentual * peso do indicador no eixo
                $pesoIndicador = $indicador->peso ?? 0;
                $scoreIndicador = ($percentual / 100) * $pesoIndicador;
                $scoreEixo += $scoreIndicador;

                // Gravar resultado mensal
                DB::table('gdp_resultados_mensais')->updateOrInsert(
                    [
                        'ciclo_id'     => $ciclo->id,
                        'user_id'      => $userId,
                        'indicador_id' => $indicador->id,
                        'mes'          => $mes,
                        'ano'          => $ano,
                    ],
                    [
                        'valor_apurado'       => $valorApurado,
                        'percentual_atingimento' => round($percentual, 2),
                        'score_ponderado'     => round($scoreIndicador, 2),
                        'updated_at'          => now(),
                    ]
                );
            }

            $scoresPorEixo[$eixo->id] = round($scoreEixo, 2);
        }

        // ETAPA 2: Executar scanner de penalizacoes
        $scanResult = $scanner->scanUsuario($userId);

        // ETAPA 3: Calcular descontos por eixo (com cap)
        foreach ($eixos as $eixo) {
            $totalDesconto = GdpPenalizacao::totalDescontoEixo(
                $ciclo->id, $userId, $eixo->id, $mes, $ano
            );

            // Cap de penalizacao por eixo
            $descontoCapped = min($totalDesconto, self::CAP_PENALIZACAO_EIXO);

            $penalizacoesPorEixo[$eixo->id] = $descontoCapped;

            // Score final do eixo = positivo - penalizacoes (minimo 0)
            $scoreFinal = max(0, ($scoresPorEixo[$eixo->id] ?? 0) - $descontoCapped);
            $scoresFinaisPorEixo[$eixo->id] = round($scoreFinal, 2);

            // Score global = soma ponderada dos eixos
            $pesoEixo = $eixo->peso ?? 0;
            $scoreFinalGlobal += ($scoreFinal / 100) * $pesoEixo * 100;
        }

        $scoreFinalGlobal = round(min(100, max(0, $scoreFinalGlobal)), 2);

        // Faixa de remuneracao variavel
        $faixa = GdpRemuneracaoFaixa::faixaParaScore($ciclo->id, $scoreFinalGlobal);

        // ETAPA 4: Gravar snapshot
        DB::table('gdp_snapshots')->updateOrInsert(
            [
                'ciclo_id' => $ciclo->id,
                'user_id'  => $userId,
                'mes'      => $mes,
                'ano'      => $ano,
            ],
            [
                'score_juridico'      => $scoresFinaisPorEixo[$this->getEixoId($eixos, 'JURIDICO')] ?? 0,
                'score_financeiro'    => $scoresFinaisPorEixo[$this->getEixoId($eixos, 'FINANCEIRO')] ?? 0,
                'score_desenvolvimento' => $scoresFinaisPorEixo[$this->getEixoId($eixos, 'DESENVOLVIMENTO')] ?? 0,
                'score_atendimento'   => $scoresFinaisPorEixo[$this->getEixoId($eixos, 'ATENDIMENTO')] ?? 0,
                'score_total'         => $scoreFinalGlobal,
                'total_penalizacoes'  => $scanResult['total_penalizacoes'],
                'percentual_variavel' => $faixa ? $faixa->percentual : 0,
                'updated_at'          => now(),
            ]
        );

        // ETAPA 5: Audit log
        DB::table('gdp_audit_log')->insert([
            'user_id'    => $userId,
            'entidade'   => 'gdp_snapshots',
            'entidade_id' => 0,
            'campo'      => 'apuracao_automatica',
            'valor_anterior' => null,
            'valor_novo' => json_encode([
                'mes' => $mes, 'ano' => $ano,
                'scores_eixo' => $scoresPorEixo,
                'penalizacoes_eixo' => $penalizacoesPorEixo,
                'scores_finais_eixo' => $scoresFinaisPorEixo,
                'score_global' => $scoreFinalGlobal,
                'penalizacoes_total' => $scanResult['total_penalizacoes'],
                'penalizacoes_detalhes' => $scanResult['detalhes'],
            ]),
            'created_at' => now(),
        ]);

        return [
            'success'              => true,
            'score_global'         => $scoreFinalGlobal,
            'scores_eixo'          => $scoresPorEixo,
            'penalizacoes_eixo'    => $penalizacoesPorEixo,
            'scores_finais'        => $scoresFinaisPorEixo,
            'total_penalizacoes'   => $scanResult['total_penalizacoes'],
            'percentual_variavel'  => $faixa ? $faixa->percentual : 0,
        ];
    }

    /**
     * Calcula ranking entre usuarios no mes
     */
    private function calcularRanking(int $cicloId, int $mes, int $ano, array $resultados): void
    {
        $scores = [];
        foreach ($resultados as $userId => $r) {
            if (isset($r['score_global'])) {
                $scores[$userId] = $r['score_global'];
            }
        }

        arsort($scores);
        $ranking = 1;
        foreach ($scores as $userId => $score) {
            DB::table('gdp_snapshots')
                ->where('ciclo_id', $cicloId)
                ->where('user_id', $userId)
                ->where('mes', $mes)
                ->where('ano', $ano)
                ->update(['ranking' => $ranking]);
            $ranking++;
        }
    }

    /**
     * Retorna ID do eixo pelo codigo
     */
    private function getEixoId($eixos, string $codigo): ?int
    {
        foreach ($eixos as $eixo) {
            if ($eixo->codigo === $codigo) return $eixo->id;
        }
        return null;
    }
}
