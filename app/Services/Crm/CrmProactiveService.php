<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmEvent;
use App\Models\NotificationIntranet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmProactiveService
{
    private array $stats = [
        'alertas_criados'  => 0,
        'tasks_criadas'    => 0,
        'skipped_cooldown' => 0,
        'skipped_dup_task' => 0,
    ];

    private const COOLDOWNS = [
        'sem_contato_30d'   => 7,
        'followup_vencido'  => 1,
        'titulo_vencendo'   => 999,
        'lifecycle_changed' => 999,
        'task_primeiro_contato' => 999,
        'task_agendar_reuniao'  => 999,
        'task_reativacao'       => 999,
        'task_cobranca_titulo'  => 999,
    ];

    public function executar(array $lifecycleChanges = []): array
    {
        $this->alertaSemContato30d();
        $this->alertaFollowupVencido();
        $this->alertaTituloVencendo();
        $this->alertaLifecycleChanged($lifecycleChanges);
        $this->taskReativacaoAdormecidos($lifecycleChanges);

        Log::info('[CrmProactive] Execução concluída', $this->stats);
        return $this->stats;
    }

    public function criarTaskPrimeiroContato(int $accountId, string $nome, ?int $ownerUserId): void
    {
        if ($this->taskAbertaExiste($accountId, 'Primeiro contato')) return;
        $this->criarTask($accountId, "Primeiro contato com {$nome}", 'Fazer contato inicial com o lead promovido ao CRM.', 2, $ownerUserId);
        $this->registrarAlerta($accountId, 'task_primeiro_contato', null, $ownerUserId);
        $this->stats['tasks_criadas']++;
    }

    public function criarTaskAgendarReuniao(int $accountId, int $oppId, string $titulo, ?int $ownerUserId): void
    {
        if ($this->taskAbertaExiste($accountId, 'Agendar reunião')) return;
        $this->criarTask($accountId, "Agendar reunião — {$titulo}", 'Oportunidade criada. Agendar reunião inicial com o cliente.', 3, $ownerUserId, $oppId);
        $this->registrarAlerta($accountId, 'task_agendar_reuniao', $oppId, $ownerUserId);
        $this->stats['tasks_criadas']++;
    }

    // ─── Bloco 1: Alertas ────────────────────────────────

    private function alertaSemContato30d(): void
    {
        $accounts = DB::table('crm_accounts')
            ->where('lifecycle', 'ativo')
            ->whereNotNull('owner_user_id')
            ->where(function ($q) {
                $q->whereNull('last_touch_at')
                  ->orWhere('last_touch_at', '<', Carbon::now()->subDays(30));
            })
            ->select('id', 'name', 'owner_user_id', 'last_touch_at')
            ->get();

        foreach ($accounts as $acc) {
            if ($this->emCooldown($acc->id, 'sem_contato_30d', $acc->owner_user_id)) {
                $this->stats['skipped_cooldown']++;
                continue;
            }

            $dias = $acc->last_touch_at
                ? Carbon::parse($acc->last_touch_at)->diffInDays(Carbon::now())
                : '30+';

            NotificationIntranet::enviar(
                $acc->owner_user_id,
                'Cliente sem contato',
                "{$acc->name} está há {$dias} dias sem interação.",
                route('crm.accounts.show', $acc->id),
                'warning',
                'user-x'
            );

            $this->registrarAlerta($acc->id, 'sem_contato_30d', null, $acc->owner_user_id);
            $this->stats['alertas_criados']++;
        }
    }

    private function alertaFollowupVencido(): void
    {
        $accounts = DB::table('crm_accounts')
            ->whereNotNull('next_touch_at')
            ->where('next_touch_at', '<', Carbon::now())
            ->whereNotNull('owner_user_id')
            ->select('id', 'name', 'owner_user_id', 'next_touch_at')
            ->get();

        foreach ($accounts as $acc) {
            if ($this->emCooldown($acc->id, 'followup_vencido', $acc->owner_user_id)) {
                $this->stats['skipped_cooldown']++;
                continue;
            }

            $atraso = Carbon::parse($acc->next_touch_at)->diffInDays(Carbon::now());

            NotificationIntranet::enviar(
                $acc->owner_user_id,
                'Follow-up atrasado',
                "{$acc->name} — follow-up vencido há {$atraso} dia(s).",
                route('crm.accounts.show', $acc->id),
                'danger',
                'clock'
            );

            $this->registrarAlerta($acc->id, 'followup_vencido', null, $acc->owner_user_id);
            $this->stats['alertas_criados']++;
        }
    }

    private function alertaTituloVencendo(): void
    {
        $titulos = DB::table('contas_receber')
            ->whereNotIn('status', ['Concluído', 'Concluido', 'Excluido', 'Excluído'])
            ->whereBetween('data_vencimento', [
                Carbon::now()->format('Y-m-d'),
                Carbon::now()->addDays(7)->format('Y-m-d'),
            ])
            ->whereNotNull('cliente_datajuri_id')
            ->select('id', 'cliente', 'valor', 'data_vencimento', 'cliente_datajuri_id')
            ->get();

        foreach ($titulos as $titulo) {
            $account = DB::table('crm_accounts')
                ->where('datajuri_pessoa_id', $titulo->cliente_datajuri_id)
                ->select('id', 'name', 'owner_user_id')
                ->first();

            if (!$account || !$account->owner_user_id) continue;

            if ($this->alertaJaEnviado($account->id, 'titulo_vencendo', $titulo->id)) {
                $this->stats['skipped_cooldown']++;
                continue;
            }

            $valorFmt = 'R$ ' . number_format($titulo->valor, 2, ',', '.');
            $venc = Carbon::parse($titulo->data_vencimento)->format('d/m');

            NotificationIntranet::enviar(
                $account->owner_user_id,
                'Título vencendo',
                "{$account->name} — {$valorFmt} vence em {$venc}.",
                route('crm.accounts.show', $account->id),
                'warning',
                'dollar-sign'
            );

            $this->criarTaskSeNaoExiste(
                $account->id,
                "Cobrar título {$valorFmt} — {$account->name}",
                "Título vence em {$venc}. Verificar pagamento ou contatar cliente.",
                $titulo->data_vencimento,
                $account->owner_user_id
            );

            $this->registrarAlerta($account->id, 'titulo_vencendo', $titulo->id, $account->owner_user_id);
            $this->stats['alertas_criados']++;
            $this->stats['tasks_criadas']++;
        }
    }

    private function alertaLifecycleChanged(array $changes): void
    {
        foreach ($changes as $change) {
            $accId = $change['account_id'];
            $from = $change['from'];
            $to = $change['to'];
            $ownerId = $change['owner_user_id'] ?? null;

            if (!$ownerId) continue;

            $labels = [
                'onboarding' => 'Onboarding',
                'ativo'      => 'Ativo',
                'adormecido' => 'Adormecido',
                'arquivado'  => 'Arquivado',
                'risco'      => 'Risco',
            ];

            $fromLabel = $labels[$from] ?? $from;
            $toLabel = $labels[$to] ?? $to;
            $name = $change['name'] ?? 'Cliente';

            $tipo = in_array($to, ['adormecido', 'arquivado', 'risco']) ? 'danger' : 'info';

            NotificationIntranet::enviar(
                $ownerId,
                'Mudança de lifecycle',
                "{$name}: {$fromLabel} → {$toLabel}.",
                route('crm.accounts.show', $accId),
                $tipo,
                'refresh-cw'
            );

            $this->registrarAlerta($accId, 'lifecycle_changed', null, $ownerId);
            $this->stats['alertas_criados']++;
        }
    }

    // ─── Bloco 2: Tasks automáticas ─────────────────────

    private function taskReativacaoAdormecidos(array $changes): void
    {
        $transicoes = array_filter($changes, fn($c) => $c['to'] === 'adormecido' && $c['from'] === 'ativo');

        foreach ($transicoes as $change) {
            $accId = $change['account_id'];
            $ownerId = $change['owner_user_id'] ?? null;
            $name = $change['name'] ?? 'Cliente';

            if (!$ownerId) continue;
            if ($this->taskAbertaExiste($accId, 'Reativação')) {
                $this->stats['skipped_dup_task']++;
                continue;
            }

            $this->criarTask($accId, "Reativação: contatar {$name}", 'Cliente passou de Ativo para Adormecido. Verificar situação e reengajar.', 5, $ownerId);
            $this->registrarAlerta($accId, 'task_reativacao', null, $ownerId);
            $this->stats['tasks_criadas']++;
        }
    }

    // ─── Helpers ─────────────────────────────────────────

    private function criarTask(int $accountId, string $title, string $body, int $dueDays, ?int $ownerUserId, ?int $oppId = null): void
    {
        DB::table('crm_activities')->insert([
            'account_id'         => $accountId,
            'opportunity_id'     => $oppId,
            'type'               => 'task',
            'title'              => $title,
            'body'               => $body,
            'due_at'             => Carbon::now()->addWeekdays($dueDays),
            'done_at'            => null,
            'created_by_user_id' => $ownerUserId,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    private function criarTaskSeNaoExiste(int $accountId, string $title, string $body, string $dueDate, ?int $ownerUserId): void
    {
        if ($this->taskAbertaExiste($accountId, substr($title, 0, 20))) {
            $this->stats['skipped_dup_task']++;
            return;
        }

        DB::table('crm_activities')->insert([
            'account_id'         => $accountId,
            'type'               => 'task',
            'title'              => $title,
            'body'               => $body,
            'due_at'             => $dueDate,
            'done_at'            => null,
            'created_by_user_id' => $ownerUserId,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    private function taskAbertaExiste(int $accountId, string $tituloPrefix): bool
    {
        return DB::table('crm_activities')
            ->where('account_id', $accountId)
            ->where('type', 'task')
            ->whereNull('done_at')
            ->where('title', 'LIKE', $tituloPrefix . '%')
            ->exists();
    }

    private function emCooldown(int $accountId, string $tipo, ?int $userId): bool
    {
        $dias = self::COOLDOWNS[$tipo] ?? 1;

        return DB::table('crm_alertas_enviados')
            ->where('account_id', $accountId)
            ->where('tipo_alerta', $tipo)
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->where('created_at', '>=', Carbon::now()->subDays($dias))
            ->exists();
    }

    private function alertaJaEnviado(int $accountId, string $tipo, int $refId): bool
    {
        return DB::table('crm_alertas_enviados')
            ->where('account_id', $accountId)
            ->where('tipo_alerta', $tipo)
            ->where('ref_id', $refId)
            ->exists();
    }

    private function registrarAlerta(int $accountId, string $tipo, ?int $refId = null, ?int $userId = null): void
    {
        DB::table('crm_alertas_enviados')->insert([
            'account_id'  => $accountId,
            'tipo_alerta' => $tipo,
            'ref_id'      => $refId,
            'user_id'     => $userId,
            'created_at'  => now(),
        ]);
    }
}
