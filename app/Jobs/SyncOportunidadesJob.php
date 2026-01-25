<?php
namespace App\Jobs;
use App\Services\Orchestration\IntegrationOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

class SyncOportunidadesJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function handle(IntegrationOrchestrator $orch): void {
        $orch->syncOportunidades();
    }
}
