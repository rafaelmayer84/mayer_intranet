<?php

namespace App\Http\Controllers;

use App\Services\CrmPainelService;
use Illuminate\Http\Request;

class CrmPainelController extends Controller
{
    protected CrmPainelService $service;

    public function __construct(CrmPainelService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $kpis       = $this->service->getKpisCarteira();
        $pipeline   = $this->service->getPipelineForecast();
        $atividade  = $this->service->getAtividadeSemana();
        $alertas    = $this->service->getAlertas();

        return view('crm.painel.index', compact('kpis', 'pipeline', 'atividade', 'alertas'));
    }
}
