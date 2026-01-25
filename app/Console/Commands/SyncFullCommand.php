<?php

namespace App\Console\Commands;

use App\Services\Orchestration\IntegrationOrchestrator;
use Illuminate\Console\Command;

class SyncFullCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:full';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronização completa de todas as entidades';

    /**
     * Execute the console command.
     */
    public function handle(IntegrationOrchestrator $orchestrator): int
    {
        $this->info('Iniciando sincronização completa...');
        
        $result = $orchestrator->syncAll();
        
        if ($result['success']) {
            $this->info('✓ Sincronização concluída com sucesso!');
            $this->newLine();
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Clientes Criados', $result['clientes']['criados']],
                    ['Clientes Atualizados', $result['clientes']['atualizados']],
                    ['Leads Criados', $result['leads']['criados']],
                    ['Leads Atualizados', $result['leads']['atualizados']],
                    ['Oportunidades Criadas', $result['oportunidades']['criados']],
                    ['Oportunidades Atualizadas', $result['oportunidades']['atualizados']]
                ]
            );
            return 0;
        } else {
            $this->error('✗ Erro na sincronização: ' . ($result['error'] ?? 'Erro desconhecido'));
            return 1;
        }
    }
}
