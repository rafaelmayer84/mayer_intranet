<?php

namespace App\Console\Commands;

use App\Services\Crm\CrmAdminProcessAlertService;
use Illuminate\Console\Command;

class CrmAdminProcessAlertsCommand extends Command
{
    protected $signature   = 'admin-processes:alerts';
    protected $description = 'Verifica prazos, inatividade e etapas atrasadas nos processos administrativos e emite notificações proativas.';

    public function handle(CrmAdminProcessAlertService $service): int
    {
        $this->info('[admin-processes:alerts] Iniciando verificação ' . now()->toDateTimeString());

        $prazos   = $service->verificarPrazos();
        $etapas   = $service->verificarEtapasAtrasadas();
        $inativos = $service->verificarInatividade();

        $total = $prazos + $etapas + $inativos;

        $this->info("  Alertas de prazo:     {$prazos}");
        $this->info("  Etapas atrasadas:     {$etapas}");
        $this->info("  Processos inativos:   {$inativos}");
        $this->info("  Total de notificações: {$total}");

        return self::SUCCESS;
    }
}
