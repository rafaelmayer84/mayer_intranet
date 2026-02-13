<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Opportunity;
use App\Models\Crm\Event;
use Illuminate\Support\Facades\Log;

class CrmActivityService
{
    /**
     * Cria uma atividade vinculada a oportunidade.
     */
    public function create(Opportunity $opportunity, array $data): Activity
    {
        $activity = Activity::create([
            'opportunity_id' => $opportunity->id,
            'type' => $data['type'] ?? 'task',
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'created_by_user_id' => auth()->id(),
        ]);

        Event::log('activity_created', $opportunity->id, $opportunity->account_id, [
            'activity_id' => $activity->id,
            'type' => $activity->type,
            'title' => $activity->title,
        ]);

        // Atualizar next_action_at da oportunidade se atividade tem due_at
        if ($activity->due_at && $opportunity->isOpen()) {
            $nextAction = $opportunity->pendingActivities()
                ->whereNotNull('due_at')
                ->orderBy('due_at')
                ->value('due_at');

            if ($nextAction) {
                $opportunity->update(['next_action_at' => $nextAction]);
            }
        }

        return $activity;
    }

    /**
     * Marca atividade como concluída.
     */
    public function complete(Activity $activity): Activity
    {
        $activity->markDone();

        $opportunity = $activity->opportunity;

        Event::log('activity_completed', $opportunity->id, $opportunity->account_id, [
            'activity_id' => $activity->id,
            'type' => $activity->type,
            'title' => $activity->title,
        ]);

        // Recalcular next_action_at
        if ($opportunity->isOpen()) {
            $nextAction = $opportunity->pendingActivities()
                ->whereNotNull('due_at')
                ->orderBy('due_at')
                ->value('due_at');

            $opportunity->update(['next_action_at' => $nextAction]);
        }

        return $activity->fresh();
    }
}
