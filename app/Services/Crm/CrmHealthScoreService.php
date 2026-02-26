<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmAccount;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CrmHealthScoreService
{
    /**
     * Recalcula o health_score de um account (0-100).
     *
     * Fatores:
     *  - Recência de contato (40 pts)
     *  - Processos ativos    (20 pts)
     *  - Financeiro em dia   (25 pts)
     *  - Atividade recente   (15 pts)
     */
    public function recalculate(int $accountId): int
    {
        $account = CrmAccount::findOrFail($accountId);
        $score = 0;

        // --- Fator 1: Recência de contato (40 pts) ---
        $lastTouch = $account->last_touch_at ? Carbon::parse($account->last_touch_at) : null;
        if ($lastTouch) {
            $days = $lastTouch->diffInDays(now());
            $score += match (true) {
                $days <= 7   => 40,
                $days <= 15  => 30,
                $days <= 30  => 20,
                $days <= 60  => 10,
                default      => 0,
            };
        }

        // --- Fator 2: Processos ativos (20 pts) ---
        if ($account->datajuri_pessoa_id) {
            $processosAtivos = DB::table('processos')
                ->where('cliente_datajuri_id', $account->datajuri_pessoa_id)
                ->whereIn('status', ['Ativo', 'Em andamento', 'Em Andamento'])
                ->count();

            $score += match (true) {
                $processosAtivos >= 3 => 20,
                $processosAtivos == 2 => 15,
                $processosAtivos == 1 => 10,
                default               => 0,
            };
        }

        // --- Fator 3: Financeiro em dia (25 pts) ---
        if ($account->datajuri_pessoa_id) {
            $vencidas = DB::table('contas_receber')
                ->where('pessoa_datajuri_id', $account->datajuri_pessoa_id)
                ->whereNotIn('status', ['Concluído', 'Concluido', 'Excluido', 'Excluído'])
                ->where('data_vencimento', '<', now()->format('Y-m-d'))
                ->count();

            $score += match (true) {
                $vencidas == 0            => 25,
                $vencidas <= 2            => 15,
                default                   => 5,
            };
        } else {
            // Sem dados financeiros = neutro
            $score += 15;
        }

        // --- Fator 4: Atividade recente - 30 dias (15 pts) ---
        $recentActivities = DB::table('crm_activities')
            ->where('account_id', $accountId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $score += match (true) {
            $recentActivities >= 5 => 15,
            $recentActivities >= 3 => 10,
            $recentActivities >= 1 => 5,
            default                => 0,
        };

        // Persistir com auditoria
        $oldScore = $account->health_score;
        $account->update(['health_score' => $score]);

        if ($oldScore !== $score) {
            \App\Models\Crm\CrmEvent::create([
                'account_id'  => $account->id,
                'type'        => 'health_score_changed',
                'payload'     => ['from' => $oldScore, 'to' => $score],
                'happened_at' => now(),
            ]);
        }

        return $score;
    }

    /**
     * Recalcula todos os accounts ativos (para command agendado).
     */
    public function recalculateAll(): int
    {
        $ids = CrmAccount::where('lifecycle', '!=', 'arquivado')
            ->pluck('id');

        $count = 0;
        foreach ($ids as $id) {
            try {
                $this->recalculate($id);
                $count++;
            } catch (\Exception $e) {
                \Log::warning("[CRM] HealthScore falhou account #{$id}: {$e->getMessage()}");
            }
        }

        return $count;
    }
}
