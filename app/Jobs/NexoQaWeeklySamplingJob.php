<?php

namespace App\Jobs;

use App\Models\NexoQaCampaign;
use App\Services\NexoQa\NexoQaSamplingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NexoQaWeeklySamplingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $campaignId;
    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
        $this->onQueue('default');
    }

    public function handle(NexoQaSamplingService $samplingService): void
    {
        $campaign = NexoQaCampaign::find($this->campaignId);

        if ($campaign === null || !$campaign->isActive()) {
            Log::info('[NexoQA] Sampling skipped: campanha inativa ou inexistente', [
                'campaign_id' => $this->campaignId,
            ]);
            return;
        }

        Log::info('[NexoQA] Iniciando amostragem semanal', [
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'sample_size' => $campaign->sample_size,
        ]);

        $sampled = $samplingService->executeSampling($campaign);

        Log::info('[NexoQA] Amostragem finalizada, enfileirando disparos', [
            'campaign_id' => $campaign->id,
            'sampled' => $sampled,
        ]);

        // Enfileirar disparo dos targets PENDING
        NexoQaDispatchPendingJob::dispatch($this->campaignId);
    }
}
