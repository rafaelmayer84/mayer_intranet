<?php

namespace App\Console\Commands;

use App\Services\EspoCrmSyncService;
use Illuminate\Console\Command;

class SyncEspoCrmCommand extends Command
{
    protected $signature = 'sync:espocrm {--entity=all : Entidade a sincronizar (accounts|leads|opportunities|all)}';
    protected $description = 'Sincronizar dados do ESPO CRM para a Intranet';

    public function handle()
    {
        $this->info('🔄 Iniciando sincronização ESPO CRM...');
        
        $service = new EspoCrmSyncService();
        $entity = $this->option('entity');

        switch ($entity) {
            case 'accounts':
                $this->syncAccounts($service);
                break;
            case 'leads':
                $this->syncLeads($service);
                break;
            case 'opportunities':
                $this->syncOpportunities($service);
                break;
            case 'all':
            default:
                $this->syncAccounts($service);
                $this->syncLeads($service);
                $this->syncOpportunities($service);
                break;
        }

        $this->info('✅ Sincronização concluída!');
        return Command::SUCCESS;
    }

    private function syncAccounts($service)
    {
        $this->info('🏢 Sincronizando Contas...');
        $result = $service->syncAccounts();
        
        if ($result['success']) {
            $this->info("   ✅ Importados: {$result['imported']}, Atualizados: {$result['updated']}");
        } else {
            $this->error("   ❌ Erro: {$result['message']}");
        }
    }

    private function syncLeads($service)
    {
        $this->info('📨 Sincronizando Leads...');
        $result = $service->syncLeads();
        
        if ($result['success']) {
            $this->info("   ✅ Importados: {$result['imported']}, Atualizados: {$result['updated']}");
        } else {
            $this->error("   ❌ Erro: {$result['message']}");
        }
    }

    private function syncOpportunities($service)
    {
        $this->info('💼 Sincronizando Oportunidades...');
        $result = $service->syncOpportunities();
        
        if ($result['success']) {
            $this->info("   ✅ Importados: {$result['imported']}, Atualizados: {$result['updated']}");
        } else {
            $this->error("   ❌ Erro: {$result['message']}");
        }
    }
}
