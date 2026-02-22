<?php

namespace App\Jobs;

use App\Models\NexoQaSampledTarget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NexoQaDispatchPendingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $campaignId;
    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $targets = NexoQaSampledTarget::where('campaign_id', $this->campaignId)
            ->where('send_status', 'PENDING')
            ->get();

        if ($targets->isEmpty()) {
            Log::info('[NexoQA] Nenhum target PENDING para dispatch', [
                'campaign_id' => $this->campaignId,
            ]);
            return;
        }

        Log::info('[NexoQA] Enfileirando envio de pesquisas', [
            'campaign_id' => $this->campaignId,
            'count' => $targets->count(),
        ]);

        foreach ($targets as $target) {
            // Delay entre envios para não estourar rate limit do SendPulse
            NexoQaSendSurveyJob::dispatch($target->id)
                ->delay(now()->addSeconds($targets->search($target) * 3));
        }
    }
}
