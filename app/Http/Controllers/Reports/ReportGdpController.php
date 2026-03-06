<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportGdpController extends Controller
{
    public function performance()
    {
        return view('reports._stub', [
            'reportTitle' => 'Scorecard',
            'domainLabel' => 'Performance (GDP)',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }

    public function penalizacoes()
    {
        return view('reports._stub', [
            'reportTitle' => 'Penalizações',
            'domainLabel' => 'Performance (GDP)',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }

    public function avaliacoes180()
    {
        return view('reports._stub', [
            'reportTitle' => 'Avaliações 180°',
            'domainLabel' => 'Performance (GDP)',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }
}
