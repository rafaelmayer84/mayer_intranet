<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Crm\CrmDistributionService;
use App\Models\Crm\CrmDistributionProposal;

class CrmDistributeCommand extends Command
{
    protected $signature = 'crm:distribute {--user=1 : User ID do criador}';
    protected $description = 'Gera proposta de distribuição de carteira via IA';

    public function handle(CrmDistributionService $service)
    {
        $this->info('Gerando proposta de distribuição...');
        $this->info('Carregando dados dos clientes ativos...');

        try {
            $proposal = $service->gerarProposta((int) $this->option('user'), function($msg) {
                $this->line('  → ' . $msg);
            });

            $this->info("Proposta #{$proposal->id} gerada com sucesso!");
            $this->info("Total de atribuições: " . count($proposal->assignments));

            if ($proposal->summary) {
                $this->newLine();
                $this->info("Resumo:");
                foreach ($proposal->summary as $s) {
                    $this->line("  {$s['name']}: {$s['qty']} clientes (max {$s['max']})");
                }
            }

            $this->newLine();
            $this->info("Acesse /crm/distribuicao/{$proposal->id}/revisar para revisar e aplicar.");

        } catch (\Throwable $e) {
            $this->error("Erro: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
