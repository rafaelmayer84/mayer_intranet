<?php

namespace App\Console\Commands;

use App\Services\CrmAiInsightService;
use Illuminate\Console\Command;

class CrmGenerateInsights extends Command
{
    protected $signature = 'crm:generate-insights {--type=weekly : Tipo: weekly ou account} {--account= : ID da conta (obrigatório se type=account)}';

    protected $description = 'Gera insights IA para o CRM (weekly digest ou sugestão por conta)';

    public function handle(): int
    {
        $service = app(CrmAiInsightService::class);
        $type = $this->option('type');

        if ($type === 'weekly') {
            $this->info('Gerando weekly digest...');
            $insight = $service->generateWeeklyDigest();

            if ($insight) {
                $this->info("Digest gerado: {$insight->titulo} (prioridade: {$insight->priority})");
                return self::SUCCESS;
            } else {
                $this->error('Falha ao gerar digest — ver logs.');
                return self::FAILURE;
            }
        }

        if ($type === 'account') {
            $accountId = $this->option('account');
            if (!$accountId) {
                $this->error('Informe --account=ID');
                return self::FAILURE;
            }

            $this->info("Gerando sugestão para account #{$accountId}...");
            $insight = $service->generateAccountAction((int) $accountId);

            if ($insight) {
                $this->info("Sugestão gerada: {$insight->titulo}");
                $this->info("Ação: {$insight->action_suggested}");
                return self::SUCCESS;
            } else {
                $this->error('Falha ao gerar sugestão — ver logs.');
                return self::FAILURE;
            }
        }

        $this->error("Tipo inválido: {$type}. Use 'weekly' ou 'account'.");
        return self::FAILURE;
    }
}
