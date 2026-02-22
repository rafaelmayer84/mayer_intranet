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

            // Resolver responsável
            $responsibleUserId = $this->resolver->resolveResponsibleUser(
                $candidate->source_type,
                $candidate->source_id,
                $phoneHash
            );

            // Resolver última interação
            $lastInteraction = $this->resolver->resolveLastInteractionAt($phoneE164);

            // Se não tem responsável → SKIPPED
            $sendStatus = $responsibleUserId === null ? 'SKIPPED' : 'PENDING';
            $skipReason = $responsibleUserId === null ? 'Sem responsável identificado' : null;

            NexoQaSampledTarget::create([
                'campaign_id' => $campaign->id,
                'source_type' => $candidate->source_type,
                'source_id' => $candidate->source_id,
                'phone_e164' => $phoneE164,
                'phone_hash' => $phoneHash,
                'responsible_user_id' => $responsibleUserId,
                'last_interaction_at' => $lastInteraction,
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
     * Monta o universo elegível combinando DataJuri e NEXO.
     */
    private function buildEligibleUniverse(NexoQaCampaign $campaign, Carbon $now): Collection
    {
        $cooldownDate = $now->copy()->subDays($campaign->cooldown_days);
        $lookbackDate = $campaign->lookback_days > 0
            ? $now->copy()->subDays($campaign->lookback_days)
            : null;

        // Telefones que já receberam pesquisa no cooldown (por phone_hash + campaign)
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

        // Fonte 1: Clientes DataJuri com telefone válido
        $dataJuriQuery = DB::table('clientes')
            ->select([
                DB::raw("'DATAJURI' as source_type"),
                DB::raw('datajuri_id as source_id'),
                DB::raw("COALESCE(celular, telefone) as phone"),
            ])
            ->where(function ($q) {
                $q->whereNotNull('celular')->where('celular', '!=', '')
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('telefone')->where('telefone', '!=', '');
                    });
            });

        // Fonte 2: Conversas NEXO com telefone
        $nexoQuery = DB::table('wa_conversations')
            ->select([
                DB::raw("'NEXO' as source_type"),
                DB::raw('id as source_id'),
                'phone',
            ])
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        // Aplicar lookback se configurado (filtrar por última interação)
        if ($lookbackDate !== null) {
            $nexoQuery->where('updated_at', '>=', $lookbackDate);
        }

        // Combinar fontes
        $allCandidates = collect();

        foreach ([$dataJuriQuery->get(), $nexoQuery->get()] as $batch) {
            foreach ($batch as $row) {
                if (empty($row->phone)) {
                    continue;
                }
                $normalized = NexoQaResolverService::normalizePhone($row->phone);
                if (strlen($normalized) < 12) {
                    continue; // Telefone inválido (precisa ter pelo menos 55+DDD+8dig)
                }
                $hash = hash('sha256', $normalized);
                if (in_array($hash, $excludedHashes, true)) {
                    continue;
                }
                // Deduplicar por phone_hash
                if (!$allCandidates->contains(fn($c) => hash('sha256', NexoQaResolverService::normalizePhone($c->phone)) === $hash)) {
                    $allCandidates->push($row);
                }
            }
        }

        return $allCandidates;
    }
}
