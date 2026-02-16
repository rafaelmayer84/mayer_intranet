<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SendPulseWhatsAppService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NexoCloseAbandonedChats extends Command
{
    protected $signature = 'nexo:close-abandoned-chats
                            {--hours=6 : Horas de inatividade para considerar abandonado}
                            {--dry-run : Simula sem fechar chats}
                            {--notify : Envia mensagem ao cliente antes de fechar (se última msg foi dele)}';

    protected $description = 'Fecha chats SendPulse abandonados (sem atividade por X horas) e reativa o bot';

    private SendPulseWhatsAppService $sp;

    public function __construct(SendPulseWhatsAppService $sp)
    {
        parent::__construct();
        $this->sp = $sp;
    }

    public function handle(): int
    {
        $hoursThreshold = (int) $this->option('hours');
        $dryRun = $this->option('dry-run');
        $notify = $this->option('notify');
        $now = Carbon::now('UTC');
        $cutoff = $now->copy()->subHours($hoursThreshold);

        $this->info("=== NEXO Close Abandoned Chats ===");
        $this->info("Threshold: {$hoursThreshold}h | Cutoff: {$cutoff->toIso8601String()} | Dry-run: " . ($dryRun ? 'SIM' : 'NÃO'));

        $closed = 0;
        $notified = 0;
        $errors = 0;
        $processed = [];

        // API SendPulse /whatsapp/chats retorna max 100 (paginação não funciona)
        $response = $this->sp->getChatsPage(0);

        if (!$response || !isset($response['data'])) {
            $this->error("Falha ao buscar chats");
            Log::error('nexo:close-abandoned-chats — falha getChatsPage');
            return self::FAILURE;
        }

        $chats = $response['data'];
        $this->line("Chats recebidos: " . count($chats));

        foreach ($chats as $chat) {
            $contact = $chat['contact'] ?? [];
            $contactId = $contact['id'] ?? null;
            $contactName = $contact['channel_data']['name'] ?? 'Desconhecido';
            $isOpen = $contact['is_chat_opened'] ?? false;
            $lastActivity = $contact['last_activity_at'] ?? null;

            // Dedup safety (API pode retornar duplicatas)
            if (!$contactId || isset($processed[$contactId])) {
                continue;
            }
            $processed[$contactId] = true;

            // Só processa chats abertos (operador assumiu)
            if (!$isOpen || !$lastActivity) {
                continue;
            }

            $lastActivityAt = Carbon::parse($lastActivity);

            // Verifica se está inativo há mais de X horas
            if ($lastActivityAt->greaterThan($cutoff)) {
                continue;
            }

            $inactiveHours = round($lastActivityAt->diffInMinutes($now) / 60, 1);

            // Determinar quem enviou última mensagem
            $inboxMsg = $chat['inbox_last_message'] ?? [];
            $inboxDirection = $inboxMsg['direction'] ?? null;
            // direction 1 = incoming (cliente enviou), 2 = outgoing (bot/operador)
            $clienteSemResposta = ($inboxDirection === 1);

            if ($dryRun) {
                $this->warn("[DRY-RUN] Fecharia: {$contactName} (inativo {$inactiveHours}h)" .
                    ($clienteSemResposta ? ' [cliente sem resposta]' : ''));
                $closed++;
                continue;
            }

            // Enviar mensagem se cliente foi o último a escrever e --notify ativo
            if ($notify && $clienteSemResposta) {
                $phone = (string) ($contact['channel_data']['phone'] ?? '');
                if ($phone) {
                    $msg = "Olá! Seu atendimento foi encerrado por inatividade. "
                         . "Se precisar de algo, envie uma nova mensagem a qualquer momento. "
                         . "Estamos à disposição! 😊";
                    $sendResult = $this->sp->sendMessageByPhone($phone, $msg);
                    if ($sendResult['success'] ?? false) {
                        $notified++;
                        $this->line("  📩 Notificado: {$contactName} ({$phone})");
                    } else {
                        $this->warn("  ⚠ Falha ao notificar {$contactName}: " . ($sendResult['error'] ?? 'unknown'));
                    }
                    sleep(1);
                }
            }

            // Fechar o chat
            $result = $this->sp->closeChat($contactId);

            if ($result['success'] ?? false) {
                $closed++;
                $this->line("  ✅ Fechado: {$contactName} (inativo {$inactiveHours}h)");
                Log::info('nexo:close-abandoned — chat fechado', [
                    'contact_id'   => $contactId,
                    'name'         => $contactName,
                    'inactive_hrs' => $inactiveHours,
                    'notified'     => ($notify && $clienteSemResposta),
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
        }

        $this->newLine();
        $this->info("=== RESULTADO ===");
        $this->info("Chats únicos analisados: " . count($processed));
        $this->info("Chats fechados: {$closed}");
        if ($notify) {
            $this->info("Clientes notificados: {$notified}");
        }
        if ($errors > 0) {
            $this->error("Erros: {$errors}");
        }

        Log::info('nexo:close-abandoned — execução concluída', [
            'unique_scanned' => count($processed),
            'closed'  => $closed,
            'notified' => $notified,
            'errors'  => $errors,
            'threshold_hours' => $hoursThreshold,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}
