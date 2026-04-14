<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SendPulseWhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NexoCloseAbandonedChats extends Command
{
    protected $signature = 'nexo:close-abandoned-chats
                            {--reminder-hours=6  : Horas de inatividade para enviar lembrete}
                            {--close-hours=23    : Horas de inatividade para encerrar a conversa}
                            {--dry-run           : Simula sem enviar mensagens nem fechar chats}';

    protected $description = 'Gerencia chats inativos: lembrete após X horas, encerramento após Y horas';

    private SendPulseWhatsAppService $sp;

    public function __construct(SendPulseWhatsAppService $sp)
    {
        parent::__construct();
        $this->sp = $sp;
    }

    public function handle(): int
    {
        $reminderHours = (int) $this->option('reminder-hours');
        $closeHours    = (int) $this->option('close-hours');
        $dryRun        = $this->option('dry-run');
        $now           = Carbon::now('UTC');
        $reminderCutoff = $now->copy()->subHours($reminderHours);
        $closeCutoff    = $now->copy()->subHours($closeHours);

        $this->info("=== NEXO Close Abandoned Chats ===");
        $this->info("Lembrete: >{$reminderHours}h | Encerramento: >{$closeHours}h | Dry-run: " . ($dryRun ? 'SIM' : 'NÃO'));

        $reminders = 0;
        $closed    = 0;
        $errors    = 0;
        $processed = [];

        $response = $this->sp->getOpenChats();

        if (!$response || !isset($response['data'])) {
            $this->error("Falha ao buscar chats");
            Log::error('nexo:close-abandoned-chats — falha getOpenChats');
            return self::FAILURE;
        }

        $chats = $response['data'];
        $this->line("Chats recebidos: " . count($chats));

        foreach ($chats as $chat) {
            $contact      = $chat['contact'] ?? [];
            $contactId    = $contact['id'] ?? null;
            $contactName  = $contact['channel_data']['name'] ?? 'Desconhecido';
            $isOpen       = $contact['is_chat_opened'] ?? false;
            $lastActivity = $contact['last_activity_at'] ?? null;
            $phone        = (string) ($contact['channel_data']['phone'] ?? '');

            // Dedup
            if (!$contactId || isset($processed[$contactId])) {
                continue;
            }
            $processed[$contactId] = true;

            // Só processa chats com operador (live chat aberto)
            if (!$isOpen || !$lastActivity || !$phone) {
                continue;
            }

            $lastActivityAt = Carbon::parse($lastActivity);

            // Nada a fazer se ainda está dentro do threshold de lembrete
            if ($lastActivityAt->greaterThan($reminderCutoff)) {
                continue;
            }

            // ── ESTÁGIO 2: encerrar após closeHours ──────────────────────────
            if ($lastActivityAt->lessThanOrEqualTo($closeCutoff)) {
                $inactiveHours = round($lastActivityAt->diffInMinutes($now) / 60, 1);

                if ($dryRun) {
                    $this->warn("[DRY-RUN] Encerraria: {$contactName} (inativo {$inactiveHours}h)");
                    $closed++;
                    continue;
                }

                // Mensagem de encerramento
                $msg = "Encerramos sua conversa por falta de resposta. 😊\n\n"
                     . "Quando precisar, é só mandar uma mensagem — estaremos aqui!";

                $this->sp->sendMessageByPhone($phone, $msg);
                sleep(1);

                $result = $this->sp->closeChat($contactId);

                // Limpa o lembrete no banco (conversa encerrada)
                DB::table('wa_conversations')
                    ->where('contact_id', $contactId)
                    ->update(['lembrete_inatividade_at' => null]);

                if ($result['success'] ?? false) {
                    $closed++;
                    $this->line("  ✅ Encerrado: {$contactName} (inativo {$inactiveHours}h)");
                    Log::info('nexo:close-abandoned — encerrado', [
                        'contact_id'   => $contactId,
                        'name'         => $contactName,
                        'inactive_hrs' => $inactiveHours,
                    ]);
                } else {
                    $errors++;
                    $this->error("  ❌ Erro ao fechar {$contactName}: " . ($result['error'] ?? 'unknown'));
                    Log::error('nexo:close-abandoned — erro closeChat', [
                        'contact_id' => $contactId,
                        'error'      => $result['error'] ?? 'unknown',
                    ]);
                }

                usleep(200000);
                continue;
            }

            // ── ESTÁGIO 1: lembrete após reminderHours ───────────────────────
            // Verifica se já enviamos o lembrete neste ciclo de inatividade
            $conv = DB::table('wa_conversations')
                ->where('contact_id', $contactId)
                ->select('id', 'lembrete_inatividade_at')
                ->first();

            if ($conv && $conv->lembrete_inatividade_at) {
                // Lembrete já enviado neste ciclo — aguardar estágio 2
                continue;
            }

            $inactiveHours = round($lastActivityAt->diffInMinutes($now) / 60, 1);

            if ($dryRun) {
                $this->warn("[DRY-RUN] Enviaria lembrete: {$contactName} (inativo {$inactiveHours}h)");
                $reminders++;
                continue;
            }

            $msg = "Oi! 👋 Vimos que nossa conversa ficou em aberto.\n\n"
                 . "Ainda podemos te ajudar? Se quiser continuar, é só responder aqui. 😊";

            $sendResult = $this->sp->sendMessageByPhone($phone, $msg);

            if ($sendResult['success'] ?? false) {
                $reminders++;
                $this->line("  💬 Lembrete enviado: {$contactName} (inativo {$inactiveHours}h)");

                // Marca o lembrete no banco
                if ($conv) {
                    DB::table('wa_conversations')
                        ->where('id', $conv->id)
                        ->update(['lembrete_inatividade_at' => $now->toDateTimeString()]);
                }

                Log::info('nexo:close-abandoned — lembrete enviado', [
                    'contact_id'   => $contactId,
                    'name'         => $contactName,
                    'inactive_hrs' => $inactiveHours,
                ]);
            } else {
                $this->warn("  ⚠ Falha ao enviar lembrete para {$contactName}: " . ($sendResult['error'] ?? 'unknown'));
            }

            usleep(200000);
        }

        $this->newLine();
        $this->info("=== RESULTADO ===");
        $this->info("Chats analisados: " . count($processed));
        $this->info("Lembretes enviados: {$reminders}");
        $this->info("Conversas encerradas: {$closed}");
        if ($errors > 0) {
            $this->error("Erros: {$errors}");
        }

        Log::info('nexo:close-abandoned — execução concluída', [
            'unique_scanned'  => count($processed),
            'reminders'       => $reminders,
            'closed'          => $closed,
            'errors'          => $errors,
            'reminder_hours'  => $reminderHours,
            'close_hours'     => $closeHours,
            'dry_run'         => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
