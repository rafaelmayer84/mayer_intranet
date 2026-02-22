<?php

namespace App\Services\NexoQa;

use App\Models\NexoQaCampaign;
use App\Models\NexoQaSampledTarget;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NexoQaSamplingService
{
    private NexoQaResolverService $resolver;

    public function __construct(NexoQaResolverService $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Executa amostragem para a campanha:
     * 1. Monta universo elegível
     * 2. Sorteia sample_size
     * 3. Resolve responsável e última interação
     * 4. Grava nexo_qa_sampled_targets
     *
     * @return int Quantidade de alvos sorteados
     */
    public function executeSampling(NexoQaCampaign $campaign): int
    {
        $now = Carbon::now('America/Sao_Paulo');

        // Montar universo elegível (DataJuri + NEXO)
        $universe = $this->buildEligibleUniverse($campaign, $now);

        if ($universe->isEmpty()) {
            Log::info('[NexoQA] Amostragem: universo elegível vazio', [
                'campaign_id' => $campaign->id,
            ]);
            return 0;
        }

        // Sortear amostra
        $sampleSize = min($campaign->sample_size, $universe->count());
        $sampled = $universe->random($sampleSize);

        $created = 0;

        foreach ($sampled as $candidate) {
            $phoneE164 = NexoQaResolverService::normalizePhone($candidate->phone);
            $phoneHash = hash('sha256', $phoneE164);

            // Responsável vem direto do candidato (created_by_user_id ou assigned_user_id)
            $responsibleUserId = isset($candidate->responsible_user_id) && $candidate->responsible_user_id
                ? (int) $candidate->responsible_user_id
                : $this->resolver->resolveResponsibleUser($candidate->source_type, $candidate->source_id, $phoneHash);

            // Se não tem responsável → SKIPPED
            $sendStatus = $responsibleUserId === null ? 'SKIPPED' : 'PENDING';
            $skipReason = $responsibleUserId === null ? 'Sem responsável identificado' : null;

            NexoQaSampledTarget::create([
                'campaign_id' => $campaign->id,
                'source_type' => $candidate->source_type,
                'source_id' => (int) $candidate->source_id,
                'phone_e164' => $phoneE164,
                'phone_hash' => $phoneHash,
                'responsible_user_id' => $responsibleUserId,
                'last_interaction_at' => $now,
                'sampled_at' => $now,
                'send_status' => $sendStatus,
                'skip_reason' => $skipReason,
            ]);

            $created++;
        }

        Log::info('[NexoQA] Amostragem concluída', [
            'campaign_id' => $campaign->id,
            'universe_size' => $universe->count(),
            'sampled' => $created,
            'skipped' => $sampled->filter(fn($c) => $c->source_type)->count() - $created + $sampled->count() - $created,
        ]);

        return $created;
    }

    /**
     * Monta o universo elegível baseado em INTERAÇÕES RECENTES:
     * 1. crm_events (stage_changed, lead_status_changed, etc.)
     * 2. crm_activities com done_at preenchido
     * 3. wa_conversations com last_message_at recente
     *
     * Aplica cooldown e opt-out para não repetir pesquisa.
     */
    private function buildEligibleUniverse(NexoQaCampaign $campaign, Carbon $now): Collection
    {
        $cooldownDate = $now->copy()->subDays($campaign->cooldown_days);
        $scanWindow = $campaign->lookback_days > 0
            ? $now->copy()->subDays($campaign->lookback_days)
            : $now->copy()->subDays(7);

        // Telefones que já receberam pesquisa no cooldown
        $recentPhoneHashes = DB::table('nexo_qa_sampled_targets')
            ->where('campaign_id', $campaign->id)
            ->where('sampled_at', '>=', $cooldownDate)
            ->pluck('phone_hash')
            ->toArray();

        // Telefones que deram opt-out
        $optedOutHashes = DB::table('nexo_qa_responses_identity')
            ->where('opted_out', true)
            ->pluck('phone_hash')
            ->toArray();

        $excludedHashes = array_unique(array_merge($recentPhoneHashes, $optedOutHashes));

        $allCandidates = collect();
        $seenHashes = [];

        // --- FONTE 1: CRM Events recentes ---
        $crmEvents = DB::table('crm_events')
            ->join('crm_identities', function ($j) {
                $j->on('crm_identities.account_id', '=', 'crm_events.account_id')
                   ->where('crm_identities.kind', 'phone');
            })
            ->where('crm_events.created_at', '>=', $scanWindow)
            ->whereNotIn('crm_events.type', ['opportunity_imported', 'opportunity_created'])
            ->whereNotNull('crm_events.created_by_user_id')
            ->select([
                DB::raw("'CRM_EVENT' as source_type"),
                DB::raw('crm_events.id as source_id'),
                DB::raw('crm_identities.value as phone'),
                DB::raw('crm_events.created_by_user_id as responsible_user_id'),
            ])
            ->get();

        foreach ($crmEvents as $row) {
            $this->addCandidate($allCandidates, $seenHashes, $excludedHashes, $row);
        }

        // --- FONTE 2: CRM Activities concluídas ---
        $crmActivities = DB::table('crm_activities')
            ->join('crm_identities', function ($j) {
                $j->on('crm_identities.account_id', '=', 'crm_activities.account_id')
                   ->where('crm_identities.kind', 'phone');
            })
            ->whereNotNull('crm_activities.done_at')
            ->where('crm_activities.done_at', '>=', $scanWindow)
            ->select([
                DB::raw("'CRM_ACTIVITY' as source_type"),
                DB::raw('crm_activities.id as source_id'),
                DB::raw('crm_identities.value as phone'),
                DB::raw('crm_activities.created_by_user_id as responsible_user_id'),
            ])
            ->get();

        foreach ($crmActivities as $row) {
            $this->addCandidate($allCandidates, $seenHashes, $excludedHashes, $row);
        }

        // --- FONTE 3: Conversas NEXO com mensagem recente ---
        $nexoConversations = DB::table('wa_conversations')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where('last_message_at', '>=', $scanWindow)
            ->select([
                DB::raw("'NEXO' as source_type"),
                DB::raw('id as source_id'),
                'phone',
                DB::raw('assigned_user_id as responsible_user_id'),
            ])
            ->get();

        foreach ($nexoConversations as $row) {
            $this->addCandidate($allCandidates, $seenHashes, $excludedHashes, $row);
        }

        Log::info('[NexoQA] Universo elegível construído', [
            'campaign_id' => $campaign->id,
            'crm_events' => $crmEvents->count(),
            'crm_activities' => $crmActivities->count(),
            'nexo_conversations' => $nexoConversations->count(),
            'total_elegivel' => $allCandidates->count(),
            'excluidos_cooldown' => count($recentPhoneHashes),
            'excluidos_optout' => count($optedOutHashes),
        ]);

        return $allCandidates;
    }

    /**
     * Adiciona candidato se telefone válido, não excluído e não duplicado.
     */
    private function addCandidate(Collection &$candidates, array &$seenHashes, array $excludedHashes, object $row): void
    {
        if (empty($row->phone)) {
            return;
        }

        $normalized = NexoQaResolverService::normalizePhone($row->phone);
        if (strlen($normalized) < 12) {
            return;
        }

        $hash = hash('sha256', $normalized);

        if (in_array($hash, $excludedHashes, true)) {
            return;
        }

        if (isset($seenHashes[$hash])) {
            return;
        }

        $seenHashes[$hash] = true;
        $candidates->push($row);
    }
}
