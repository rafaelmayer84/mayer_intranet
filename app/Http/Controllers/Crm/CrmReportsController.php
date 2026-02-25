<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmMetricsService;

class CrmReportsController extends Controller
{
    public function index(CrmMetricsService $metrics)
    {
        // Existentes
        $projected  = $metrics->projectedValue();
        $winRate    = $metrics->winRateByOwner(3);
        $funnel     = $metrics->funnelEnriched(6);
        $conversion = $metrics->conversionByStage(6);
        $avgTime    = $metrics->avgTimePerStage(6);
        $lostReasons = $metrics->lostReasons(6);

        // Novos — Bloco C
        $carteira   = $metrics->carteiraByOwner();
        $heatmap    = $metrics->heatmapInatividade();

        return view('crm.reports.index', compact(
            'projected', 'winRate', 'funnel', 'conversion', 'avgTime', 'lostReasons',
            'carteira', 'heatmap'
        ));
    }
}
