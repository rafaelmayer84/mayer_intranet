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
     * Executa amostragem ESTRATIFICADA POR ADVOGADO:
     * 1. Monta universo elegível (WhatsApp + CRM Activities)
     * 2. Agrupa por responsible_user_id
     * 3. Distribui quota igualitária entre advogados
     * 4. Sorteia dentro de cada grupo
     * 5. Grava nexo_qa_sampled_targets
     *
     * @return int Quantidade de alvos sorteados
     */
    public function executeSampling(NexoQaCampaign $campaign): int
    {
        $now = Carbon::now('America/Sao_Paulo');

        // Montar universo elegível (WhatsApp + CRM Activities)
        $universe = $this->buildEligibleUniverse($campaign, $now);

        if ($universe->isEmpty()) {
            Log::info('[NexoQA] Amostragem: universo elegível vazio', [
                'campaign_id' => $campaign->id,
            ]);
            return 0;
        }

        // Agrupar por advogado responsável
        $byLawyer = $universe->groupBy(function ($candidate) {
            return $candidate->responsible_user_id ?? 0;
        })->filter(function ($group, $key) {
            return $key > 0; // Excluir candidatos sem responsável
        });

        if ($byLawyer->isEmpty()) {
            Log::info('[NexoQA] Amostragem: nenhum candidato com responsável identificado', [
                'campaign_id' => $campaign->id,
                'universe_size' => $universe->count(),
            ]);
            return 0;
        }

        $numLawyers = $byLawyer->count();
        $sampleSize = $campaign->sample_size;

        // Distribuir quota igualitária
        $baseQuota = (int) floor($sampleSize / $numLawyers);
        $remainder = $sampleSize % $numLawyers;

        // Garantir mínimo 1 por advogado (se sample_size permite)
        if ($baseQuota < 1 && $sampleSize >= $numLawyers) {
            $baseQuota = 1;
            $remainder = $sampleSize - $numLawyers;
        } elseif ($baseQuota < 1) {
            // sample_size menor que número de advogados: sortear quais advogados participam
            $baseQuota = 1;
            $remainder = 0;
            $byLawyer = $byLawyer->shuffle()->take($sampleSize);
            $numLawyers = $byLawyer->count();
        }

        $created = 0;
        $lawyerIndex = 0;

        foreach ($byLawyer as $userId => $candidates) {
            // Quota deste advogado: base + 1 extra para os primeiros (round-robin do remainder)
            $quota = $baseQuota + ($lawyerIndex < $remainder ? 1 : 0);
            $lawyerIndex++;

            // Sortear do pool deste advogado
            $pick = min($quota, $candidates->count());
            $sampled = $candidates->random($pick);

            // Garantir Collection mesmo se random retornou objeto único
            if (!$sampled instanceof Collection) {
                $sampled = collect([$sampled]);
            }

            foreach ($sampled as $candidate) {
                $phoneE164 = NexoQaResolverService::normalizePhone($candidate->phone);
                $phoneHash = hash('sha256', $phoneE164);

                NexoQaSampledTarget::create([
                    'campaign_id' => $campaign->id,
                    'source_type' => $candidate->source_type,
                    'source_id' => (int) $candidate->source_id,
                    'phone_e164' => $phoneE164,
                    'phone_hash' => $phoneHash,
                    'responsible_user_id' => (int) $userId,
                    'last_interaction_at' => $now,
                    'sampled_at' => $now,
                    'send_status' => 'PENDING',
                ]);

                $created++;
            }
        }

        Log::info('[NexoQA] Amostragem estratificada concluída', [
            'campaign_id' => $campaign->id,
            'universe_size' => $universe->count(),
            'advogados' => $numLawyers,
            'quota_base' => $baseQuota,
            'sampled' => $created,
        ]);

        return $created;
    }

    /**
     * Monta o universo elegível baseado em INTERAÇÕES RECENTES:
     * 1. CRM Activities concluídas (reuniões, ligações, etc.)
     * 2. Conversas WhatsApp NEXO com mensagem recente
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

        // --- FONTE 1: CRM Activities concluídas ---
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

        // --- FONTE 2: Conversas WhatsApp NEXO com mensagem recente ---
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
