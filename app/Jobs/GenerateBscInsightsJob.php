<?php

namespace App\Jobs;

use App\Models\BscInsightRun;
use App\Services\BscInsights\V2\BscInsightsEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateBscInsightsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    private ?int $userId;
    private bool $force;
    private int $runId;

    public function __construct(int $runId, ?int $userId = null, bool $force = false)
    {
        $this->runId  = $runId;
        $this->userId = $userId;
        $this->force  = $force;
        $this->onQueue('bsc-insights');
    }

    public function handle(): void
    {
        $run = BscInsightRun::find($this->runId);
        if (!$run || $run->status !== 'queued') {
            Log::warning('GenerateBscInsightsJob: run invalida ou status errado', ['run_id' => $this->runId, 'status' => $run?->status]);
            return;
        }

        try {
            $engine = new BscInsightsEngineService();
            $engine->executeFromRun($run, $this->userId, $this->force);
        } catch (\Throwable $e) {
            Log::error('GenerateBscInsightsJob: falha', ['run_id' => $this->runId, 'error' => $e->getMessage()]);
            $run->markFailed($e->getMessage());
        }
    }
}
