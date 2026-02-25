<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmCadenceTask;
use App\Models\Crm\CrmCadenceTemplate;
use App\Models\Crm\CrmOpportunity;
use App\Models\NotificationIntranet;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CrmCadenceService
{
    /**
     * Aplica cadência a uma oportunidade.
     */
    public function aplicarCadencia(CrmOpportunity $opp, ?int $templateId = null): array
    {
        $template = $templateId
            ? CrmCadenceTemplate::active()->find($templateId)
            : CrmCadenceTemplate::getDefault();

        if (!$template) {
            Log::warning("[CrmCadence] Nenhum template encontrado para opp #{$opp->id}");
            return [];
        }

        $steps = $template->steps ?? [];
        $tasks = [];
        $baseDate = now();

        foreach ($steps as $i => $step) {
            $dueDate = (clone $baseDate)->addDays($step['day'] ?? 0);

            $task = CrmCadenceTask::create([
                'opportunity_id'      => $opp->id,
                'account_id'          => $opp->account_id,
                'cadence_template_id' => $template->id,
                'step_number'         => $i + 1,
                'title'               => $step['title'] ?? "Passo " . ($i + 1),
                'description'         => $step['description'] ?? null,
                'due_date'            => $dueDate->toDateString(),
                'assigned_user_id'    => $opp->owner_user_id,
            ]);

            $tasks[] = $task;
        }

        Log::info("[CrmCadence] Cadência '{$template->name}' aplicada à opp #{$opp->id} — {$template->name}, " . count($tasks) . " tasks");

        return $tasks;
    }

    /**
     * Verificar tasks vencendo hoje e notificar.
     */
    public function verificarENotificar(): array
    {
        $stats = ['notificacoes' => 0, 'emails' => 0];

        // Tasks de hoje não notificadas
        $tasksDueToday = CrmCadenceTask::with(['opportunity', 'account', 'assignedUser'])
            ->pending()
            ->whereDate('due_date', today())
            ->where('notified', false)
            ->get();

        foreach ($tasksDueToday as $task) {
            $this->notificarSininho($task);
            $stats['notificacoes']++;
        }

        // Tasks vencidas não notificadas por email
        $tasksOverdue = CrmCadenceTask::with(['opportunity', 'account', 'assignedUser'])
            ->overdue()
            ->where('notified_email', false)
            ->get();

        foreach ($tasksOverdue as $task) {
            $this->notificarEmail($task);
            $stats['emails']++;
        }

        Log::info('[CrmCadence] Verificação concluída', $stats);
        return $stats;
    }

    private function notificarSininho(CrmCadenceTask $task): void
    {
        if (!$task->assigned_user_id) return;

        $accName = $task->account?->name ?? 'Cliente';
        $oppTitle = $task->opportunity?->title ?? 'Oportunidade';

        NotificationIntranet::enviar(
            userId: $task->assigned_user_id,
            titulo: "📋 Cadência: {$task->title}",
            mensagem: "{$accName} — {$oppTitle} (Passo {$task->step_number})",
            link: $task->opportunity_id ? route('crm.opportunities.show', $task->opportunity_id) : null,
            tipo: 'crm_cadence',
            icone: 'clipboard-list'
        );

        $task->update(['notified' => true]);
    }

    private function notificarEmail(CrmCadenceTask $task): void
    {
        $user = $task->assignedUser;
        if (!$user || !$user->email) return;

        try {
            Mail::send('emails.crm_task_due', [
                'task'     => $task,
                'account'  => $task->account,
                'opp'      => $task->opportunity,
                'userName' => $user->name,
            ], function ($m) use ($user, $task) {
                $m->to($user->email, $user->name)
                  ->subject("⚠️ Cadência vencida: {$task->title}");
            });

            $task->update(['notified_email' => true]);
        } catch (\Exception $e) {
            Log::warning("[CrmCadence] Email falhou para {$user->email}: {$e->getMessage()}");
        }
    }
}
