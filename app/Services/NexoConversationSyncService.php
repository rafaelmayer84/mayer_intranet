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

/**
 * Serviço de sincronização de conversas WhatsApp via SendPulse.
 *
 * HOTFIX 15/04/2026 — syncConversationFromWebhook()
 * BUG: Ao reabrir conversa fechada, não chamava reativarAutomacao(),
 * deixando atendimento_humano="sim" no SendPulse. Fluxo "Resposta padrão"
 * enviava "Aguarde a resposta do advogado" ao invés do menu de boas-vindas.
 * Corrigido: agora chama reativarAutomacao() ao reabrir conversa fechada.
 */
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
        \Log::info('NEXO-AUDIT: parsed webhook', ['phone' => $parsed['phone'] ?? 'VAZIO', 'contact_id' => $parsed['contact_id'] ?? 'VAZIO', 'text' => substr($parsed['text'] ?? '', 0, 50), 'msg_id' => $parsed['message_id'] ?? 'VAZIO']);
        if (!$parsed) return false;

        try {
            DB::beginTransaction();

            $phone = WaConversation::normalizePhone($parsed['phone']);

            $conversation = WaConversation::where('contact_id', $parsed['contact_id'])->first();
            if (!$conversation && $phone) {
                $conversation = WaConversation::where('phone', $phone)->first();

                // Fix v2.5: busca por variantes do telefone (nono digito)
                // Ex: webhook manda 554799156367, banco tem 5547999156367
                if (!$conversation && strlen($phone) >= 12) {
                    $ddd = substr($phone, 2, 2); // ex: 47
                    $numero = substr($phone, 4);  // ex: 99156367

                    if (strlen($numero) === 8) {
                        // Falta o 9: tentar com 9 prefixado
                        $variant = '55' . $ddd . '9' . $numero;
                        $conversation = WaConversation::where('phone', $variant)->first();
                    } elseif (strlen($numero) === 9 && $numero[0] === '9') {
                        // Tem o 9: tentar sem ele
                        $variant = '55' . $ddd . substr($numero, 1);
                        $conversation = WaConversation::where('phone', $variant)->first();
                    }

                    if ($conversation) {
                        \Log::info('NEXO-AUDIT: phone lookup por variante', [
                            'original' => $phone, 'variante' => $variant, 'conv_id' => $conversation->id
                        ]);
                    }
                }
            }

            $now = now();
            $sentAt = \Carbon\Carbon::createFromTimestamp($parsed['timestamp'], config('app.timezone'));

            if (!$conversation) {
                $conversation = WaConversation::create([
                    'provider'         => 'sendpulse',
                    'contact_id'       => $parsed['contact_id'],
                    'phone'            => $phone,
                    'name'             => $parsed['contact_name'] ?: null,
                    'status'           => 'open',
                    'bot_ativo'        => true,
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

                // Conversa que estava fechada reabre — resetar estado do bot
                $eraFechada = $conversation->status === 'closed';
                if ($eraFechada) {
                    $updateData['status']           = 'open';
                    $updateData['bot_ativo']        = true;
                    $updateData['assigned_user_id'] = null;
                    $updateData['lembrete_inatividade_at'] = null;

                    // Resetar variáveis no SendPulse para que o fluxo de boas-vindas funcione
                    $contactIdReset = $parsed['contact_id'] ?: $conversation->contact_id;
                    if ($contactIdReset) {
                        try {
                            $this->sendpulse->reativarAutomacao($contactIdReset);
                            \Log::info('NEXO-AUDIT: bot reativado ao reabrir conversa fechada', [
                                'conv_id' => $conversation->id, 'contact_id' => $contactIdReset,
                            ]);
                        } catch (\Throwable $e) {
                            \Log::warning('NEXO-AUDIT: falha ao reativar bot na reabertura', [
                                'conv_id' => $conversation->id, 'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                // SEMPRE atualizar contact_id se webhook trouxer diferente (SendPulse recria contacts)
                if (!empty($parsed['contact_id']) && $conversation->contact_id !== $parsed['contact_id']) {
                    $updateData['contact_id'] = $parsed['contact_id'];
                    \Log::info('NEXO-AUDIT: contact_id atualizado', ['conv_id' => $conversation->id, 'old' => $conversation->contact_id, 'new' => $parsed['contact_id']]);
                }
                if (empty($conversation->name) && !empty($parsed['contact_name'])) $updateData['name'] = $parsed['contact_name'];
                if (empty($conversation->phone) && !empty($phone)) $updateData['phone'] = $phone;
                $conversation->update($updateData);
                $conversation->refresh();

                // Auto-link se conversa reabriu sem vínculo
                if (!$conversation->linked_cliente_id && !$conversation->linked_lead_id) {
                    $this->autoLink($conversation);
                }
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

            // ── SAFEGUARD: Gerenciar estado do bot com base no tempo de inatividade ──
            // Regra:
            //   • bot_ativo = false + assigned_user_id + último incoming < 6h → re-pausar (humano ainda atendendo)
            //   • bot_ativo = false + último incoming ≥ 6h → resetar para fluxo inicial (inatividade encerrou sessão)
            $minutosDesdeUltimoIncoming = $conversation->last_incoming_at
                ? (int) $conversation->last_incoming_at->diffInMinutes(now())
                : 9999;

            if ($conversation->bot_ativo === false && $conversation->assigned_user_id) {
                $contactIdAction = $parsed['contact_id'] ?: $conversation->contact_id;

                if ($minutosDesdeUltimoIncoming >= 360) {
                    // ≥ 6h sem mensagem: resetar conversa para o fluxo inicial
                    $conversation->update([
                        'bot_ativo'               => true,
                        'assigned_user_id'        => null,
                        'lembrete_inatividade_at' => null,
                    ]);
                    if ($contactIdAction) {
                        try {
                            $this->sendpulse->reativarAutomacao($contactIdAction);
                            \Log::info('NEXO-AUDIT: bot reativado por inatividade ≥6h (fluxo inicial)', [
                                'conv_id'    => $conversation->id,
                                'contact_id' => $contactIdAction,
                                'minutos'    => $minutosDesdeUltimoIncoming,
                            ]);
                        } catch (\Throwable $e) {
                            \Log::warning('NEXO-AUDIT: falha ao reativar bot após 6h', [
                                'conv_id' => $conversation->id,
                                'error'   => $e->getMessage(),
                            ]);
                        }
                    }
                } else {
                    // < 6h: humano ainda pode estar atendendo — re-pausar
                    if ($contactIdAction) {
                        try {
                            $this->sendpulse->pausarAutomacao($contactIdAction);
                            \Log::info('NEXO-AUDIT: bot re-pausado via webhook safeguard', [
                                'conv_id'    => $conversation->id,
                                'contact_id' => $contactIdAction,
                                'minutos'    => $minutosDesdeUltimoIncoming,
                            ]);
                        } catch (\Throwable $e) {
                            \Log::warning('NEXO-AUDIT: falha ao re-pausar bot no webhook', [
                                'conv_id' => $conversation->id,
                                'error'   => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            // ── DOR 3: Auto-sync WhatsApp → CRM ──
            if (!$msgExists && $conversation->phone) {
                try {
                    $this->syncWhatsappToCrm($conversation, $parsed['text'] ?? '', $sentAt);
                } catch (\Throwable $crmEx) {
                    \Log::warning('CRM auto-sync falhou', ['conv' => $conversation->id, 'error' => $crmEx->getMessage()]);
                }
            }

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
        if ($conversation->status === 'closed') {
            return 0;
        }

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
        $contactId = $conversation->contact_id;
        if (empty($contactId)) {
            Log::warning("NexoSync: conversa {$conversation->id} sem contact_id, polling ignorado");
            return 0;
        }

        $since = null;
        $lastMsg = $conversation->messages()->orderBy('sent_at', 'desc')->first();
        if ($lastMsg && $lastMsg->sent_at) {
            $since = $lastMsg->sent_at->toISOString();
        }

        $messages = $this->sendpulse->getChatMessages($contactId, $limit, $since);

        if ($messages === null) {
            $newFailCount = $failCount + 1;
            // Se atingiu limite maximo, desativar sync permanentemente
            if ($newFailCount >= self::POLL_MAX_FAILS) {
                $conversation->update(['status' => 'closed']);
                Cache::forget($failKey);
                Log::warning("NexoSync: conversa {$conversation->id} fechada permanentemente apos {$newFailCount} falhas consecutivas (contact_id provavelmente inexistente no SendPulse)");
                return 0;
            }
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
            // Fix 26/02: extrair wamid para evitar duplicata webhook vs sync
            $wamid = data_get($msg, 'data.id') ?? data_get($msg, 'data.message_id');

            if ($providerMsgId) {
                $existingMsg = WaMessage::where('provider_message_id', $providerMsgId)->first();
                // Se nao achou pelo ID SendPulse, tentar pelo wamid (webhook salva com wamid)
                if (!$existingMsg && $wamid) {
                    $existingMsg = WaMessage::where('provider_message_id', $wamid)->first();
                }
                if ($existingMsg) {
                    // Fix 18/02: atualizar media_url se estava NULL (webhook salvou sem URL)
                    if (empty($existingMsg->media_url)) {
                        $media = SendPulseWhatsAppService::extractMedia($msg);
                        if (!empty($media['url'])) {
                            $updateFields = ['media_url' => $media['url']];
                            if (empty($existingMsg->media_mime_type) && !empty($media['mime_type'])) $updateFields['media_mime_type'] = $media['mime_type'];
                            if (empty($existingMsg->media_filename) && !empty($media['filename'])) $updateFields['media_filename'] = $media['filename'];
                            if (empty($existingMsg->media_caption) && !empty($media['caption'])) $updateFields['media_caption'] = $media['caption'];
                            // Se body era a URL de mídia, limpar
                            if (!empty($existingMsg->body) && str_contains($existingMsg->body, 'login.sendpulse.com/api/chatbots-service/whatsapp/messages/media')) {
                                $updateFields['body'] = $media['caption'] ?? '';
                            }
                            $existingMsg->update($updateFields);
                        }
                    }
                    continue;
                }
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

            // v2.6: dedup msg humana local vs sync
            // enviarMensagem grava com pmid=NULL, sync traz com pmid e prefixo de nome
            if ($direction === 2 && $providerMsgId) {
                $localDupe = WaMessage::where('conversation_id', $conversation->id)
                    ->where('direction', 2)
                    ->where('is_human', true)
                    ->whereNull('provider_message_id')
                    ->whereBetween('sent_at', [
                        $sentAt->copy()->subSeconds(5),
                        $sentAt->copy()->addSeconds(5),
                    ])
                    ->first();
                if ($localDupe) {
                    // Atualizar o registro local com o provider_message_id do sync
                    $localDupe->update(['provider_message_id' => $providerMsgId]);
                    \Log::info('NEXO-SYNC: dedup humana local', [
                        'conv_id' => $conversation->id, 'msg_id' => $localDupe->id, 'pmid' => $providerMsgId
                    ]);
                    $newCount++;
                    continue;
                }
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
                'created_at'          => $sentAt,
                'updated_at'          => $sentAt,
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
            'user_id'             => $userId,
            'message_type'        => 'text',
            'body'                => $text,
            'sent_at'             => now(),
        ]);

        $updateData = ['last_message_at' => now()];
        if (!$conversation->first_response_at) $updateData['first_response_at'] = now();
        $updateData['assigned_user_id'] = $userId;
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
        $phone = $conversation->phone; // formato: 55XXXXXXXXXXX (só dígitos)

        // ── CLIENTE: buscar por telefone_normalizado, depois celular normalizado ──
        if (!$conversation->linked_cliente_id) {
            $cliente = Cliente::where('telefone_normalizado', $phone)->first();

            if (!$cliente) {
                // Fallback: normalizar celular e comparar
                $cliente = Cliente::whereNotNull('celular')
                    ->where('celular', '!=', '')
                    ->where('celular', 'NOT LIKE', '%(00)%')
                    ->get()
                    ->first(function ($c) use ($phone) {
                        $norm = preg_replace('/\D/', '', $c->celular);
                        if (!str_starts_with($norm, '55') && strlen($norm) >= 10 && strlen($norm) <= 11) {
                            $norm = '55' . $norm;
                        }
                        return $norm === $phone;
                    });
            }

            if ($cliente) {
                $conversation->update(['linked_cliente_id' => $cliente->id]);
            }
        }

        // ── LEAD: só vincular se NÃO há cliente e o lead está ativo ──
        if (!$conversation->linked_lead_id && !$conversation->linked_cliente_id) {
            $lead = Lead::ativo()
                ->whereNotNull('telefone')
                ->where('telefone', '!=', '')
                ->get()
                ->first(function ($l) use ($phone) {
                    $norm = preg_replace('/\D/', '', $l->telefone);
                    if (!str_starts_with($norm, '55') && strlen($norm) >= 10 && strlen($norm) <= 11) {
                        $norm = '55' . $norm;
                    }
                    return $norm === $phone;
                });

            if ($lead) {
                $conversation->update(['linked_lead_id' => $lead->id]);
            }
        }

        // ── CONSISTÊNCIA: se há cliente vinculado, arquivar lead com mesmo telefone ──
        if ($conversation->linked_cliente_id) {
            $this->arquivarLeadSeDuplicaCliente($phone, $conversation->linked_cliente_id);
        }
    }

    /**
     * Quando uma conversa é vinculada a um cliente, arquiva qualquer lead ativo
     * com o mesmo telefone, evitando duplicidade lead+cliente para a mesma pessoa.
     */
    private function arquivarLeadSeDuplicaCliente(string $phone, int $clienteId): void
    {
        Lead::ativo()
            ->whereNotNull('telefone')
            ->where('telefone', '!=', '')
            ->get()
            ->each(function ($lead) use ($phone, $clienteId) {
                $norm = preg_replace('/\D/', '', $lead->telefone);
                if (!str_starts_with($norm, '55') && strlen($norm) >= 10 && strlen($norm) <= 11) {
                    $norm = '55' . $norm;
                }
                if ($norm === $phone) {
                    $lead->update([
                        'status'     => 'convertido',
                        'cliente_id' => $clienteId,
                    ]);
                }
            });
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
        try { return \Carbon\Carbon::parse($raw, 'UTC')->setTimezone(config('app.timezone')); }
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

    /**
     * DOR 3: Sincroniza mensagem incoming WhatsApp com CRM
     * - Atualiza last_touch_at do account
     * - Cria crm_activity automática
     * - Se cadência tem passo pendente "aguardar retorno", marca concluído
     */
    protected function syncWhatsappToCrm($conversation, string $messageText, $sentAt): void
    {
        if (empty($conversation->phone)) return;

        // Normalizar telefone para busca
        $phoneDigits = preg_replace('/\D/', '', $conversation->phone);

        // Buscar account pelo telefone
        $account = \App\Models\Crm\CrmAccount::where('phone_e164', 'LIKE', '%' . substr($phoneDigits, -8) . '%')->first();

        if (!$account) return;

        // Atualizar last_touch_at
        $account->update(['last_touch_at' => $sentAt ?? now()]);

        // Criar atividade automática (max 1 por conversa por dia para não poluir)
        $jaRegistrouHoje = \App\Models\Crm\CrmActivity::where('account_id', $account->id)
            ->where('type', 'whatsapp_incoming')
            ->whereDate('created_at', today())
            ->exists();

        if (!$jaRegistrouHoje) {
            \App\Models\Crm\CrmActivity::create([
                'account_id'        => $account->id,
                'type'              => 'whatsapp_incoming',
                'purpose'           => 'acompanhamento',
                'title'             => 'Mensagem recebida via WhatsApp',
                'body'              => mb_substr($messageText, 0, 200),
                'done_at'           => $sentAt ?? now(),
                'created_by_user_id' => null, // automático
            ]);
        }

        // Verificar cadência: se tem oportunidade open com passo pendente
        $oportunidades = \App\Models\Crm\CrmOpportunity::where('account_id', $account->id)
            ->where('status', 'open')
            ->pluck('id');

        if ($oportunidades->isNotEmpty()) {
            // Buscar passos de cadência pendentes do tipo "aguardar"
            $passosPendentes = \App\Models\Crm\CrmCadenceTask::whereIn('opportunity_id', $oportunidades)
                ->whereNull('completed_at')
                ->where(function ($q) {
                    $q->where('title', 'LIKE', '%aguardar%')
                      ->orWhere('title', 'LIKE', '%retorno%')
                      ->orWhere('description', 'LIKE', '%aguardar retorno%');
                })
                ->get();

            foreach ($passosPendentes as $passo) {
                $passo->update([
                    'completed_at'      => now(),
                    'resolution_status' => 'cliente_respondeu',
                    'resolution_notes'  => 'Auto: cliente respondeu via WhatsApp em ' . now('America/Sao_Paulo')->format('d/m/Y H:i'),
                ]);
            }
        }
    }

}
