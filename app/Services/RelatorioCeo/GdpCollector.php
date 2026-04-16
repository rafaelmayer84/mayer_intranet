<?php

namespace App\Services\RelatorioCeo;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GdpCollector
{
    public function coletar(Carbon $inicio, Carbon $fim): array
    {
        $mes = (int) $fim->format('m');
        $ano = (int) $fim->format('Y');
        $mesPrev = (int) $inicio->copy()->subMonth()->format('m');
        $anoPrev = (int) $inicio->copy()->subMonth()->format('Y');

        // Snapshots do mês de referência
        $snapshots = DB::table('gdp_snapshots as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.mes', $mes)
            ->where('s.ano', $ano)
            ->select(
                'u.name',
                's.score_total',
                's.score_juridico',
                's.score_financeiro',
                's.score_atendimento',
                's.score_desenvolvimento',
                's.score_eval180',
                's.qa_avg_score',
                's.qa_nps',
                's.ranking',
                's.total_penalizacoes',
                's.percentual_variavel'
            )
            ->orderBy('s.ranking')
            ->get()
            ->toArray();

        // Snapshots mês anterior para comparação
        $snapshotsPrev = DB::table('gdp_snapshots as s')
            ->join('users as u', 'u.id', '=', 's.user_id')
            ->where('s.mes', $mesPrev)
            ->where('s.ano', $anoPrev)
            ->select('u.name', 's.score_total', 's.ranking')
            ->get()
            ->keyBy('name')
            ->toArray();

        // Metas vs realizado para indicadores principais
        $metasVsRealizado = DB::table('gdp_resultados_mensais as r')
            ->join('gdp_indicadores as i', 'i.id', '=', 'r.indicador_id')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.mes', $mes)
            ->where('r.ano', $ano)
            ->where('i.ativo', 1)
            ->where('i.status_v1', 'score')
            ->select('u.name', 'i.nome as indicador', 'r.valor_apurado', 'r.percentual_atingimento')
            ->orderBy('u.name')
            ->orderBy('i.ordem')
            ->get()
            ->toArray();

        // Penalizações do período
        $penalizacoes = DB::table('gdp_penalizacoes as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->join('gdp_penalizacao_tipos as t', 't.id', '=', 'p.tipo_id')
            ->where('p.mes', $mes)
            ->where('p.ano', $ano)
            ->select('u.name', 't.nome as tipo', 't.gravidade', 'p.pontos_desconto')
            ->orderBy('u.name')
            ->get()
            ->toArray();

        // Enriquecer snapshots com variação de ranking
        $snapshotsEnriquecidos = array_map(function ($s) use ($snapshotsPrev) {
            $s = (array)$s;
            $prev = $snapshotsPrev[$s['name']] ?? null;
            $s['ranking_anterior'] = $prev ? $prev->ranking : null;
            $s['score_anterior']   = $prev ? $prev->score_total : null;
            $s['variacao_score']   = $prev ? round($s['score_total'] - $prev->score_total, 2) : null;
            return $s;
        }, $snapshots);

        return [
            'mes_referencia'      => "{$mes}/{$ano}",
            'snapshots'           => $snapshotsEnriquecidos,
            'metas_vs_realizado'  => array_map(fn($m) => (array)$m, $metasVsRealizado),
            'penalizacoes'        => array_map(fn($p) => (array)$p, $penalizacoes),
            'total_advogados'     => count($snapshots),
        ];
    }
}
