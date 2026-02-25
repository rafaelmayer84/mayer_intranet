<?php

namespace App\Console\Commands;

use App\Services\Crm\CrmHealthScoreService;
use Illuminate\Console\Command;

class CrmRecalcHealthCommand extends Command
{
    protected $signature = 'crm:recalc-health';
    protected $description = 'Recalcula health score de todos os accounts ativos do CRM';

    public function handle(CrmHealthScoreService $service): int
    {
        $this->info('Recalculando health scores...');
        $count = $service->recalculateAll();
        $this->info("Concluído: {$count} accounts atualizados.");

        return self::SUCCESS;
    }
}
