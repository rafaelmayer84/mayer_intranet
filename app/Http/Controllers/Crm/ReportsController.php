<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmMetricsService;
use App\Models\User;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    protected CrmMetricsService $metrics;

    public function __construct(CrmMetricsService $metrics)
    {
        $this->metrics = $metrics;
    }

    public function index(Request $request)
    {
        $filters = [
            'owner' => $request->input('owner'),
            'source' => $request->input('source'),
            'period_start' => $request->input('period_start'),
            'period_end' => $request->input('period_end'),
        ];

        $reports = $this->metrics->reports($filters);
        $kpis = $this->metrics->pipelineKPIs($filters);
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('crm.reports', compact('reports', 'kpis', 'users', 'filters'));
    }
}
