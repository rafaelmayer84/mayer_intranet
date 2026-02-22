<?php

namespace App\Console\Commands;

use App\Models\NexoQaCampaign;
use App\Services\NexoQa\NexoQaSamplingService;
use Illuminate\Console\Command;

class NexoQaSampleCommand extends Command
{
    protected $signature = 'nexo:qa-sample {campaign_id? : ID da campanha (se vazio, processa todas ACTIVE)}';
    protected $description = 'Executa amostragem da pesquisa de qualidade NEXO QA (manual)';

    public function handle(NexoQaSamplingService $samplingService): int
    {
        $campaignId = $this->argument('campaign_id');

        if ($campaignId) {
            $campaign = NexoQaCampaign::find($campaignId);
            if (!$campaign) {
                $this->error("Campanha #{$campaignId} não encontrada.");
                return 1;
            }
            if (!$campaign->isActive()) {
                $this->warn("Campanha #{$campaignId} não está ACTIVE (status: {$campaign->status}).");
                return 1;
            }
            $campaigns = collect([$campaign]);
        } else {
            $campaigns = NexoQaCampaign::active()->get();
            if ($campaigns->isEmpty()) {
                $this->info('Nenhuma campanha ACTIVE encontrada.');
                return 0;
            }
        }

        foreach ($campaigns as $campaign) {
            $this->info("Processando campanha #{$campaign->id}: {$campaign->name}");
            $sampled = $samplingService->executeSampling($campaign);
            $this->info("  → {$sampled} alvos sorteados.");
        }

        $this->info('Amostragem concluída.');
        return 0;
    }
}
