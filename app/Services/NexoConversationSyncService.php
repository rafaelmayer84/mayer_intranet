<?php

namespace App\Services;

use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\WaEvent;
use App\Models\Lead;
use App\Models\Cliente;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class NexoConversationSyncService
{
    private SendPulseWhatsAppService $sendpulse;

    private const POLL_LOCK_TTL     = 10;
    private const POLL_MAX_FAILS    = 5;
    private const POLL_FAIL_BACKOFF = 30;

    public function __construct(SendPulseWhatsAppService $sendpulse)
    {
        $this->sendpulse = $sendpulse;
    }

    // ═══════════════════════════════════════════════════════
    // VALIDAÇÃO DE WEBHOOK
    // ═══════════════════════════════════════════════════════

    public static function validateWebhookSecret(?string $headerValue): bool
    {
        $expectedSecret = env('SENDPULSE_WEBHOOK_SECRET', '');
        if (empty($expectedSecret)) return true;
        if (empty($headerValue)) return false;
        return hash_equals($expectedSecret, $headerValue);
    }

    // ═══════════════════════════════════════════════════════
    // PROCESSAMENTO DE WEBHOOK (COM MÍDIA)
    // ═══════════════════════════════════════════════════════

    public function syncConversationFromWebhook(array $rawPayload): bool
    {
        $parsed = SendPulseWhatsAppService::parseWebhookIncomingMessage($rawPayload);
        if (!$parsed) return false;

        try {
            DB::beginTransaction();

            $phone = WaConversation::normalizePhone($parsed['phone']);

            $conversation = WaConversation::where('contact_id', $parsed['contact_id'])->first();
            if (!$conversation && $phone) {
                $conversation = WaConversation::where('phone', $phone)->first();
            }

            $now = now();
            $sentAt = \Carbon\Carbon::createFromTimestamp($parsed['timestamp'], 'UTC')->setTimezone(config('app.timezone'));

            if (!$conversation) {
                $conversation = WaConversation::create([
                    'provider'         => 'sendpulse',
                    'contact_id'       => $parsed['contact_id'],
                    'phone'            => $phone,
                    'name'             => $parsed['contact_name'] ?: null,
                    'status'           => 'open',
                    'last_message_at'  => $sentAt,
                    'last_incoming_at' => $sentAt,
                    'unread_count'     => 1,
                ]);
                $this->autoLink($conversation);
            } else {
                $updateData = [
                    'last_message_at'  => $sentAt,
                    'last_incoming_at' => $sentAt,
                    'unread_count'     => $conversation->unread_count + 1,
                ];
                if ($conversation->status === 'closed') $updateData['status'] = 'open';
                if (empty($conversation->contact_id) && !empty($parsed['contact_id'])) $updateData['contact_id'] = $parsed['contact_id'];
                if (empty($conversation->name) && !empty($parsed['contact_name'])) $updateData['name'] = $parsed['contact_name'];
                if (empty($conversation->phone) && !empty($phone)) $updateData['phone'] = $phone;
                $conversation->update($updateData);
                $conversation->refresh();
            }

            // Verificar duplicata
            $msgExists = false;
            if (!empty($parsed['message_id'])) {
                $msgExists = WaMessage::where('provider_message_id', $parsed['message_id'])->exists();
            }

            if (!$msgExists) {
                WaMessage::create([
                    'conversation_id'     => $conversation->id,
                    'provider_message_id' => $parsed['message_id'] ?: null,
                    'direction'           => WaMessage::DIRECTION_INCOMING,
                    'is_human'            => false,
                    'message_type'        => $parsed['message_type'],
                    'body'                => $parsed['text'],
                    'media_url'           => $parsed['media_url'] ?? null,
                    'media_mime_type'     => $parsed['media_mime'] ?? null,
                    'media_filename'      => $parsed['media_filename'] ?? null,
                    'media_caption'       => $parsed['media_caption'] ?? null,
                    'raw_payload'         => $this->safePayloadForStorage($rawPayload),
                    'sent_at'             => $sentAt,
                ]);
            }

            WaEvent::log(WaEvent::TYPE_WEBHOOK_RECEIVED, $conversation->id, [
                'contact_id' => $parsed['contact_id'],
                'msg_type'   => $parsed['message_type'],
            ]);

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('NexoSync: falha ao processar webhook', [
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
                'file'  => basename($e->getFile()),
            ]);
            WaEvent::log(WaEvent::TYPE_ERROR, null, [
                'error'   => $e->getMessage(),
                'context' => 'syncConversationFromWebhook',
            ]);
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════
    // ALIAS: syncConversation → syncMessages (chamado pelo Controller)
    // ═══════════════════════════════════════════════════════

    public function syncConversation(WaConversation $conversation, int $limit = 50): int
    {
        return $this->syncMessages($conversation, $limit);
    }

    // ═══════════════════════════════════════════════════════
    // SINCRONIZAÇÃO DE MENSAGENS (POLLING) — COM LOCK E BACKOFF
    // ═══════════════════════════════════════════════════════

    public function syncMessages(WaConversation $conversation, int $limit = 50): int
    {
        if (empty($conversation->chat_id) && empty($conversation->contact_id)) {
            Log::warning('NexoSync: sem chat_id nem contact_id para sync', ['conversation_id' => $conversation->id]);
            return 0;
        }

        $failKey = "nexo_poll_fail_{$conversation->id}";
        $failCount = (int) Cache::get($failKey, 0);

        if ($failCount >= self::POLL_MAX_FAILS) {
            Log::info("NexoSync: polling em backoff para conversa {$conversation->id} (falhas: {$failCount})");
            return -1;
        }

        $lockKey = "nexo_poll_lock_{$conversation->id}";
        $lock = Cache::lock($lockKey, self::POLL_LOCK_TTL);

        if (!$lock->get()) return -1;

        try {
            return $this->doSyncMessages($conversation, $limit, $failKey, $failCount);
        } finally {
            $lock->release();
        }
    }

    private function doSyncMessages(WaConversation $conversation, int $limit, string $failKey, int $failCount): int
    {
        $chatId = $conversation->chat_id;
        if (empty($chatId) && !empty($conversation->contact_id)) {
            $chatId = $conversation->contact_id;
        }

        $since = null;
        $lastMsg = $conversation->messages()->orderBy('sent_at', 'desc')->first();
        if ($lastMsg && $lastMsg->sent_at) {
            $since = $lastMsg->sent_at->toISOString();
        }

        $messages = $this->sendpulse->getChatMessages($chatId, $limit, $since);

        if ($messages === null) {
            $newFailCount = $failCount + 1;
            $backoffTtl = $newFailCount * self::POLL_FAIL_BACKOFF;
            Cache::put($failKey, $newFailCount, $backoffTtl);
            Log::warning("NexoSync: API falhou para conversa {$conversation->id}, backoff {$backoffTtl}s (falha #{$newFailCount})");
            return 0;
        }

        if ($failCount > 0) Cache::forget($failKey);
        if (!is_array($messages)) return 0;

        if (isset($messages['data']) && is_array($messages['data'])) {
            $messages = $messages['data'];
        }

        $newCount = 0;

        foreach ($messages as $msg) {
            if (!is_array($msg)) continue;

            $providerMsgId = data_get($msg, 'id') ?? data_get($msg, 'message_id');
            if ($providerMsgId && WaMessage::where('provider_message_id', $providerMsgId)->exists()) {
                continue;
            }

            $direction   = SendPulseWhatsAppService::extractDirection($msg);
            $text        = SendPulseWhatsAppService::extractText($msg);
            $messageType = SendPulseWhatsAppService::extractMessageType($msg);
            $media       = SendPulseWhatsAppService::extractMedia($msg);

            $sentAtRaw = data_get($msg, 'date') ?? data_get($msg, 'created_at') ?? data_get($msg, 'timestamp');
            $sentAt = $sentAtRaw ? $this->parseTimestamp($sentAtRaw) : now();

            // Se é mídia e tem caption mas não text, usar caption como body
            $body = $text;
            if (empty($body) && !empty($media['caption'])) {
                $body = $media['caption'];
            }

            WaMessage::create([
                'conversation_id'     => $conversation->id,
                'provider_message_id' => $providerMsgId,
                'direction'           => $direction,
                'is_human'            => false,
                'message_type'        => $messageType,
                'body'                => $body,
                'media_url'           => $media['url'],
                'media_mime_type'     => $media['mime_type'],
                'media_filename'      => $media['filename'],
                'media_caption'       => $media['caption'],
                'raw_payload'         => $this->safePayloadForStorage($msg),
                'sent_at'             => $sentAt,
            ]);

            $newCount++;
        }

        if ($newCount > 0) {
            $lastIncoming = $conversation->messages()->incoming()->orderBy('sent_at', 'desc')->first();
            $updateData = [
                'last_message_at' => $conversation->messages()->orderBy('sent_at', 'desc')->value('sent_at'),
            ];
            if ($lastIncoming) $updateData['last_incoming_at'] = $lastIncoming->sent_at;
            $conversation->update($updateData);

            WaEvent::log(WaEvent::TYPE_SYNC_RUN, $conversation->id, [
                'new_messages'  => $newCount,
                'total_fetched' => count($messages),
            ]);
        }

        return $newCount;
    }

    // ═══════════════════════════════════════════════════════
    // ENVIO DE MENSAGEM (HUMANO)
    // ═══════════════════════════════════════════════════════

    public function sendHumanMessage(WaConversation $conversation, string $text, int $userId): array
    {
        $text = trim($text);
        if (empty($text)) return ['success' => false, 'message' => null, 'error' => 'Mensagem vazia'];

        if (!empty($conversation->contact_id)) {
            $result = $this->sendpulse->sendMessage($conversation->contact_id, $text);
        } elseif (!empty($conversation->phone)) {
            $result = $this->sendpulse->sendMessageByPhone($conversation->phone, $text);
        } else {
            return ['success' => false, 'message' => null, 'error' => 'Conversa sem contact_id nem telefone'];
        }

        if (!$result['success']) {
            WaEvent::log(WaEvent::TYPE_ERROR, $conversation->id, [
                'error' => $result['error'], 'context' => 'sendHumanMessage', 'user_id' => $userId,
            ]);
            return ['success' => false, 'message' => null, 'error' => $result['error']];
        }

        $waMessage = WaMessage::create([
            'conversation_id'     => $conversation->id,
            'provider_message_id' => data_get($result, 'data.id') ?? data_get($result, 'data.message_id'),
            'direction'           => WaMessage::DIRECTION_OUTGOING,
            'is_human'            => true,
            'message_type'        => 'text',
            'body'                => $text,
            'sent_at'             => now(),
        ]);

        $updateData = ['last_message_at' => now()];
        if (!$conversation->first_response_at) $updateData['first_response_at'] = now();
        if (!$conversation->assigned_user_id) $updateData['assigned_user_id'] = $userId;
        $updateData['unread_count'] = 0;
        $conversation->update($updateData);

        WaEvent::log(WaEvent::TYPE_SEND_MESSAGE, $conversation->id, [
            'user_id' => $userId, 'text_size' => strlen($text),
        ]);

        return ['success' => true, 'message' => $waMessage, 'error' => null];
    }

    // ═══════════════════════════════════════════════════════
    // VINCULAÇÃO AUTOMÁTICA
    // ═══════════════════════════════════════════════════════

    public function autoLink(WaConversation $conversation): void
    {
        if (empty($conversation->phone)) return;
        $phone = $conversation->phone;

        if (!$conversation->linked_lead_id) {
            $leads = Lead::where('telefone', $phone)->get();
            if ($leads->count() === 1) {
                $conversation->update(['linked_lead_id' => $leads->first()->id]);
            } elseif ($leads->count() > 1) {
                WaEvent::log('autolink_ambiguous', $conversation->id, [
                    'entity' => 'lead', 'phone' => $phone, 'candidates' => $leads->pluck('id')->toArray(),
                ]);
            }
        }

        if (!$conversation->linked_cliente_id) {
            $clientes = Cliente::where('telefone', $phone)->get();
            if ($clientes->count() === 1) {
                $conversation->update(['linked_cliente_id' => $clientes->first()->id]);
            } elseif ($clientes->count() > 1) {
                WaEvent::log('autolink_ambiguous', $conversation->id, [
                    'entity' => 'cliente', 'phone' => $phone, 'candidates' => $clientes->pluck('id')->toArray(),
                ]);
            }
        }
    }

    // ═══════════════════════════════════════════════════════
    // HELPERS INTERNOS
    // ═══════════════════════════════════════════════════════

    private function parseTimestamp($raw): \Carbon\Carbon
    {
        if ($raw instanceof \Carbon\Carbon) return $raw;
        if (is_numeric($raw) && strlen((string) $raw) >= 10) {
            return \Carbon\Carbon::createFromTimestamp((int) $raw, 'UTC')->setTimezone(config('app.timezone'));
        }
        try { return \Carbon\Carbon::parse($raw); }
        catch (\Throwable $e) { return now(); }
    }

    private function safePayloadForStorage($payload): ?array
    {
        if (!is_array($payload)) return null;

        $sensitiveKeys = ['token', 'access_token', 'api_key', 'secret', 'password', 'authorization'];
        array_walk_recursive($payload, function (&$value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower($key), $sensitiveKeys, true)) $value = '***';
        });

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json && strlen($json) > 51200) {
            return ['_truncated' => true, 'original_size' => strlen($json)];
        }

        return $payload;
    }
}
