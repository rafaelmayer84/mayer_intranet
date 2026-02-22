<?php

namespace App\Console\Commands;

use App\Jobs\NexoQaSendSurveyJob;
use App\Models\NexoQaCampaign;
use App\Models\NexoQaSampledTarget;
use Illuminate\Console\Command;

class NexoQaDispatchSurveys extends Command
{
    protected $signature = 'nexo-qa:dispatch-surveys {--campaign= : ID da campanha} {--dry-run : Simular sem enviar}';
    protected $description = 'Dispara pesquisa QA via template WhatsApp para targets PENDING';

    public function handle(): int
    {
        $campaignId = $this->option('campaign');
        $dryRun = $this->option('dry-run');

        $query = NexoQaSampledTarget::where('send_status', 'PENDING');

        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }

        $targets = $query->get();

        if ($targets->isEmpty()) {
            $this->info('Nenhum target PENDING encontrado.');
            return 0;
        }

        $this->info("Encontrados {$targets->count()} targets PENDING.");

        if ($dryRun) {
            foreach ($targets as $t) {
                $this->line("  [DRY-RUN] Target #{$t->id} | Phone: ***" . substr($t->phone_e164, -4) . " | Campaign: {$t->campaign_id}");
            }
            $this->info('Dry-run finalizado. Nenhum envio realizado.');
            return 0;
        }

        $dispatched = 0;
        foreach ($targets as $target) {
            NexoQaSendSurveyJob::dispatch($target->id);
            $dispatched++;
            $this->line("  Dispatched Target #{$target->id}");
        }

        $this->info("$dispatched jobs dispatched. Execute 'php artisan queue:work --once' para processar.");
        return 0;
    }
}
