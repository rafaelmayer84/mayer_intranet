<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Activity;
use App\Models\Crm\Opportunity;
use App\Services\Crm\CrmActivityService;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    protected CrmActivityService $activityService;

    public function __construct(CrmActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

    /**
     * Criar atividade.
     */
    public function store(Request $request)
    {
        $request->validate([
            'opportunity_id' => 'required|exists:crm_opportunities,id',
            'title' => 'required|string|max:255',
            'type' => 'required|in:task,call,meeting,whatsapp,note,email',
            'due_at' => 'nullable|date',
        ]);

        $opportunity = Opportunity::findOrFail($request->input('opportunity_id'));

        $activity = $this->activityService->create($opportunity, [
            'title' => $request->input('title'),
            'type' => $request->input('type'),
            'body' => $request->input('body'),
            'due_at' => $request->input('due_at'),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'activity' => $activity]);
        }

        return redirect()->route('crm.opportunity.show', $opportunity->id)
            ->with('success', 'Atividade criada.');
    }

    /**
     * Concluir atividade.
     */
    public function complete(Request $request, int $id)
    {
        $activity = Activity::findOrFail($id);

        $this->activityService->complete($activity);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('crm.opportunity.show', $activity->opportunity_id)
            ->with('success', 'Atividade concluída.');
    }
}
