<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportCrmController extends Controller
{
    public function baseClientes()
    {
        return view('reports._stub', [
            'reportTitle' => 'Base de Clientes',
            'domainLabel' => 'CRM / Clientes',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }

    public function pipeline()
    {
        return view('reports._stub', [
            'reportTitle' => 'Pipeline',
            'domainLabel' => 'CRM / Clientes',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }

    public function healthSegmentacao()
    {
        return view('reports._stub', [
            'reportTitle' => 'Health Score',
            'domainLabel' => 'CRM / Clientes',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }

    public function atividades()
    {
        return view('reports._stub', [
            'reportTitle' => 'Atividades',
            'domainLabel' => 'CRM / Clientes',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }
}
