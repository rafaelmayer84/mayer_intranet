<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendPulseWhatsAppService
{
    private string $baseUrl = 'https://api.sendpulse.com';
    private string $apiId;
    private string $apiSecret;
    private string $botId;

    public function __construct()
    {
        $this->apiId     = config('services.sendpulse.api_id', env('SENDPULSE_API_ID', ''));
        $this->apiSecret = config('services.sendpulse.api_secret', env('SENDPULSE_API_SECRET', ''));
        $this->botId     = config('services.sendpulse.bot_id', env('SENDPULSE_BOT_ID', ''));
    }

    public function getBotId(): string
    {
        return $this->botId;
    }

    // ═══════════════════════════════════════════════════════
    // AUTENTICAÇÃO
    // ═══════════════════════════════════════════════════════

    public function getToken(): ?string
    {
        return Cache::remember('sendpulse_wa_token', 3000, function () {
            try {
                $response = Http::timeout(15)
                    ->post("{$this->baseUrl}/oauth/access_token", [
                        'grant_type'    => 'client_credentials',
                        'client_id'     => $this->apiId,
                        'client_secret' => $this->apiSecret,
                    ]);

                if ($response->successful()) {
                    $token = $response->json('access_token');
                    if ($token) {
                        return $token;
                    }
                }

                Log::error('SendPulse WA: falha ao obter token', [
                    'status' => $response->status(),
                    'body'   => mb_substr($response->body(), 0, 500),
                ]);
                return null;
            } catch (\Throwable $e) {
                Log::error('SendPulse WA: exceção ao obter token', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    public function clearTokenCache(): void
    {
        Cache::forget('sendpulse_wa_token');
    }

    // ═══════════════════════════════════════════════════════
    // LEITURA DE CHATS E MENSAGENS
    // ═══════════════════════════════════════════════════════

    public function getChats(): ?array
    {
        return $this->apiGet("/whatsapp/chats", ['bot_id' => $this->botId]);
    }

    public function getChatMessages(string $contactId, int $limit = 100, ?string $since = null): ?array
    {
        // SendPulse API exige contact_id valido (hex 24 chars), nao chat_id
        if (str_starts_with($contactId, 'whatsapp_') || str_starts_with($contactId, 'backup_')) {
            \Log::warning("NexoSync: contact_id legado ignorado no polling", ['contact_id' => $contactId]);
            return null;
        }
        $params = ['bot_id' => $this->botId, 'contact_id' => $contactId, 'limit' => $limit];
        if ($since) $params['since'] = $since;
        return $this->apiGet("/whatsapp/chats/messages", $params);
    }

    public function getContactByPhone(string $phone): ?array
    {
        $result = $this->apiGet("/whatsapp/contacts/getByPhone", ['bot_id' => $this->botId, 'phone' => $phone]);
        if ($result && isset($result['id'])) return $result;
        if ($result && isset($result['data']['id'])) return $result['data'];
        return $result;
    }

    // ═══════════════════════════════════════════════════════
    // FLOWS
    // ═══════════════════════════════════════════════════════

    public function getFlows(): ?array
    {
        return $this->apiGet("/whatsapp/flows", ['bot_id' => $this->botId]);
    }

    public function runFlow(string $contactId, string $flowId, array $externalData = []): array
    {
        return $this->apiPost("/whatsapp/flows/run", [
            'contact_id'    => $contactId,
            'flow_id'       => $flowId,
            'external_data' => $externalData ?: (object) [],
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // ENVIO DE MENSAGENS
    // ═══════════════════════════════════════════════════════

    public function sendMessage(string $contactId, string $text): array
    {
        return $this->apiPost("/whatsapp/contacts/send", [
            'contact_id' => $contactId,
            'bot_id'     => $this->botId,
            'message'    => ['type' => 'text', 'text' => ['body' => $text]],
        ]);
    }

    public function sendMessageByPhone(string $phone, string $text): array
    {
        return $this->apiPost("/whatsapp/contacts/sendByPhone", [
            'bot_id' => $this->botId,
            'phone'  => $phone,
            'message' => ['type' => 'text', 'text' => ['body' => $text]],
        ]);
    }

    /**
     * Enviar mensagem com reply/quote (context.message_id) via contact_id.
     */
    public function sendMessageWithReply(string $contactId, string $text, string $replyToMessageId): array
    {
        return $this->apiPost("/whatsapp/contacts/send", [
            'contact_id' => $contactId,
            'bot_id'     => $this->botId,
            'message'    => [
                'type' => 'text',
                'text' => ['body' => $text],
                'context' => ['message_id' => $replyToMessageId],
            ],
        ]);
    }

    /**
     * Enviar mensagem com reply/quote via telefone.
     */
    public function sendMessageByPhoneWithReply(string $phone, string $text, string $replyToMessageId): array
    {
        return $this->apiPost("/whatsapp/contacts/sendByPhone", [
            'bot_id' => $this->botId,
            'phone'  => $phone,
            'message' => [
                'type' => 'text',
                'text' => ['body' => $text],
                'context' => ['message_id' => $replyToMessageId],
            ],
        ]);
    }

    /**
     * Enviar reação emoji a uma mensagem.
     */
    public function sendReaction(string $contactId, string $messageId, string $emoji): array
    {
        return $this->apiPost("/whatsapp/contacts/send", [
            'contact_id' => $contactId,
            'bot_id'     => $this->botId,
            'message'    => [
                'type'      => 'reaction',
                'reaction'  => ['emoji' => $emoji],
                'messageId' => $messageId,
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════
    // PARSING DE MENSAGENS (ESTÁTICO)
    // ═══════════════════════════════════════════════════════

    public static function extractText(array $msg): string
    {
        $text = data_get($msg, 'data.text.body');
        if (!empty($text) && is_string($text)) return $text;
        $dt = data_get($msg, 'data.text');
        if (!empty($dt) && is_string($dt)) return $dt;
        $db = data_get($msg, 'data.body');
        if (!empty($db) && is_string($db)) return $db;
        $tb = data_get($msg, 'text.body');
        if (!empty($tb) && is_string($tb)) return $tb;
        $b = data_get($msg, 'body');
        if (!empty($b) && is_string($b)) return $b;
        $m2 = data_get($msg, 'message');
        if (!empty($m2) && is_string($m2)) return $m2;
        $btnTitle = data_get($msg, 'info.message.channel_data.message.interactive.button_reply.title')
                 ?? data_get($msg, 'data.interactive.button_reply.title');
        if (!empty($btnTitle) && is_string($btnTitle)) return $btnTitle;
        $listTitle = data_get($msg, 'info.message.channel_data.message.interactive.list_reply.title')
                  ?? data_get($msg, 'data.interactive.list_reply.title');
        if (!empty($listTitle) && is_string($listTitle)) return $listTitle;
        $cdText = data_get($msg, 'info.message.channel_data.message.text.body');
        if (!empty($cdText) && is_string($cdText)) return $cdText;
        return '';
    }

    public static function extractMessageType(array $msg): string
    {
        $type = data_get($msg, 'data.type') ?? data_get($msg, 'type') ?? 'text';
        return is_string($type) ? $type : 'text';
    }

    public static function extractDirection(array $msg): int
    {
        $dir = data_get($msg, 'direction');
        if (is_int($dir) || is_string($dir)) {
            $intDir = (int) $dir;
            if (in_array($intDir, [1, 2], true)) {
                return $intDir;
            }
        }
        $title = data_get($msg, 'title') ?? data_get($msg, 'info.title');
        if (is_string($title)) {
            if (str_contains($title, 'outgoing')) return 2;
            if (str_contains($title, 'incoming')) return 1;
        }
        if (!empty(data_get($msg, 'info.message.channel_data.sent_by'))) {
            return 2;
        }
        return 1;
    }

    /**
     * Extrai dados de mídia (imagem, áudio, documento, vídeo) do payload SendPulse.
     *
     * @return array{url: string|null, mime_type: string|null, filename: string|null, caption: string|null}
     */
    public static function extractMedia(array $msg): array
    {
        $result = ['url' => null, 'mime_type' => null, 'filename' => null, 'caption' => null];

        $type = self::extractMessageType($msg);
        if ($type === 'text') return $result;

        $mediaTypes = ['image', 'document', 'audio', 'video', 'voice', 'sticker'];
        $mediaKey = in_array($type, $mediaTypes) ? $type : null;

        // Buscar URL em múltiplos caminhos
        $url = null;
        if ($mediaKey) {
            foreach (["data.{$mediaKey}.link", "data.{$mediaKey}.url"] as $path) {
                $val = data_get($msg, $path);
                if (!empty($val) && is_string($val) && filter_var($val, FILTER_VALIDATE_URL)) {
                    $url = $val;
                    break;
                }
            }
        }

        // Fallback: qualquer chave de mídia
        if (!$url) {
            foreach ($mediaTypes as $mt) {
                $val = data_get($msg, "data.{$mt}.link") ?? data_get($msg, "data.{$mt}.url");
                if (!empty($val) && is_string($val) && filter_var($val, FILTER_VALIDATE_URL)) {
                    $url = $val;
                    $mediaKey = $mt;
                    break;
                }
            }
        }

        $result['url'] = $url;

        if ($mediaKey) {
            $caption = data_get($msg, "data.{$mediaKey}.caption");
            if (!empty($caption) && is_string($caption)) $result['caption'] = $caption;

            $filename = data_get($msg, "data.{$mediaKey}.filename") ?? data_get($msg, "data.{$mediaKey}.file_name");
            if (!empty($filename) && is_string($filename)) $result['filename'] = $filename;

            $mime = data_get($msg, "data.{$mediaKey}.mime_type") ?? data_get($msg, "data.{$mediaKey}.mimeType");
            if (!empty($mime) && is_string($mime)) $result['mime_type'] = $mime;
        }

        // Inferir mime_type
        if (!$result['mime_type'] && $url) {
            $mimeMap = ['image' => 'image/jpeg', 'audio' => 'audio/ogg', 'voice' => 'audio/ogg', 'video' => 'video/mp4', 'document' => 'application/octet-stream', 'sticker' => 'image/webp'];
            $result['mime_type'] = $mimeMap[$mediaKey ?? $type] ?? null;
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════
    // PARSING DE WEBHOOK
    // ═══════════════════════════════════════════════════════

    public static function parseWebhookIncomingMessage(array $payload): ?array
    {
        $event = is_array($payload) && isset($payload[0]) ? $payload[0] : $payload;

        $title = data_get($event, 'title', '');
        if ($title !== 'incoming_message') return null;

        $contactId   = data_get($event, 'contact.id', '');
        $contactName = data_get($event, 'contact.name', '');
        $phone       = data_get($event, 'info.message.channel_data.message.from', '');
        $textBody    = data_get($event, 'info.message.channel_data.message.text.body', '');
        $msgType     = data_get($event, 'info.message.channel_data.message.type', 'text');
        $msgId       = data_get($event, 'info.message.channel_data.message.id', '');
        $timestamp   = data_get($event, 'date', time());
        $botId       = data_get($event, 'bot.id', '');

        if (empty($textBody)) {
            $textBody = data_get($event, 'contact.last_message', '');
        }

        // Extrair mídia do webhook (fix 18/02/2026: construir URL SendPulse)
        $mediaData = ['media_url' => null, 'media_mime' => null, 'media_filename' => null, 'media_caption' => null];
        $channelMsg = data_get($event, 'info.message.channel_data.message', []);
        if (is_array($channelMsg) && $msgType !== 'text') {
            foreach (['image', 'document', 'audio', 'video', 'voice', 'sticker'] as $mt) {
                $block = data_get($channelMsg, $mt);
                if (is_array($block)) {
                    // Tentar link/url direto primeiro
                    $mediaUrl = data_get($block, 'link') ?? data_get($block, 'url');
                    // Se não tem URL válida, construir a partir de message_id + media_id
                    if (empty($mediaUrl) || !filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
                        $mediaId = data_get($block, 'id');
                        if ($mediaId && $msgId) {
                            $mediaUrl = "https://login.sendpulse.com/api/chatbots-service/whatsapp/messages/media?message_id={$msgId}&id={$mediaId}";
                        }
                    }
                    $mediaData['media_url']      = $mediaUrl;
                    $mediaData['media_mime']     = data_get($block, 'mime_type');
                    $mediaData['media_filename'] = data_get($block, 'filename');
                    $mediaData['media_caption']  = data_get($block, 'caption');
                    break;
                }
            }
        }

        // Se body é uma URL de mídia SendPulse e temos media_url, limpar o body
        if (!empty($mediaData['media_url']) && !empty($textBody) && str_contains($textBody, 'login.sendpulse.com/api/chatbots-service/whatsapp/messages/media')) {
            $textBody = $mediaData['media_caption'] ?? '';
        }

        return array_merge([
            'contact_id'   => $contactId,
            'contact_name' => $contactName,
            'phone'        => $phone,
            'text'         => $textBody,
            'message_type' => $msgType,
            'message_id'   => $msgId,
            'timestamp'    => $timestamp,
            'bot_id'       => $botId,
        ], $mediaData);
    }

    // ═══════════════════════════════════════════════════════
    // MÉTODOS HTTP INTERNOS
    // ═══════════════════════════════════════════════════════

    private function apiGet(string $endpoint, array $params = []): ?array
    {
        $token = $this->getToken();
        if (!$token) { Log::error("SendPulse WA: sem token para GET {$endpoint}"); return null; }

        try {
            $response = Http::timeout(20)
                ->withHeaders(['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'])
                ->get("{$this->baseUrl}{$endpoint}", $params);

            if ($response->status() === 401) {
                $this->clearTokenCache();
                $token = $this->getToken();
                if ($token) {
                    $response = Http::timeout(20)
                        ->withHeaders(['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'])
                        ->get("{$this->baseUrl}{$endpoint}", $params);
                }
            }

            if ($response->successful()) return $response->json() ?? [];

            Log::warning("SendPulse WA: GET {$endpoint} falhou", ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);
            return null;
        } catch (\Throwable $e) {
            Log::error("SendPulse WA: exceção em GET {$endpoint}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function apiPost(string $endpoint, array $data): array
    {
        $token = $this->getToken();
        if (!$token) return ['success' => false, 'data' => null, 'error' => 'Sem token de autenticação'];

        try {
            $response = Http::timeout(20)
                ->withHeaders(['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}{$endpoint}", $data);

            if ($response->status() === 401) {
                $this->clearTokenCache();
                $token = $this->getToken();
                if ($token) {
                    $response = Http::timeout(20)
                        ->withHeaders(['Authorization' => "Bearer {$token}", 'Content-Type' => 'application/json'])
                        ->post("{$this->baseUrl}{$endpoint}", $data);
                }
            }

            if ($response->successful()) return ['success' => true, 'data' => $response->json(), 'error' => null];

            $errorMsg = "HTTP {$response->status()}: " . mb_substr($response->body(), 0, 300);
            Log::warning("SendPulse WA: POST {$endpoint} falhou", ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);
            return ['success' => false, 'data' => null, 'error' => $errorMsg];
        } catch (\Throwable $e) {
            Log::error("SendPulse WA: exceção em POST {$endpoint}", ['error' => $e->getMessage()]);
            return ['success' => false, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════
    // FECHAR CHAT (REATIVAR BOT)
    // ═══════════════════════════════════════════════════════

    /**
     * Fecha o chat (desativa live chat / reativa bot) para um contato.
     * POST /whatsapp/contacts/closeChat
     */
    public function closeChat(string $contactId): array
    {
        return $this->apiPost('/whatsapp/contacts/closeChat', [
            'bot_id'     => $this->botId,
            'contact_id' => $contactId,
        ]);
    }

    /**
     * Lista chats com paginação.
     * GET /whatsapp/chats?bot_id=...&offset=N
     */
    public function getChatsPage(int $offset = 0, int $limit = 100): ?array
    {
        return $this->apiGet('/whatsapp/chats', [
            'bot_id' => $this->botId,
            'offset' => $offset,
            'limit'  => $limit,
        ]);
    }

    /**
     * Lista apenas chats com live chat aberto (operador assumiu).
     */
    public function getOpenChats(): ?array
    {
        return $this->apiGet('/whatsapp/chats', [
            'bot_id'          => $this->botId,
            'is_chat_opened'  => 'true',
        ]);
    }

        // ═══════════════════════════════════════════════════════
    // CONTATO: INFO + TAGS
    // ═══════════════════════════════════════════════════════
    public function getContactInfo(string $contactId): ?array
    {
        return $this->apiGet("/whatsapp/contacts/get", [
            'bot_id' => $this->botId,
            'id'     => $contactId,
        ]);
    }

    public function setContactVariable(string $contactId, string $name, string $value): array
    {
        return $this->apiPost('/whatsapp/contacts/setVariable', [
            'contact_id' => $contactId,
            'variable_name' => $name,
            'variable_value' => $value,
        ]);
    }

    /**
     * Pausar automacao do contato (seta variavel + abre chat ao vivo)
     */
    public function pausarAutomacao(string $contactId): bool
    {
        $result = $this->setContactVariable($contactId, 'atendimento_humano', 'sim');
        if (!$result['success']) {
            Log::warning('SendPulse: falha ao setar atendimento_humano', ['contact_id' => $contactId, 'error' => $result['error']]);
        }
        return $result['success'] ?? false;
    }

    /**
     * Reativar automacao do contato (limpa variavel + fecha chat)
     */
    public function reativarAutomacao(string $contactId): bool
    {
        $this->setContactVariable($contactId, 'atendimento_humano', 'nao');
        $result = $this->closeChat($contactId);
        return $result['success'] ?? false;
    }

    public function setContactTags(string $contactId, array $tagNames): bool
    {
        $result = $this->apiPost("/chatbots/contacts/tags/set", [
            'contact_id' => $contactId,
            'tags'        => $tagNames,
            'channel'     => 'whatsapp',
        ]);
        return ($result['success'] ?? false) || isset($result['result']) || ($result === true);
    }

    public function getBotTags(): array
    {
        $result = $this->apiGet("/chatbots/bots/" . $this->botId . "/tags", ['channel' => 'whatsapp']);
        if (is_array($result) && isset($result[0]['id'])) return $result;
        if (is_array($result) && isset($result['data'])) return $result['data'];
        return is_array($result) ? $result : [];
    }

    public function getContactTags(string $phone): array
    {
        $contact = $this->getContactByPhone($phone);
        if (!$contact || !isset($contact['tags'])) return [];
        return is_array($contact['tags']) ? $contact['tags'] : [];
    }

    public function setContactTagsByPhone(string $phone, array $tagNames): bool
    {
        $contact = $this->getContactByPhone($phone);
        if (!$contact || !isset($contact['id'])) return false;
        return $this->setContactTags($contact['id'], $tagNames);
    }

    // == Templates WhatsApp (17/02/2026) ==

    public function getWhatsAppTemplates(): ?array
    {
        return $this->apiGet("/whatsapp/templates", ['bot_id' => $this->botId]);
    }

    public function sendTemplateByPhone(string $phone, array $template): array
    {
        \Log::info('SendPulse sendTemplateByPhone', [
            'phone' => $phone,
            'template_name' => $template['name'] ?? 'N/A',
        ]);

        return $this->apiPost("/whatsapp/contacts/sendTemplateByPhone", [
            'bot_id'   => $this->botId,
            'phone'    => $phone,
            'template' => $template,
        ]);
    }

}
