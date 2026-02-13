<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmMetricsService;

class CrmReportsController extends Controller
{
    public function index(CrmMetricsService $metrics)
    {
        $funnel     = $metrics->funnelReport(6);
        $conversion = $metrics->conversionByStage(6);
        $avgTime    = $metrics->avgTimePerStage(6);
        $winRate    = $metrics->winRateByOwner(3);
        $lostReasons = $metrics->lostReasons(6);
        $projected  = $metrics->projectedValue();

        return view('crm.reports.index', compact(
            'funnel', 'conversion', 'avgTime', 'winRate', 'lostReasons', 'projected'
        ));
    }
}
