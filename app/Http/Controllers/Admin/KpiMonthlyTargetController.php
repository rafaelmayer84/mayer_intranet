<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class KpiMonthlyTargetController extends Controller
{
    public function index(Request $request)
    {
        $ano = $request->get('ano', date('Y'));
        
        // Buscar todas as metas para o ano selecionado
        $metasDb = DB::table('kpi_monthly_targets')
            ->where('year', $ano)
            ->get();
        
        // Transformar em array associativo para fácil acesso na view
        $metas = collect();
        foreach ($metasDb as $meta) {
            $key = $meta->kpi_key . '_' . ($meta->month ?? $ano);
            $metas->put($key, $meta->target_value);
        }
        
        return view('admin.metas-kpi-mensais', [
            'ano' => $ano,
            'metas' => $metas,
        ]);
    }

    public function store(Request $request)
    {
        $ano = $request->get('ano', date('Y'));
        $metas = $request->get('metas', []);

        // Salvar metas anuais
        $kpisAnuais = [
            'receita_total_ano',
            'despesa_total_ano',
            'resultado_liquido_ano',
            'margem_liquida_ano',
        ];

        foreach ($kpisAnuais as $kpiKey) {
            $valor = $metas[$kpiKey][$ano] ?? null;
            if ($valor !== null && $valor !== '') {
                DB::table('kpi_monthly_targets')->updateOrCreate(
                    [
                        'year' => $ano,
                        'month' => null,
                        'kpi_key' => $kpiKey,
                    ],
                    [
                        'target_value' => $valor,
                    ]
                );
            }
        }

        // Salvar metas mensais
        $kpisMensais = [
            'receita_pf',
            'receita_pj',
            'despesas',
        ];

        foreach ($kpisMensais as $kpiKey) {
            for ($mes = 1; $mes <= 12; $mes++) {
                $valor = $metas[$kpiKey][$mes] ?? null;
                if ($valor !== null && $valor !== '') {
                    DB::table('kpi_monthly_targets')->updateOrCreate(
                        [
                            'year' => $ano,
                            'month' => $mes,
                            'kpi_key' => $kpiKey,
                        ],
                        [
                            'target_value' => $valor,
                        ]
                    );
                }
            }
        }

        return redirect()
            ->route('config.metas-kpi-mensais', ['ano' => $ano])
            ->with('success', 'Metas salvas com sucesso!');
    }
}
