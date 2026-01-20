<?php

namespace App\Console\Commands;

use App\Services\SyncService;
use Illuminate\Console\Command;

class FinanceiroSyncContasReceber extends Command
{
    protected $signature = 'financeiro:sync-contas-receber {--dry-run : Não grava} {--limit=0 : Limita itens} {--chunk=200 : Tamanho do lote}';
    protected $description = 'Sincroniza Contas a Receber do DataJuri para o banco local (contas_receber).';

    public function handle(SyncService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $chunk = (int) $this->option('chunk');

        $res = $service->sincronizarContasReceber($dryRun, $limit, $chunk);

        $this->line(json_encode($res, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        $this->line('Log: storage/logs/sync_debug.log');

        return $res['success'] ? self::SUCCESS : self::FAILURE;
    }
}
