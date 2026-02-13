<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmEvent;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmOpportunityService
{
    /**
     * Cria oportunidade ou retorna existente aberta para o account.
     */
    public function createOrGetOpen(
        int $accountId,
        string $source = 'manual',
        string $type = 'aquisicao',
        ?string $area = null,
        ?string $title = null,
        ?int $ownerUserId = null
    ): CrmOpportunity {
        // Buscar opp aberta existente para esse account
        $existing = CrmOpportunity::where('account_id', $accountId)
            ->where('status', 'open')
            ->where('source', $source)
            ->first();

        if ($existing) return $existing;

        $account = CrmAccount::findOrFail($accountId);
        $firstStage = CrmStage::active()->ordered()->first();

        if (!$firstStage) {
            throw new \RuntimeException('Nenhum stage ativo configurado no CRM.');
        }

        $opp = CrmOpportunity::create([
            'account_id'     => $accountId,
            'stage_id'       => $firstStage->id,
            'type'           => $type,
            'title'          => $title ?: "Nova oportunidade - {$account->name}",
            'area'           => $area,
            'source'         => $source,
            'owner_user_id'  => $ownerUserId,
            'status'         => 'open',
            'next_action_at' => now()->addDays(2),
        ]);

        CrmEvent::create([
            'account_id'     => $accountId,
            'opportunity_id' => $opp->id,
            'type'           => 'opportunity_created',
            'payload'        => ['source' => $source, 'type' => $type],
            'happened_at'    => now(),
        ]);

        Log::info("[CRM] Opp #{$opp->id} criada para account #{$accountId} (source={$source})");
        return $opp;
    }

    /**
     * Move oportunidade para um novo stage.
     */
    public function moveToStage(CrmOpportunity $opp, int $stageId, ?int $userId = null): CrmOpportunity
    {
        $oldStage = $opp->stage;
        $newStage = CrmStage::findOrFail($stageId);

        $opp->stage_id = $stageId;

        if ($newStage->is_won) {
            $opp->status = 'won';
            $opp->won_at = now();
        } elseif ($newStage->is_lost) {
            $opp->status = 'lost';
            $opp->lost_at = now();
        } else {
            $opp->status = 'open';
        }

        $opp->save();

        CrmEvent::create([
            'account_id'         => $opp->account_id,
            'opportunity_id'     => $opp->id,
            'type'               => 'stage_changed',
            'payload'            => [
                'from_stage' => $oldStage->slug ?? null,
                'to_stage'   => $newStage->slug,
            ],
            'happened_at'        => now(),
            'created_by_user_id' => $userId,
        ]);

        // Atualizar last_touch no account
        $opp->account->update(['last_touch_at' => now()]);

        return $opp;
    }

    /**
     * Marcar como ganho.
     */
    public function markWon(CrmOpportunity $opp, ?int $userId = null): CrmOpportunity
    {
        $wonStage = CrmStage::where('is_won', true)->first();
        if (!$wonStage) throw new \RuntimeException('Stage "Ganho" não encontrado.');
        return $this->moveToStage($opp, $wonStage->id, $userId);
    }

    /**
     * Marcar como perdido.
     */
    public function markLost(CrmOpportunity $opp, ?string $reason = null, ?int $userId = null): CrmOpportunity
    {
        $lostStage = CrmStage::where('is_lost', true)->first();
        if (!$lostStage) throw new \RuntimeException('Stage "Perdido" não encontrado.');

        $opp->lost_reason = $reason;
        $opp->save();

        $opp = $this->moveToStage($opp, $lostStage->id, $userId);

        CrmEvent::create([
            'account_id'         => $opp->account_id,
            'opportunity_id'     => $opp->id,
            'type'               => 'opportunity_lost',
            'payload'            => ['reason' => $reason],
            'happened_at'        => now(),
            'created_by_user_id' => $userId,
        ]);

        return $opp;
    }
}
