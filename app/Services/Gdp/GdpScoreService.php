<?php

namespace App\Services\Gdp;

use App\Models\GdpCiclo;
use App\Models\GdpIndicador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Eval180Response;
use App\Models\Eval180Form;

class GdpScoreService
{
    private GdpDataAdapter $adapter;

    public function __construct(GdpDataAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function apurarMes(int $mes, int $ano, ?int $cicloId = null): array
    {
        $stats = ['usuarios' => 0, 'resultados' => 0, 'snapshots' => 0, 'erros' => 0, 'detalhes' => []];

        $ciclo = $cicloId ? GdpCiclo::find($cicloId) : GdpCiclo::ativo();
        if (!$ciclo) {
            return array_merge($stats, ['erro' => 'Nenhum ciclo ativo']);
        }

        if (!$ciclo->contemMes($mes, $ano)) {
            return array_merge($stats, ['erro' => "Mes {$mes}/{$ano} fora do ciclo {$ciclo->nome}"]);
        }

        $congelado = DB::table('gdp_snapshots')
            ->where('ciclo_id', $ciclo->id)
            ->where('mes', $mes)
            ->where('ano', $ano)
            ->where('congelado', true)
            ->exists();

        if ($congelado) {
            return array_merge($stats, ['erro' => "Mes {$mes}/{$ano} ja esta congelado"]);
        }

        $indicadores = GdpIndicador::whereHas('eixo', function ($q) use ($ciclo) {
            $q->where('ciclo_id', $ciclo->id);
        })->where('status_v1', 'score')->where('ativo', true)
          ->with('eixo')
          ->get();

        $users = DB::table('users')
            ->where('datajuri_ativo', true)->where('ativo', true)
            ->whereNotNull('datajuri_proprietario_id')
            ->select('id', 'name', 'datajuri_proprietario_id')
            ->get();

        $stats['usuarios'] = $users->count();

        foreach ($users as $user) {
            try {
                $this->apurarUsuario($ciclo, $user, $indicadores, $mes, $ano, $stats);
            } catch (\Throwable $e) {
                Log::error("[GDP-Score] Erro user={$user->id}: " . $e->getMessage());
                $stats['erros']++;
                $stats['detalhes'][] = "ERRO {$user->name}: " . substr($e->getMessage(), 0, 200);
            }
        }

        $this->gerarRanking($ciclo->id, $mes, $ano);

        Log::info("[GDP-Score] Apuracao {$mes}/{$ano}: " . json_encode($stats));
        return $stats;
    }

    private function apurarUsuario(
        GdpCiclo $ciclo,
        object $user,
        $indicadores,
        int $mes,
        int $ano,
        array &$stats
    ): void {
        $scoresPorEixo = [
            'JURIDICO' => 0,
            'FINANCEIRO' => 0,
            'DESENVOLVIMENTO' => 0,
            'ATENDIMENTO' => 0,
        ];

        foreach ($indicadores as $indicador) {
            $eixoCodigo = $indicador->eixo->codigo;

            $valorApurado = $this->adapter->calcular($indicador->codigo, $user->id, $mes, $ano);

            $meta = DB::table('gdp_metas_individuais')
                ->where('ciclo_id', $ciclo->id)
                ->where('indicador_id', $indicador->id)
                ->where('user_id', $user->id)
                ->where('mes', $mes)
                ->where('ano', $ano)
                ->value('valor_meta');

            $percentual = null;
            $scorePonderado = null;

            if ($valorApurado !== null && $meta !== null && (float) $meta >= 0) {
                $metaFloat = (float) $meta;

                if ($metaFloat == 0) {
                    // Meta zero = atingimento 100% (qualquer resultado vale)
                    $percentual = 100.0;
                } else
if ($indicador->direcao === 'menor_melhor') {
                    $percentual = $valorApurado > 0
                        ? ($metaFloat / $valorApurado) * 100
                        : 100.0;
                } else {
                    $percentual = ($valorApurado / $metaFloat) * 100;
                }

                $cap = (float) $indicador->cap_percentual;
                $percentual = min($percentual, $cap);
                $percentual = max($percentual, 0);
                $percentual = round($percentual, 2);

                $scorePonderado = round(($percentual / 100) * (float) $indicador->peso, 4);

                if (isset($scoresPorEixo[$eixoCodigo])) {
                    $scoresPorEixo[$eixoCodigo] += $scorePonderado;
                }
            }

            DB::table('gdp_resultados_mensais')->updateOrInsert(
                [
                    'ciclo_id'     => $ciclo->id,
                    'indicador_id' => $indicador->id,
                    'user_id'      => $user->id,
                    'mes'          => $mes,
                    'ano'          => $ano,
                ],
                [
                    'valor_apurado'          => $valorApurado,
                    'apurado_em'             => now(),
                    'percentual_atingimento' => $percentual,
                    'score_ponderado'        => $scorePonderado,
                    'updated_at'             => now(),
                ]
            );

            $stats['resultados']++;
        }

        $pesosEixo = [];
        foreach ($indicadores->pluck('eixo')->unique('id') as $eixo) {
            $pesosEixo[$eixo->codigo] = (float) $eixo->peso;
        }

        // Calcular soma dos pesos dos indicadores por eixo (para normalizar)
        $somaPesosIndPorEixo = [];
        foreach ($indicadores as $ind) {
            $ec = $ind->eixo->codigo;
            $somaPesosIndPorEixo[$ec] = ($somaPesosIndPorEixo[$ec] ?? 0) + (float) $ind->peso;
        }

        $scoreTotal = 0;
        foreach ($scoresPorEixo as $eixoCod => $scoreEixo) {
            $pesoEixo = $pesosEixo[$eixoCod] ?? 0;
            $somaPesosInd = $somaPesosIndPorEixo[$eixoCod] ?? 100;
            // Normalizar: scoreEixo / somaMaxPesos = fração 0..1.2, * pesoEixo = contribuição
            $contribuicao = ($somaPesosInd > 0) ? ($scoreEixo / $somaPesosInd) * $pesoEixo : 0;
            $scoreTotal += $contribuicao;
        }
        $scoreTotal = min(round($scoreTotal, 2), 100.0);

        // Buscar nota Eval180 do período (nota do gestor, se submetida)
        $eval180Score = $this->getEval180Score($ciclo->id, $user->id, $mes, $ano);

        // Guardrail: se ativo e nota < piso, penalizar score_total
        $scoreTotalOriginal = $scoreTotal;
        $guardrailAtivo = DB::table('configuracoes')
            ->where('chave', 'gdp_eval180_guardrail_ativo')
            ->value('valor') === 'true';

        if ($guardrailAtivo && $eval180Score !== null) {
            $pisoQualidade = 3.0; // nota 1-5
            if ($eval180Score < $pisoQualidade) {
                $fator = (float) (DB::table('configuracoes')
                    ->where('chave', 'gdp_eval180_guardrail_fator')
                    ->value('valor') ?? '0.90');
                $scoreTotal = round($scoreTotal * $fator, 2);
                Log::info("[GDP-Score] Guardrail Eval180: user={$user->id} eval180={$eval180Score} < piso={$pisoQualidade}, fator={$fator}, score {$scoreTotalOriginal} -> {$scoreTotal}");
            }
        }

        DB::table('gdp_snapshots')->updateOrInsert(
            [
                'ciclo_id' => $ciclo->id,
                'user_id'  => $user->id,
                'mes'      => $mes,
                'ano'      => $ano,
            ],
            [
                'score_juridico'        => min(round(($somaPesosIndPorEixo['JURIDICO'] ?? 100) > 0 ? ($scoresPorEixo['JURIDICO'] / $somaPesosIndPorEixo['JURIDICO']) * 100 : 0, 2), 100),
                'score_financeiro'      => min(round(($somaPesosIndPorEixo['FINANCEIRO'] ?? 100) > 0 ? ($scoresPorEixo['FINANCEIRO'] / $somaPesosIndPorEixo['FINANCEIRO']) * 100 : 0, 2), 100),
                'score_desenvolvimento' => min(round(($somaPesosIndPorEixo['DESENVOLVIMENTO'] ?? 100) > 0 ? ($scoresPorEixo['DESENVOLVIMENTO'] / $somaPesosIndPorEixo['DESENVOLVIMENTO']) * 100 : 0, 2), 100),
                'score_atendimento'     => min(round(($somaPesosIndPorEixo['ATENDIMENTO'] ?? 100) > 0 ? ($scoresPorEixo['ATENDIMENTO'] / $somaPesosIndPorEixo['ATENDIMENTO']) * 100 : 0, 2), 100),
                'score_eval180'         => $eval180Score,
                'score_total_original'  => round($scoreTotalOriginal, 2),
                'score_total'           => round($scoreTotal, 2),
                'updated_at'            => now(),
            ]
        );

        $stats['snapshots']++;
        $stats['detalhes'][] = "{$user->name}: score={$scoreTotal}" . ($eval180Score !== null ? " eval180={$eval180Score}" : '');
    }

    /**
     * Busca a nota do gestor na Eval180 para o usuário/período.
     * Retorna nota 1-5 ou null se não houver avaliação submetida.
     */
    private function getEval180Score(int $cicloId, int $userId, int $mes, int $ano): ?float
    {
        // Tentar período mensal (YYYY-MM)
        $period = sprintf('%04d-%02d', $ano, $mes);

        $form = Eval180Form::where('cycle_id', $cicloId)
            ->where('user_id', $userId)
            ->where('period', $period)
            ->first();

        // Se não encontrou mensal, tentar trimestral (YYYY-Q1, Q2, etc.)
        if (!$form) {
            $quarter = (int) ceil($mes / 3);
            $periodQ = sprintf('%04d-Q%d', $ano, $quarter);
            $form = Eval180Form::where('cycle_id', $cicloId)
                ->where('user_id', $userId)
                ->where('period', $periodQ)
                ->first();
        }

        if (!$form) {
            return null;
        }

        // Buscar resposta do gestor submetida
        $managerResponse = Eval180Response::where('form_id', $form->id)
            ->where('rater_type', 'manager')
            ->whereNotNull('submitted_at')
            ->first();

        return $managerResponse ? (float) $managerResponse->total_score : null;
    }

    private function gerarRanking(int $cicloId, int $mes, int $ano): void
    {
        $snapshots = DB::table('gdp_snapshots')
            ->where('ciclo_id', $cicloId)
            ->where('mes', $mes)
            ->where('ano', $ano)
            ->orderByDesc('score_total')
            ->get();

        $pos = 1;
        foreach ($snapshots as $snap) {
            DB::table('gdp_snapshots')->where('id', $snap->id)->update(['ranking' => $pos]);
            $pos++;
        }
    }
}
