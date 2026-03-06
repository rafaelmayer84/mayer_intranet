<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportProdutividadeController extends Controller
{
    public function horas()
    {
        return view('reports._stub', [
            'reportTitle' => 'Horas Trabalhadas',
            'domainLabel' => 'Produtividade',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }

    public function atividades()
    {
        return view('reports._stub', [
            'reportTitle' => 'Atividades',
            'domainLabel' => 'Produtividade',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }

    public function receitaHora()
    {
        return view('reports._stub', [
            'reportTitle' => 'R$/Hora',
            'domainLabel' => 'Produtividade',
            'message'     => 'Relatório em implementação — Fase 2+',
        ]);
    }
}
