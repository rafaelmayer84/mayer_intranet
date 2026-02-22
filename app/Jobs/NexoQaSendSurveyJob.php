<?php

namespace App\Jobs;

use App\Models\NexoQaSampledTarget;
use App\Services\NexoQa\NexoQaSurveyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NexoQaSendSurveyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $targetId;
    public int $tries = 3;
    public int $timeout = 30;
    public int $backoff = 10;

    public function __construct(int $targetId)
    {
        $this->targetId = $targetId;
        $this->onQueue('default');
    }

    public function handle(NexoQaSurveyService $surveyService): void
    {
        $target = NexoQaSampledTarget::find($this->targetId);

        if ($target === null) {
            Log::warning('[NexoQA] Target não encontrado para envio', [
                'target_id' => $this->targetId,
            ]);
            return;
        }

        if (!$target->isPending()) {
            Log::info('[NexoQA] Target não está PENDING, ignorando', [
                'target_id' => $this->targetId,
                'status' => $target->send_status,
            ]);
            return;
        }

        $surveyService->sendSurvey($target);
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $target = NexoQaSampledTarget::find($this->targetId);
        if ($target !== null && $target->isPending()) {
            $target->markFailed('Job failed: ' . $exception->getMessage());
        }

        Log::error('[NexoQA] Job de envio falhou definitivamente', [
            'target_id' => $this->targetId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
