<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\KpiMonthlyTarget;

class KpiMonthlyTargetController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->query('year', now()->year);
        $month = $request->query('month', now()->month);

        $kpiList = [
            ['key' => 'receita_total', 'label' => 'Receita Total'],
            ['key' => 'despesas_totais', 'label' => 'Despesas Totais'],
            ['key' => 'resultado_liquido', 'label' => 'Resultado Líquido'],
            ['key' => 'margem_liquida', 'label' => 'Margem Líquida'],
        ];

        $metas = KpiMonthlyTarget::where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('kpi_key');

        return view('admin.metas-kpi-mensais', compact('year', 'month', 'kpiList', 'metas'));
    }

    public function store(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $metas = $request->input('metas', []);

        foreach ($metas as $kpiKey => $targetValue) {
            $value = trim($targetValue) ?: null;

            KpiMonthlyTarget::updateOrCreate(
                ['year' => $year, 'month' => $month, 'kpi_key' => $kpiKey],
                ['target_value' => $value]
            );
        }

        return redirect()->route('admin.metas-kpi-mensais', ['year' => $year, 'month' => $month])
            ->with('success', 'Metas salvas com sucesso!');
    }
}
