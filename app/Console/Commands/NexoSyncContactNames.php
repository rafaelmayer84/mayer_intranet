<?php

namespace App\Console\Commands;

use App\Models\WaConversation;
use App\Services\SendPulseWhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NexoSyncContactNames extends Command
{
    protected $signature = 'nexo:sync-names {--limit=50 : Limite por execução} {--force : Reprocessar quem já tem nome}';
    protected $description = 'Sincroniza nomes dos contatos WhatsApp via API SendPulse';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $query = WaConversation::whereNotNull('phone')->where('phone', '!=', '');
        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('name')->orWhere('name', '')->orWhere('name', 'Sem nome');
            });
        }

        $conversations = $query->limit($limit)->get();
        $this->info("Encontradas {$conversations->count()} conversas para processar.");

        if ($conversations->isEmpty()) {
            $this->info('Nenhuma conversa sem nome.');
            return 0;
        }

        $service = app(SendPulseWhatsAppService::class);
        $updated = 0;
        $errors = 0;

        foreach ($conversations as $conv) {
            try {
                $phone = preg_replace('/\D/', '', $conv->phone);
                if (empty($phone)) continue;

                $contact = $service->getContactByPhone($phone);

                if ($contact && !empty($contact['name'])) {
                    $conv->name = $contact['name'];
                    $conv->save();
                    $updated++;
                    $this->line("  + [{$conv->id}] {$phone} -> {$contact['name']}");
                } else {
                    $this->line("  - [{$conv->id}] {$phone} -> sem nome no SendPulse");
                }

                usleep(200000);
            } catch (\Throwable $e) {
                $errors++;
                $this->error("  x [{$conv->id}] {$conv->phone} -> {$e->getMessage()}");
                Log::warning('nexo:sync-names error', ['conv_id' => $conv->id, 'error' => $e->getMessage()]);
                usleep(500000);
            }
        }

        $this->info("Concluido: {$updated} atualizados, {$errors} erros de " . $conversations->count() . " processados.");
        return 0;
    }
}
