<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportNexoController extends Controller
{
    public function conversas()
    {
        return view('reports._stub', [
            'reportTitle' => 'Conversas',
            'domainLabel' => 'Atendimento (NEXO)',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }

    public function tickets()
    {
        return view('reports._stub', [
            'reportTitle' => 'Tickets',
            'domainLabel' => 'Atendimento (NEXO)',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }

    public function qa()
    {
        return view('reports._stub', [
            'reportTitle' => 'Satisfação (QA)',
            'domainLabel' => 'Atendimento (NEXO)',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }

    public function performanceAtendentes()
    {
        return view('reports._stub', [
            'reportTitle' => 'Performance Atendentes',
            'domainLabel' => 'Atendimento (NEXO)',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }
}
