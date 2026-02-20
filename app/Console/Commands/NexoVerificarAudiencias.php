<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Nexo\NexoNotificacaoService;

class NexoVerificarAudiencias extends Command
{
    protected $signature = 'nexo:verificar-audiencias {--dias=10 : Antecedencia em dias}';
    protected $description = 'Envia lembretes WhatsApp de audiencias proximas via SendPulse template';

    public function handle(NexoNotificacaoService $service): int
    {
        $dias = (int) $this->option('dias');
        $this->info("Verificando audiencias nos proximos {$dias} dias...");

        $stats = $service->processarLembretesAudiencia($dias);

        $this->info("Resultado:");
        $this->info("  Total encontradas: {$stats['total']}");
        $this->info("  Enviados:          {$stats['enviados']}");
        $this->info("  Ja notificados:    {$stats['ja_enviados']}");
        $this->info("  Sem telefone:      {$stats['sem_telefone']}");
        $this->info("  Sem processo:      {$stats['sem_processo']}");
        $this->info("  Falha envio:       {$stats['falha']}");

        return self::SUCCESS;
    }
}
