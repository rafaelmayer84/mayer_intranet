<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WaConversation;
use App\Services\NexoConversationSyncService;

class NexoAutoLinkBackfillCommand extends Command
{
    protected $signature = 'nexo:autolink-backfill {--dry-run : Apenas simular sem salvar}';
    protected $description = 'Vincula automaticamente conversas WhatsApp a clientes/leads existentes';

    public function handle(NexoConversationSyncService $syncService): int
    {
        $dryRun = $this->option('dry-run');
        $this->info(($dryRun ? '[DRY-RUN] ' : '') . 'Iniciando backfill de autoLink...');

        $conversations = WaConversation::where(function ($q) {
            $q->whereNull('linked_cliente_id')->whereNull('linked_lead_id');
        })->orWhere(function ($q) {
            // Conversas com lead mas sem cliente (pode promover)
            $q->whereNotNull('linked_lead_id')->whereNull('linked_cliente_id');
        })->get();

        $this->info("Conversas a processar: {$conversations->count()}");

        $stats = ['cliente_linked' => 0, 'lead_linked' => 0, 'upgraded' => 0, 'unchanged' => 0];

        foreach ($conversations as $conv) {
            $beforeCliente = $conv->linked_cliente_id;
            $beforeLead = $conv->linked_lead_id;

            if (!$dryRun) {
                $syncService->autoLink($conv);
                $conv->refresh();
            }

            if ($dryRun) {
                // Simular
                $this->line("  #{$conv->id} [{$conv->phone}] {$conv->name} — seria processado");
                $stats['unchanged']++;
            } else {
                if (!$beforeCliente && $conv->linked_cliente_id) {
                    $label = $beforeLead ? 'UPGRADED lead→cliente' : 'LINKED cliente';
                    $this->line("  #{$conv->id} [{$conv->phone}] → {$label} #{$conv->linked_cliente_id}");
                    $beforeLead ? $stats['upgraded']++ : $stats['cliente_linked']++;
                } elseif (!$beforeLead && $conv->linked_lead_id) {
                    $this->line("  #{$conv->id} [{$conv->phone}] → LINKED lead #{$conv->linked_lead_id}");
                    $stats['lead_linked']++;
                } else {
                    $stats['unchanged']++;
                }
            }
        }

        $this->newLine();
        $this->info('Resultado:');
        $this->table(
            ['Métrica', 'Qtd'],
            collect($stats)->map(fn($v, $k) => [$k, $v])->values()->toArray()
        );

        return Command::SUCCESS;
    }
}
