<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportLeadsController extends Controller
{
    public function funil()
    {
        return view('reports._stub', [
            'reportTitle' => 'Funil de Leads',
            'domainLabel' => 'Leads & Marketing',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }

    public function marketing()
    {
        return view('reports._stub', [
            'reportTitle' => 'Performance Marketing',
            'domainLabel' => 'Leads & Marketing',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }

    public function bscInsights()
    {
        return view('reports._stub', [
            'reportTitle' => 'BSC Insights (IA)',
            'domainLabel' => 'Leads & Marketing',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }
}
