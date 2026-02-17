<?php

namespace App\Services\Gdp;

use App\Models\GdpCiclo;
use App\Models\GdpIndicador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

            if ($valorApurado !== null && $meta !== null && (float) $meta > 0) {
                $metaFloat = (float) $meta;

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

        $scoreTotal = 0;
        foreach ($scoresPorEixo as $eixoCod => $scoreEixo) {
            $pesoEixo = $pesosEixo[$eixoCod] ?? 0;
            $scoreTotal += ($scoreEixo / 100) * $pesoEixo;
        }

        DB::table('gdp_snapshots')->updateOrInsert(
            [
                'ciclo_id' => $ciclo->id,
                'user_id'  => $user->id,
                'mes'      => $mes,
                'ano'      => $ano,
            ],
            [
                'score_juridico'        => round($scoresPorEixo['JURIDICO'], 2),
                'score_financeiro'      => round($scoresPorEixo['FINANCEIRO'], 2),
                'score_desenvolvimento' => round($scoresPorEixo['DESENVOLVIMENTO'], 2),
                'score_atendimento'     => round($scoresPorEixo['ATENDIMENTO'], 2),
                'score_total'           => round($scoreTotal, 2),
                'updated_at'            => now(),
            ]
        );

        $stats['snapshots']++;
        $stats['detalhes'][] = "{$user->name}: score={$scoreTotal}";
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
