<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class LeadProcessingService
{
    /**
     * Obter token de acesso do SendPulse
     */
    public function getSendPulseToken(): ?string
    {
        try {
            $response = Http::post('https://api.sendpulse.com/oauth/access_token', [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.sendpulse.api_id'),
                'client_secret' => config('services.sendpulse.api_secret')
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error('Erro ao obter token SendPulse', ['response' => $response->body()]);
            return null;

        } catch (Exception $e) {
            Log::error('Exceção ao obter token SendPulse', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Buscar contact_id pelo telefone no SendPulse
     */
    public function getContactIdByPhone(string $accessToken, string $phone): ?string
    {
        try {
            $response = Http::withToken($accessToken)
                ->post('https://api.sendpulse.com/whatsapp/contacts/getByPhone', [
                    'bot_id' => config('services.sendpulse.bot_id'),
                    'phone' => $phone
                ]);

            if ($response->successful()) {
                return $response->json('data.id');
            }

            return null;

        } catch (Exception $e) {
            Log::error('Erro ao buscar contact_id', ['phone' => $phone, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Buscar mensagens do chat via API SendPulse
     */
    public function getChatMessages(string $accessToken, string $contactId): ?array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get('https://api.sendpulse.com/whatsapp/chats/messages', [
                    'bot_id' => config('services.sendpulse.bot_id'),
                    'contact_id' => $contactId,
                    'limit' => 500,
                    'offset' => 0
                ]);

            if ($response->successful()) {
                return $response->json('data', []);
            }

            Log::error('Erro ao buscar mensagens', [
                'contact_id' => $contactId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;

        } catch (Exception $e) {
            Log::error('Exceção ao buscar mensagens', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extrair texto de uma mensagem (lógica portada do PHP legado)
     */
    protected function extractText(array $msg): string
    {
        if (empty($msg)) {
            return '';
        }

        $candidates = [];

        // Formato: text.body (mais comum)
        if (isset($msg['text'])) {
            if (is_array($msg['text']) && isset($msg['text']['body']) && is_string($msg['text']['body'])) {
                $candidates[] = $msg['text']['body'];
            } elseif (is_string($msg['text'])) {
                $candidates[] = $msg['text'];
            }
        }

        // Outros campos possíveis
        foreach (['body', 'message', 'content'] as $key) {
            if (isset($msg[$key]) && is_string($msg[$key])) {
                $candidates[] = $msg[$key];
            }
        }

        // Mensagens especiais
        if (isset($msg['audio']) || isset($msg['voice'])) {
            return '[Mensagem de áudio]';
        }
        if (isset($msg['image'])) {
            return '[Imagem enviada]';
        }

        // Retornar primeira string não vazia
        foreach ($candidates as $text) {
            $text = trim($text);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    /**
     * Verificar se mensagem é do cliente (inbound)
     */
    protected function isInbound(array $msg): bool
    {
        if (empty($msg)) {
            return false;
        }

        $direction = $msg['direction'] ?? null;

        if (is_string($direction)) {
            $dir = strtolower(trim($direction));
            if (in_array($dir, ['in', 'incoming', 'inbound', 'client', 'user', 'subscriber', 'contact'])) {
                return true;
            }
            if (in_array($dir, ['out', 'outgoing', 'bot', 'agent', 'operator'])) {
                return false;
            }
        }

        if (is_int($direction)) {
            return $direction === 1;
        }

        if (isset($msg['is_incoming'])) {
            return (bool) $msg['is_incoming'];
        }

        return false;
    }

    /**
     * Processar histórico de chat com OpenAI
     */
    public function processWithOpenAI(array $chatHistory): array
    {
        $gclid = '';
        $clientLines = [];
        $allLines = [];

        // Processar mensagens
        foreach ($chatHistory as $msg) {
            $text = $this->extractText($msg);
            if ($text === '') {
                continue;
            }

            // Extrair GCLID se presente
            if ($gclid === '' && preg_match('/\bGCLID:\s*([A-Za-z0-9_-]+)\b/i', $text, $matches)) {
                $gclid = $matches[1];
            }

            // Remover referência ao GCLID do texto
            $text = preg_replace('/Ref\.\s*GCLID:.*$/i', '', $text);
            $line = "- " . trim($text);

            $allLines[] = $line;
            if ($this->isInbound($msg)) {
                $clientLines[] = $line;
            }
        }

        $clientText = trim(implode("\n", $clientLines));
        $allText = trim(implode("\n", $allLines));
        $conversationText = (strlen($clientText) >= 20) ? $clientText : $allText;

        // Se conversa muito curta, retornar vazio
        if (strlen(trim(preg_replace('/\s+/', ' ', $conversationText))) < 20) {
            return [
                'success' => true,
                'resumo_demanda' => '',
                'palavras_chave' => '',
                'intencao_contratar' => 'não',
                'cidade' => '',
                'gclid_extracted' => $gclid,
                'conversation_text' => $conversationText
            ];
        }

        // Chamar OpenAI
        try {
            $prompt = "Você é um extrator fiel de informações de triagem jurídica.\n\n" .
                      "Regras:\n" .
                      "- Use SOMENTE o que estiver escrito nas mensagens.\n" .
                      "- NÃO invente fatos, valores, datas, locais ou detalhes.\n" .
                      "- Se faltar informação, deixe o campo em branco e marque intenção como 'não' ou 'talvez'.\n\n" .
                      "Mensagens:\n$conversationText\n\n" .
                      "Retorne APENAS JSON válido neste formato:\n" .
                      "{\n" .
                      "  \"resumo_demanda\": \"\",\n" .
                      "  \"palavras_chave\": \"\",\n" .
                      "  \"intencao_contratar\": \"sim|não|talvez\",\n" .
                      "  \"cidade\": \"\"\n" .
                      "}\n";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                'Content-Type' => 'application/json'
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Return ONLY valid JSON. Do not invent facts.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.0,
                'response_format' => ['type' => 'json_object']
            ]);

            if (!$response->successful()) {
                Log::error('Erro OpenAI', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'OpenAI error',
                    'gclid_extracted' => $gclid,
                    'conversation_text' => $conversationText
                ];
            }

            $content = $response->json('choices.0.message.content', '');
            $parsed = json_decode($content, true);

            if (!$parsed || !is_array($parsed)) {
                Log::error('JSON inválido da OpenAI', ['content' => $content]);

                return [
                    'success' => false,
                    'error' => 'JSON inválido',
                    'gclid_extracted' => $gclid,
                    'conversation_text' => $conversationText
                ];
            }

            return [
                'success' => true,
                'resumo_demanda' => $parsed['resumo_demanda'] ?? '',
                'palavras_chave' => $parsed['palavras_chave'] ?? '',
                'intencao_contratar' => $parsed['intencao_contratar'] ?? 'talvez',
                'cidade' => $parsed['cidade'] ?? '',
                'gclid_extracted' => $gclid,
                'conversation_text' => $conversationText
            ];

        } catch (Exception $e) {
            Log::error('Exceção ao processar com OpenAI', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'gclid_extracted' => $gclid,
                'conversation_text' => $conversationText
            ];
        }
    }

    /**
     * Enviar lead para EspoCRM
     */
    public function sendToEspoCRM(Lead $lead): ?string
    {
        try {
            // Preparar descrição
            $description = "[ÁREA JURÍDICA]: " . ($lead->area_interesse ?? 'Não informado') . "\n\n";
            $description .= $lead->resumo_demanda . "\n\n";
            $description .= "INTENÇÃO DE CONTRATAR: " . $lead->intencao_contratar . "\n\n";
            $description .= "Palavras-chave: " . $lead->palavras_chave;

            // Limpar telefone
            $telefone = preg_replace('/[^0-9]/', '', $lead->telefone);

            // Dividir nome
            $nomeParts = explode(' ', trim($lead->nome), 2);
            $firstName = $nomeParts[0] ?? '';
            $lastName = $nomeParts[1] ?? '';

            // Payload
            $payload = [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'phoneNumber' => $telefone,
                'addressCity' => $lead->cidade ?? '',
                'addressState' => 'SC',
                'addressCountry' => 'Brasil',
                'source' => 'WhatsApp Bot',
                'status' => 'New',
                'cGCLID' => $lead->gclid ?? '',
                'description' => $description
            ];

            $response = Http::withHeaders([
                'X-Api-Key' => config('services.espocrm.api_key'),
                'Content-Type' => 'application/json',
                'X-Skip-Duplicate-Check' => 'true'
            ])->post(config('services.espocrm.url') . '/Lead', $payload);

            if ($response->successful()) {
                $crmLeadId = $response->json('id');
                Log::info('Lead enviado ao EspoCRM', [
                    'lead_id' => $lead->id,
                    'crm_lead_id' => $crmLeadId
                ]);
                return $crmLeadId;
            }

            Log::error('Erro ao enviar lead ao EspoCRM', [
                'lead_id' => $lead->id,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;

        } catch (Exception $e) {
            Log::error('Exceção ao enviar lead ao EspoCRM', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Salvar mensagens do histórico
     */
    public function saveMessages(Lead $lead, array $messages): void
    {
        foreach ($messages as $msg) {
            $text = $this->extractText($msg);
            if ($text === '') {
                continue;
            }

            $direction = $this->isInbound($msg) ? 'in' : 'out';
            $messageType = 'text';

            if (isset($msg['audio']) || isset($msg['voice'])) {
                $messageType = 'audio';
            } elseif (isset($msg['image'])) {
                $messageType = 'image';
            } elseif (isset($msg['document'])) {
                $messageType = 'document';
            }

            LeadMessage::create([
                'lead_id' => $lead->id,
                'direction' => $direction,
                'message_text' => $text,
                'message_type' => $messageType,
                'raw_data' => $msg,
                'sent_at' => isset($msg['created_at']) ? $msg['created_at'] : now()
            ]);
        }
    }

    /**
     * Processar lead completo (método principal)
     */
    public function processLead(array $webhookData): ?Lead
    {
        $nome = '';
        $telefone = '';
        $contactId = null;

        try {
            Log::info('=== INICIO processLead ===', ['webhook_keys' => array_keys($webhookData)]);

            $data = $webhookData;
            if (isset($webhookData[0]) && is_array($webhookData[0])) {
                $data = $webhookData[0];
            }

            $contactId = $data['contact']['id'] ?? $data['contact_id'] ?? null;
            $botId = $data['bot']['id'] ?? $data['bot_id'] ?? null;
            $nome = $data['contact']['name'] ?? $data['nome'] ?? '';
            $telefone = $data['contact']['phone'] ?? $data['telefone'] ?? '';
            $variables = $data['contact']['variables'] ?? $data['variables'] ?? [];
            $tags = $data['contact']['tags'] ?? $data['tags'] ?? [];

            Log::info('Dados extraidos', [
                'contact_id' => $contactId, 'bot_id' => $botId,
                'nome' => $nome, 'telefone' => $telefone,
                'variables' => $variables, 'tags' => $tags
            ]);

            if (!$contactId || !$botId) {
                Log::error('Webhook sem contact_id ou bot_id');
                return null;
            }

            $existingLead = Lead::where('telefone', $telefone)->where('contact_id', $contactId)->first();
            if ($existingLead) {
                Log::info('Lead ja existe', ['lead_id' => $existingLead->id]);
                return $existingLead;
            }

            Log::info('Obtendo token SendPulse...');
            $accessToken = $this->getSendPulseToken();
            if (!$accessToken) { throw new \Exception('Token SendPulse falhou'); }
            Log::info('Token obtido');

            sleep(3);

            Log::info('Buscando mensagens...');
            $chatMessages = $this->getChatMessages($accessToken, $contactId);
            $msgCount = is_array($chatMessages) ? count($chatMessages) : 0;
            Log::info('Mensagens recuperadas', ['total' => $msgCount]);

            $area = '';
            foreach ($tags as $tag) {
                if (in_array($tag, ['Trabalhista', 'Previdenciário', 'Civil', 'Cível', 'Outras áreas', 'Penal'])) {
                    $area = $tag; break;
                }
            }

            $cidade = $variables['Cidade'] ?? $variables['cidade'] ?? '';
            $gclid = $variables['GCLID'] ?? $variables['gclid'] ?? '';

            if (!$chatMessages || $msgCount === 0) {
                Log::warning('Sem mensagens, criando lead sem IA');
                $lead = Lead::create([
                    'nome' => $nome, 'telefone' => $telefone, 'contact_id' => $contactId,
                    'area_interesse' => $area ?: 'não identificado', 'cidade' => $cidade,
                    'resumo_demanda' => '', 'palavras_chave' => '',
                    'intencao_contratar' => 'não', 'gclid' => $gclid,
                    'status' => 'novo', 'erro_processamento' => 'Mensagens nao recuperadas',
                    'data_entrada' => now()
                ]);
                $crmLeadId = $this->sendToEspoCRM($lead);
                if ($crmLeadId) { $lead->update(['espocrm_id' => $crmLeadId]); }
                return $lead;
            }

            Log::info('Processando com OpenAI...');
            $aiResult = $this->processWithOpenAI($chatMessages);
            Log::info('Resultado OpenAI', [
                'success' => $aiResult['success'] ?? false,
                'resumo' => substr($aiResult['resumo_demanda'] ?? '', 0, 100),
                'palavras' => $aiResult['palavras_chave'] ?? '',
                'intencao' => $aiResult['intencao_contratar'] ?? '',
                'cidade_ia' => $aiResult['cidade'] ?? ''
            ]);

            if (empty($cidade) && !empty($aiResult['cidade'])) {
                $cidade = $aiResult['cidade'];
            }

            $lead = Lead::create([
                'nome' => $nome, 'telefone' => $telefone, 'contact_id' => $contactId,
                'area_interesse' => $area ?: 'não identificado', 'cidade' => $cidade,
                'resumo_demanda' => $aiResult['resumo_demanda'] ?? '',
                'palavras_chave' => $aiResult['palavras_chave'] ?? '',
                'intencao_contratar' => $aiResult['intencao_contratar'] ?? 'não',
                'gclid' => $gclid ?: ($aiResult['gclid_extracted'] ?? ''),
                'status' => 'novo',
                'erro_processamento' => ($aiResult['success'] ?? false) ? null : ($aiResult['error'] ?? 'Erro OpenAI'),
                'data_entrada' => now()
            ]);

            Log::info('Lead criado', ['lead_id' => $lead->id, 'cidade' => $cidade]);

            $this->saveMessages($lead, $chatMessages);
            $crmLeadId = $this->sendToEspoCRM($lead);
            if ($crmLeadId) { $lead->update(['espocrm_id' => $crmLeadId]); }

            Log::info('Lead processado com sucesso', ['lead_id' => $lead->id, 'nome' => $nome, 'telefone' => $telefone]);
            return $lead;

        } catch (\Exception $e) {
            Log::error('Erro ao processar lead', ['error' => $e->getMessage()]);
            try {
                return Lead::create([
                    'nome' => $nome ?: 'Desconhecido', 'telefone' => $telefone ?: 'Desconhecido',
                    'contact_id' => $contactId, 'status' => 'novo',
                    'erro_processamento' => $e->getMessage(), 'data_entrada' => now()
                ]);
            } catch (\Exception $e2) {
                Log::error('Erro ao salvar lead com erro', ['error' => $e2->getMessage()]);
                return null;
            }
        }
    }


        public function reprocessLead(Lead $lead): bool
    {
        try {
            // Obter token
            $accessToken = $this->getSendPulseToken();
            if (!$accessToken) {
                $lead->update(['erro_processamento' => 'Não foi possível obter token do SendPulse']);
                return false;
            }

            // Se não tem contact_id, buscar pelo telefone
            if (!$lead->contact_id) {
                $contactId = $this->getContactIdByPhone($accessToken, $lead->telefone);
                if (!$contactId) {
                    $lead->update(['erro_processamento' => 'Contact ID não encontrado']);
                    return false;
                }
                $lead->update(['contact_id' => $contactId]);
            }

            // Buscar mensagens
            $chatMessages = $this->getChatMessages($accessToken, $lead->contact_id);
            if (!$chatMessages) {
                $lead->update(['erro_processamento' => 'Não foi possível buscar mensagens']);
                return false;
            }

            // Processar com OpenAI
            $aiResult = $this->processWithOpenAI($chatMessages);
            if (!$aiResult['success']) {
                $lead->update(['erro_processamento' => $aiResult['error'] ?? 'Erro ao processar com OpenAI']);
                return false;
            }

            // Atualizar lead
            $lead->update([
                'resumo_demanda' => $aiResult['resumo_demanda'] ?? '',
                'palavras_chave' => $aiResult['palavras_chave'] ?? '',
                'intencao_contratar' => $aiResult['intencao_contratar'] ?? 'talvez',
                'cidade' => $aiResult['cidade'] ?? $lead->cidade,
                'gclid' => $aiResult['gclid_extracted'] ?? $lead->gclid,
                'erro_processamento' => null
            ]);

            // Recriar mensagens
            $lead->messages()->delete();
            $this->saveMessages($lead, $chatMessages);

            // Enviar para EspoCRM se ainda não foi
            if (!$lead->espocrm_id) {
                $crmLeadId = $this->sendToEspoCRM($lead);
                if ($crmLeadId) {
                    $lead->update(['espocrm_id' => $crmLeadId]);
                }
            }

            Log::info('Lead reprocessado com sucesso', ['lead_id' => $lead->id]);
            return true;

        } catch (Exception $e) {
            Log::error('Erro ao reprocessar lead', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage()
            ]);

            $lead->update(['erro_processamento' => $e->getMessage()]);
            return false;
        }
    }
}
