<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SendPulseWhatsAppService;
use App\Models\WaConversation;
use Illuminate\Support\Facades\Log;

class NexoResetBotVariables extends Command
{
    protected $signature = 'nexo:reset-bot-variables
                            {--dry-run : Simula sem fazer alterações}
                            {--limit=0 : Limitar quantidade (0 = sem limite)}';

    protected $description = 'Reseta atendimento_humano/sessao_ativa no SendPulse para conversas sem operador ativo';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit  = (int) $this->option('limit');
        $sp     = app(SendPulseWhatsAppService::class);

        $this->info('=== NEXO Reset Bot Variables ===');
        $this->info('Dry-run: ' . ($dryRun ? 'SIM' : 'NÃO'));

        // Conversas que NÃO estão em atendimento humano ativo mas podem ter variável presa
        $query = WaConversation::whereNotNull('contact_id')
            ->where(function ($q) {
                // Fechadas (variável nunca foi resetada no close manual)
                $q->where('status', 'closed')
                  // OU abertas sem operador (bot deveria estar no controle)
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'open')
                         ->where('bot_ativo', true)
                         ->whereNull('assigned_user_id');
                  });
            });

        if ($limit > 0) {
            $query->limit($limit);
        }

        $conversations = $query->get(['id', 'contact_id', 'phone', 'name', 'status']);
        $this->info("Conversas a processar: {$conversations->count()}");

        $reset   = 0;
        $errors  = 0;
        $skipped = 0;

        foreach ($conversations as $conv) {
            if ($dryRun) {
                $this->line("  [DRY-RUN] Resetaria: {$conv->name} ({$conv->phone}) - status: {$conv->status}");
                $reset++;
                continue;
            }

            try {
                $sp->setContactVariable($conv->contact_id, 'atendimento_humano', 'nao');
                $sp->setContactVariable($conv->contact_id, 'sessao_ativa', 'nao');
                $sp->closeChat($conv->contact_id);

                $reset++;

                if ($reset % 50 === 0) {
                    $this->line("  ... {$reset} processadas");
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->warn("  ❌ Erro em {$conv->name} ({$conv->contact_id}): {$e->getMessage()}");
            }

            // Rate limiting: 200ms entre chamadas
            usleep(200000);
        }

        $this->newLine();
        $this->info("=== RESULTADO ===");
        $this->info("Resetadas: {$reset}");
        if ($errors > 0) {
            $this->error("Erros: {$errors}");
        }

        Log::info('nexo:reset-bot-variables — concluído', [
            'reset'   => $reset,
            'errors'  => $errors,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
