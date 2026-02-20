<?php

namespace App\Console\Commands;

use App\Services\Crm\CrmCarteiraService;
use App\Services\Crm\CrmProactiveService;
use Illuminate\Console\Command;

class CrmRecalcularCarteira extends Command
{
    protected $signature = 'crm:recalcular-carteira';
    protected $description = 'Recalcula owner, lifecycle e last_touch_at da carteira CRM';

    public function handle(): int
    {
        $this->info('Iniciando recálculo da carteira CRM...');

        $service = new CrmCarteiraService();
        $stats = $service->recalcularCarteira();

        $this->info("Concluído em {$stats['tempo_s']}s | Total: {$stats['total']}");
        $this->info("Owners atualizados: {$stats['owner_updated']}");
        $this->info("Lifecycle: ativo={$stats['lifecycle']['ativo']}, adormecido={$stats['lifecycle']['adormecido']}, arquivado={$stats['lifecycle']['arquivado']}, onboarding={$stats['lifecycle']['onboarding']}");
        $this->info("Last touch atualizados: {$stats['touch_updated']}");

        // Frente C: Alertas e tasks proativas
        $this->info('Executando alertas proativos...');
        $proactive = new CrmProactiveService();
        $pStats = $proactive->executar($stats['lifecycle_changes'] ?? []);
        $this->info("Alertas: {$pStats['alertas_criados']} | Tasks: {$pStats['tasks_criadas']} | Cooldown skip: {$pStats['skipped_cooldown']}");

        return self::SUCCESS;
    }
}
