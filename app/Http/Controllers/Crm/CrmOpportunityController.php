<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmEvent;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmStage;
use App\Models\Crm\CrmCadenceTask;
use App\Models\User;
use App\Services\Crm\CrmOpportunityService;
use Illuminate\Http\Request;

class CrmOpportunityController extends Controller
{
    public function show(int $id)
    {
        $opp = CrmOpportunity::with(['account', 'stage', 'owner', 'activities' => fn($q) => $q->latest()])->findOrFail($id);

        $events = CrmEvent::where('opportunity_id', $id)->latest('happened_at')->limit(30)->get();
        $stages = CrmStage::active()->ordered()->get();
        $users = User::orderBy('name')->get(['id', 'name']);

        $cadenceTasks = CrmCadenceTask::where('opportunity_id', $id)
            ->orderBy('step_number')
            ->get();

        return view('crm.opportunities.show', compact('opp', 'events', 'stages', 'users', 'cadenceTasks'));
    }

    /**
     * Atualizar dados da oportunidade (AJAX).
     */
    public function update(Request $request, int $id)
    {
        $opp = CrmOpportunity::findOrFail($id);

        $validated = $request->validate([
            'title'           => 'nullable|string|max:255',
            'area'            => 'nullable|string|max:100',
            'value_estimated' => 'nullable|numeric|min:0',
            'owner_user_id'   => 'nullable|exists:users,id',
            'next_action_at'  => 'nullable|date',
            'source'          => 'nullable|string|max:100',
        ]);

        $opp->update(array_filter($validated, fn($v) => $v !== null));

        return response()->json(['ok' => true]);
    }

    /**
     * Adicionar atividade à oportunidade (AJAX).
     */
    public function storeActivity(Request $request, int $id)
    {
        $opp = CrmOpportunity::findOrFail($id);

        $request->validate([
            'type'   => 'required|in:task,call,meeting,whatsapp,note',
            'title'  => 'required|string|max:255',
            'body'   => 'nullable|string|max:5000',
            'due_at' => 'nullable|date',
        ]);

        $activity = CrmActivity::create([
            'opportunity_id'     => $id,
            'account_id'         => $opp->account_id,
            'type'               => $request->type,
            'title'              => $request->title,
            'body'               => $request->body,
            'due_at'             => $request->due_at,
            'created_by_user_id' => auth()->id(),
        ]);

        // Atualizar last_touch
        $opp->account->update(['last_touch_at' => now()]);

        return response()->json(['ok' => true, 'id' => $activity->id]);
    }

    /**
     * Marcar cadence task como concluída (AJAX).
     */
    public function completeCadenceTask(int $oppId, int $taskId)
    {
        $task = CrmCadenceTask::where('opportunity_id', $oppId)->findOrFail($taskId);
        $task->update(['completed_at' => now()]);

        // Atualizar last_touch do account
        if ($task->account_id) {
            \App\Models\Crm\CrmAccount::where('id', $task->account_id)->update(['last_touch_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Marcar atividade como concluída (AJAX).
     */
    public function completeActivity(int $oppId, int $activityId)
    {
        $activity = CrmActivity::where('opportunity_id', $oppId)->findOrFail($activityId);
        $activity->update(['done_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
