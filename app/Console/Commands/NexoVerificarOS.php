<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Nexo\NexoNotificacaoService;

class NexoVerificarOS extends Command
{
    protected $signature = 'nexo:verificar-os {--dias=3 : Dias para buscar andamentos}';
    protected $description = 'Detecta Ordens de Servico com andamento recente e cria notificacoes pendentes';

    public function handle(NexoNotificacaoService $service): int
    {
        $dias = (int) $this->option('dias');
        $this->info("Verificando OS com andamento nos ultimos {$dias} dias...");

        $stats = $service->processarOrdensServico($dias);

        $this->info("Total ativas encontradas: {$stats['total']}");
        $this->info("Criadas pending: {$stats['criados']}");
        $this->info("Ja notificadas: {$stats['ja_notificados']}");
        $this->info("Sem advogado mapeado: {$stats['sem_advogado']}");

        return Command::SUCCESS;
    }
}
