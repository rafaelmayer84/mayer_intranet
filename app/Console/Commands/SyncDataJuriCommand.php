<?php
namespace App\Console\Commands;
use App\Services\DataJuriSyncService;
use Illuminate\Console\Command;
class SyncDataJuriCommand extends Command
{
    protected $signature = 'sync:datajuri {--entity=all : Entidade a sincronizar (pessoas|processos|movimentos|all)}';
    protected $description = 'Sincronizar dados do DataJuri para a Intranet';
    public function handle()
    {
        $this->info('🔄 Iniciando sincronização DataJuri...');
        
        $service = new DataJuriSyncService();
        $entity = $this->option('entity');
        if (!$service->authenticate()) {
            $this->error('❌ Falha na autenticação com DataJuri');
            return Command::FAILURE;
        }
        $this->info('✅ Autenticado com sucesso');
        switch ($entity) {
            case 'pessoas':
                $this->syncPessoas($service);
                break;
            case 'processos':
                $this->syncProcessos($service);
                break;
            case 'movimentos':
                $this->syncMovimentos($service);
                break;
            case 'all':
            default:
                $this->syncPessoas($service);
                $this->syncProcessos($service);
                $this->syncMovimentos($service);
                break;
        }
        $this->info('✅ Sincronização concluída!');
        return Command::SUCCESS;
    }
    private function syncPessoas($service)
    {
        $this->info('📊 Sincronizando Pessoas...');
        $result = $service->syncPessoas();
        
        if ($result['success']) {
            $this->info("   ✅ Processadas: {$result['count']} pessoas");
        } else {
            $this->error("   ❌ Erro: {$result['error']}");
        }
    }
    private function syncProcessos($service)
    {
        $this->info('⚖️  Sincronizando Processos...');
        $result = $service->syncProcessos();
        
        if ($result['success']) {
            $this->info("   ✅ Processados: {$result['count']} processos");
        } else {
            $this->error("   ❌ Erro: {$result['error']}");
        }
    }
    private function syncMovimentos($service)
    {
        $this->info('💰 Sincronizando Movimentos...');
        $result = $service->syncMovimentos();
        
        if ($result['success']) {
            $this->info("   ✅ Processados: {$result['count']} movimentos");
        } else {
            $this->error("   ❌ Erro: {$result['error']}");
        }
    }
}
