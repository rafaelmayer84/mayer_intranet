<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportSisrhController extends Controller
{
    public function folha()
    {
        return view('reports._stub', [
            'reportTitle' => 'Folha de Pagamento',
            'domainLabel' => 'RH (SISRH)',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }

    public function custos()
    {
        return view('reports._stub', [
            'reportTitle' => 'Custos RH',
            'domainLabel' => 'RH (SISRH)',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }
}
