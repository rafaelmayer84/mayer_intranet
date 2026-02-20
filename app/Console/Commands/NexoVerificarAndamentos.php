<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Nexo\NexoNotificacaoService;

class NexoVerificarAndamentos extends Command
{
    protected $signature = 'nexo:verificar-andamentos';
    protected $description = 'Detecta novos andamentos processuais e cria notificacoes pendentes para aprovacao';

    public function handle(NexoNotificacaoService $service): int
    {
        $this->info("Verificando andamentos novos...");

        $stats = $service->processarAndamentosNovos();

        $this->info("Resultado:");
        $this->info("  Total novos:       {$stats['total']}");
        $this->info("  Criados pending:   {$stats['criados']}");
        $this->info("  Ja existentes:     {$stats['ja_existe']}");
        $this->info("  Sem cliente:       {$stats['sem_cliente']}");
        $this->info("  Sem telefone:      {$stats['sem_telefone']}");
        $this->info("  Sem advogado:      {$stats['sem_advogado']}");

        return self::SUCCESS;
    }
}
