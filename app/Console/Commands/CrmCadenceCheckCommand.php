<?php

namespace App\Console\Commands;

use App\Services\Crm\CrmCadenceService;
use Illuminate\Console\Command;

class CrmCadenceCheckCommand extends Command
{
    protected $signature = 'crm:cadence-check';
    protected $description = 'Verifica cadências CRM vencendo hoje e notifica (sininho + email)';

    public function handle(CrmCadenceService $service): int
    {
        $this->info('Verificando cadências CRM...');
        $stats = $service->verificarENotificar();
        $this->info("Notificações: {$stats['notificacoes']} | Emails: {$stats['emails']}");
        return self::SUCCESS;
    }
}
