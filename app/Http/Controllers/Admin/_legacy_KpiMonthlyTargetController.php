<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class KpiMonthlyTargetController extends Controller
{
    public function index(Request $request)
    {
        $ano = (int) $request->get('ano', date('Y'));
        
        // Buscar todas as metas para o ano selecionado
        $metasDb = DB::table('kpi_monthly_targets')
            ->where('year', $ano)
            ->get();

        // Transformar em array associativo para a view
        $metas = collect();
        foreach ($metasDb as $meta) {
            $key = $meta->kpi_key . '_' . $meta->month;
            $metas->put($key, $meta->target_value ?? '');
        }

        return view('admin.metas-kpi-mensais', [
            'ano' => $ano,
            'metas' => $metas,
        ]);
    }

    public function store(Request $request)
    {
        $ano = (int) $request->get('ano', date('Y'));
        $metas = $request->get('metas', []);

        // Limpar metas antigas do ano
        DB::table('kpi_monthly_targets')
            ->where('year', $ano)
            ->delete();

        // Inserir novas metas
        $inserts = [];

        // Metas mensais de Receita PF
        if (isset($metas['receita_pf'])) {
            foreach ($metas['receita_pf'] as $mes => $valor) {
                if ($valor !== '' && $valor !== null) {
                    $inserts[] = [
                        'year' => $ano,
                        'month' => (int)$mes,
                        'kpi_key' => 'receita_pf',
                        'target_value' => (float)$valor,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        // Metas mensais de Receita PJ
        if (isset($metas['receita_pj'])) {
            foreach ($metas['receita_pj'] as $mes => $valor) {
                if ($valor !== '' && $valor !== null) {
                    $inserts[] = [
                        'year' => $ano,
                        'month' => (int)$mes,
                        'kpi_key' => 'receita_pj',
                        'target_value' => (float)$valor,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        // Metas mensais de Despesas
        if (isset($metas['despesas'])) {
            foreach ($metas['despesas'] as $mes => $valor) {
                if ($valor !== '' && $valor !== null) {
                    $inserts[] = [
                        'year' => $ano,
                        'month' => (int)$mes,
                        'kpi_key' => 'despesas',
                        'target_value' => (float)$valor,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        // Metas de Saúde Financeira (sem mês, apenas ano)
        $saude_financeira = [
            'dias_atraso_meta' => 'dias_atraso_meta',
            'taxa_cobranca_meta' => 'taxa_cobranca_meta',
            'inadimplencia_meta' => 'inadimplencia_meta',
        ];

        foreach ($saude_financeira as $key => $db_key) {
            if (isset($metas[$key]) && $metas[$key] !== '' && $metas[$key] !== null) {
                $inserts[] = [
                    'year' => $ano,
                    'month' => 0, // 0 = anual
                    'kpi_key' => $db_key,
                    'target_value' => (float)$metas[$key],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Metas de Eficiência (sem mês, apenas ano)
        $eficiencia = [
            'expense_ratio_meta' => 'expense_ratio_meta',
            'yoy_growth_meta' => 'yoy_growth_meta',
        ];

        foreach ($eficiencia as $key => $db_key) {
            if (isset($metas[$key]) && $metas[$key] !== '' && $metas[$key] !== null) {
                $inserts[] = [
                    'year' => $ano,
                    'month' => 0, // 0 = anual
                    'kpi_key' => $db_key,
                    'target_value' => (float)$metas[$key],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Inserir em chunks para evitar limite de query
        if (!empty($inserts)) {
            foreach (array_chunk($inserts, 100) as $chunk) {
                DB::table('kpi_monthly_targets')->insert($chunk);
            }
        }

        return redirect()->route('config.metas-kpi-mensais', ['ano' => $ano])
            ->with('success', 'Metas salvas com sucesso!');
    }
}
