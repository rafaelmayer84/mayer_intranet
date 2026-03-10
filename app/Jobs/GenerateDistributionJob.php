<?php

namespace App\Jobs;

use App\Services\Crm\CrmDistributionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDistributionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(public int $userId)
    {
        $this->onQueue('distribution');
    }

    public function handle(CrmDistributionService $service): void
    {
        Log::info('[DistributionJob] Iniciando', ['user_id' => $this->userId]);

        try {
            $proposal = $service->gerarProposta($this->userId, function (string $msg) {
                Log::info('[DistributionJob] ' . $msg);
            });

            Log::info('[DistributionJob] Concluido', [
                'proposal_id' => $proposal->id,
                'total' => count($proposal->assignments ?? []),
            ]);
        } catch (\Throwable $e) {
            Log::error('[DistributionJob] Falha', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }
}
