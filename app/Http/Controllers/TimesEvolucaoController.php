<?php

namespace App\Http\Controllers;

use App\Services\TimesEvolucaoService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * TimesEvolucaoController
 *
 * Dashboard BSC → Times & Evolução
 * Mede maturidade organizacional (agregado, nunca individual).
 */
class TimesEvolucaoController extends Controller
{
    protected TimesEvolucaoService $service;

    public function __construct(TimesEvolucaoService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /times-evolucao
     */
    public function index(Request $request)
    {
        $now   = Carbon::now('America/Sao_Paulo');
        $year  = (int) $request->input('year', $now->year);
        $month = (int) $request->input('month', $now->month);

        // Validação básica
        $year  = max(2024, min(2030, $year));
        $month = max(1, min(12, $month));

        $kpis  = $this->service->getKpis($year, $month);
        $trend = $this->service->getTrend($year, $month);
        $metas = $this->service->getMetas($year, $month);

        // Montar referência de mês para exibição
        $refDate = Carbon::create($year, $month, 1);

        return view('times-evolucao.index', compact(
            'kpis', 'trend', 'metas', 'year', 'month', 'refDate'
        ));
    }

    /**
     * GET /times-evolucao/api/kpis (AJAX)
     */
    public function apiKpis(Request $request)
    {
        $year  = (int) $request->input('year', Carbon::now('America/Sao_Paulo')->year);
        $month = (int) $request->input('month', Carbon::now('America/Sao_Paulo')->month);

        return response()->json([
            'kpis'  => $this->service->getKpis($year, $month),
            'trend' => $this->service->getTrend($year, $month),
            'metas' => $this->service->getMetas($year, $month),
        ]);
    }
}
