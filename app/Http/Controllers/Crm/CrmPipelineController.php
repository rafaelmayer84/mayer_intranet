<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmStage;
use App\Models\User;
use App\Services\Crm\CrmMetricsService;
use App\Services\Crm\CrmOpportunityService;
use Illuminate\Http\Request;

class CrmPipelineController extends Controller
{
    public function index(Request $request, CrmMetricsService $metrics)
    {
        $ownerFilter = $request->filled('owner_user_id') ? (int) $request->owner_user_id : null;
        $typeFilter = $request->filled('type') ? $request->type : null;

        $kpis = $metrics->pipelineKpis($ownerFilter);
        $kanban = $metrics->kanbanData($ownerFilter, $typeFilter);
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('crm.pipeline.index', compact('kpis', 'kanban', 'users'));
    }

    /**
     * Mover oportunidade de stage (AJAX).
     */
    public function moveStage(Request $request, int $id, CrmOpportunityService $service)
    {
        $request->validate(['stage_id' => 'required|exists:crm_stages,id']);

        $opp = CrmOpportunity::findOrFail($id);
        $service->moveToStage($opp, (int) $request->stage_id, auth()->id());

        return response()->json(['ok' => true, 'status' => $opp->fresh()->status]);
    }

    /**
     * Marcar como ganho (AJAX).
     */
    public function markWon(int $id, CrmOpportunityService $service)
    {
        $opp = CrmOpportunity::findOrFail($id);
        $service->markWon($opp, auth()->id());

        return response()->json(['ok' => true]);
    }

    /**
     * Marcar como perdido (AJAX).
     */
    public function markLost(Request $request, int $id, CrmOpportunityService $service)
    {
        $request->validate(['reason' => 'nullable|string|max:255']);

        $opp = CrmOpportunity::findOrFail($id);
        $service->markLost($opp, $request->reason, auth()->id());

        return response()->json(['ok' => true]);
    }
}
