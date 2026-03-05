<?php

namespace App\Http\Controllers;

use App\Services\CrmPainelService;
use App\Services\CrmAiInsightService;
use Illuminate\Http\Request;

class CrmPainelController extends Controller
{
    protected CrmPainelService $service;
    protected CrmAiInsightService $aiService;

    public function __construct(CrmPainelService $service, CrmAiInsightService $aiService)
    {
        $this->service = $service;
        $this->aiService = $aiService;
    }

    public function index()
    {
        $kpis       = $this->service->getKpisCarteira();
        $pipeline   = $this->service->getPipelineForecast();
        $atividade  = $this->service->getAtividadeSemana();
        $alertas    = $this->service->getAlertas();
        $aiDigest   = $this->aiService->getActiveDigest();

        return view('crm.painel.index', compact('kpis', 'pipeline', 'atividade', 'alertas', 'aiDigest'));
    }

    public function generateDigest(Request $request)
    {
        try {
            $insight = $this->aiService->generateWeeklyDigest();
            if ($insight) {
                return response()->json(['success' => true, 'titulo' => $insight->titulo]);
            }
            return response()->json(['success' => false, 'error' => 'Falha na geração — ver logs'], 500);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function generateAccountAction(Request $request, int $accountId)
    {
        try {
            $insight = $this->aiService->generateAccountAction($accountId, auth()->id());
            if ($insight) {
                return response()->json(['success' => true, 'insight' => $insight->toArray()]);
            }
            return response()->json(['success' => false, 'error' => 'Falha na geração'], 500);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
