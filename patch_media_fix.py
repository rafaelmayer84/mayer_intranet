#!/usr/bin/env python3
"""
PATCH: Fix media parsing in SendPulseWhatsAppService::parseWebhookIncomingMessage
- Constrói URL de mídia SendPulse a partir de message_id + media_id
- Extrai filename, mime_type e caption do bloco channel_data
- Limpa body quando é URL de mídia (não texto real)
- Adiciona fallback no doSyncMessages para atualizar media_url de mensagens existentes

Arquivo: app/Services/SendPulseWhatsAppService.php
Arquivo: app/Services/NexoConversationSyncService.php
"""
import sys

# ============================================================
# PATCH 1: SendPulseWhatsAppService.php - Fix parseWebhookIncomingMessage
# ============================================================

filepath1 = 'app/Services/SendPulseWhatsAppService.php'

with open(filepath1, 'r', encoding='utf-8') as f:
    content1 = f.read()

# Substituir o bloco de extração de mídia no parseWebhookIncomingMessage
old_media_block = """        // Extrair mídia do webhook
        $mediaData = ['media_url' => null, 'media_mime' => null, 'media_filename' => null, 'media_caption' => null];
        $channelMsg = data_get($event, 'info.message.channel_data.message', []);
        if (is_array($channelMsg) && $msgType !== 'text') {
            foreach (['image', 'document', 'audio', 'video', 'voice', 'sticker'] as $mt) {
                $block = data_get($channelMsg, $mt);
                if (is_array($block)) {
                    $mediaData['media_url']      = data_get($block, 'link') ?? data_get($block, 'url') ?? data_get($block, 'id');
                    $mediaData['media_mime']     = data_get($block, 'mime_type');
                    $mediaData['media_filename'] = data_get($block, 'filename');
                    $mediaData['media_caption']  = data_get($block, 'caption');
                    break;
                }
            }
        }"""

new_media_block = """        // Extrair mídia do webhook (fix 18/02/2026: construir URL SendPulse)
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
        }"""

if old_media_block in content1:
    content1 = content1.replace(old_media_block, new_media_block)
    print(f"[OK] PATCH 1: parseWebhookIncomingMessage media fix aplicado")
else:
    print(f"[ERRO] PATCH 1: bloco de mídia não encontrado em {filepath1}")
    sys.exit(1)

with open(filepath1, 'w', encoding='utf-8') as f:
    f.write(content1)

# ============================================================
# PATCH 2: NexoConversationSyncService.php - Update existing messages on polling
# ============================================================

filepath2 = 'app/Services/NexoConversationSyncService.php'

with open(filepath2, 'r', encoding='utf-8') as f:
    content2 = f.read()

# No doSyncMessages, quando encontra mensagem existente por provider_message_id,
# atualizar media_url se estava NULL
old_dedup = """            $providerMsgId = data_get($msg, 'id') ?? data_get($msg, 'message_id');
            if ($providerMsgId && WaMessage::where('provider_message_id', $providerMsgId)->exists()) {
                continue;
            }"""

new_dedup = """            $providerMsgId = data_get($msg, 'id') ?? data_get($msg, 'message_id');
            if ($providerMsgId) {
                $existingMsg = WaMessage::where('provider_message_id', $providerMsgId)->first();
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
            }"""

if old_dedup in content2:
    content2 = content2.replace(old_dedup, new_dedup)
    print(f"[OK] PATCH 2: doSyncMessages media update fallback aplicado")
else:
    print(f"[ERRO] PATCH 2: bloco dedup não encontrado em {filepath2}")
    sys.exit(1)

with open(filepath2, 'w', encoding='utf-8') as f:
    f.write(content2)

# ============================================================
# PATCH 3: Fix mensagens existentes no banco (one-time SQL)
# ============================================================

print(f"""
[OK] Patches aplicados com sucesso!

=== PATCH 3: SQL para corrigir mensagens existentes ===
Execute no MySQL para corrigir as mensagens que já estão salvas com URL no body:

UPDATE wa_messages 
SET media_url = body, 
    body = COALESCE(media_caption, '')
WHERE message_type IN ('document', 'image', 'video', 'audio', 'voice')
  AND media_url IS NULL 
  AND body LIKE '%login.sendpulse.com/api/chatbots-service/whatsapp/messages/media%';
""")
