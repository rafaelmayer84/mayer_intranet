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
        $this->info('Iniciando sincronizacao DataJuri...');
        $service = new DataJuriSyncService();

        if (!$service->authenticate()) {
            $this->error('Falha na autenticacao com DataJuri');
            return Command::FAILURE;
        }
        $this->info('Autenticado com sucesso');

        $syncId = 'cmd_' . now()->format('Ymd_His');
        $service->setSyncId($syncId);
        $entity = $this->option('entity');

        switch ($entity) {
            case 'pessoas':
                $this->info('Sincronizando Pessoas...');
                $service->syncPessoas();
                break;
            case 'processos':
                $this->info('Sincronizando Processos...');
                $service->syncProcessos();
                break;
            case 'movimentos':
                $this->info('Sincronizando Movimentos...');
                $service->syncMovimentos();
                break;
            case 'all':
            default:
                $this->info('Sincronizacao completa...');
                $service->syncAll();
                break;
        }

        $stats = $service->getStats();
        $this->info('Sincronizacao concluida!');
        $this->table(['Metrica', 'Valor'], collect($stats)->map(fn($v, $k) => [$k, $v])->toArray());
        return Command::SUCCESS;
    }
}
