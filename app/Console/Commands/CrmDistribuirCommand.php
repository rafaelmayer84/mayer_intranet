<?php

namespace App\Console\Commands;

use App\Services\Crm\CrmDistributionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CrmDistribuirCommand extends Command
{
    protected $signature = 'crm:distribuir {--user=1 : ID do usuario que solicitou}';
    protected $description = 'Gera proposta de distribuicao de carteira via IA (background)';

    public function handle(CrmDistributionService $service): int
    {
        $userId = (int) $this->option('user');

        $this->info("Iniciando distribuicao de carteira para user_id={$userId}...");
        Log::info('[CrmDistribuir] Iniciando geracao de proposta', ['user_id' => $userId]);

        try {
            $proposal = $service->gerarProposta($userId, function (string $msg) {
                $this->line("  > {$msg}");
                Log::info('[CrmDistribuir] ' . $msg);
            });

            $total = count($proposal->assignments ?? []);
            $this->info("Proposta #{$proposal->id} gerada com {$total} clientes.");
            Log::info('[CrmDistribuir] Proposta gerada', [
                'proposal_id' => $proposal->id,
                'total_assignments' => $total,
                'status' => $proposal->status,
            ]);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("Erro: {$e->getMessage()}");
            Log::error('[CrmDistribuir] Falha na geracao', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return self::FAILURE;
        }
    }
}
