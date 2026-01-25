<?php

namespace App\Console\Commands;

use App\Services\Orchestration\IntegrationOrchestrator;
use Illuminate\Console\Command;

class SyncClientesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:clientes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza clientes do DataJuri e ESPO CRM';

    /**
     * Execute the console command.
     */
    public function handle(IntegrationOrchestrator $orchestrator): int
    {
        $this->info('Sincronizando clientes...');
        
        $result = $orchestrator->syncClientes();
        
        if ($result['success']) {
            $this->info('✓ Sincronização de clientes concluída!');
            $this->info("  Criados: {$result['criados']}");
            $this->info("  Atualizados: {$result['atualizados']}");
            $this->info("  Ignorados: {$result['ignorados']}");
            return 0;
        } else {
            $this->error('✗ Erro: ' . ($result['error'] ?? 'Erro desconhecido'));
            return 1;
        }
    }
}
