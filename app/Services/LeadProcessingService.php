<?php

namespace App\Services;

use App\Models\Lead;
use App\Services\LeadTrackingService;
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
                ->get('https://api.sendpulse.com/whatsapp/contacts/getByPhone', [
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
     * Extrair texto de uma mensagem SendPulse
     * Suporta múltiplos formatos de payload
     */
    protected function extractText(array $msg): string
    {
        if (empty($msg)) {
            return '';
        }

        $candidates = [];

        // ============================================
        // FORMATO SENDPULSE REAL: data.text.body
        // ============================================
        if (isset($msg['data'])) {
            $d = $msg['data'];

            // data.text.body (formato principal WhatsApp)
            if (isset($d['text']['body']) && is_string($d['text']['body'])) {
                $candidates[] = $d['text']['body'];
            }
            // data.text como string
            if (isset($d['text']) && is_string($d['text'])) {
                $candidates[] = $d['text'];
            }
            // data.body
            if (isset($d['body']) && is_string($d['body'])) {
                $candidates[] = $d['body'];
            }
            // data.message.text.body
            if (isset($d['message']['text']['body']) && is_string($d['message']['text']['body'])) {
                $candidates[] = $d['message']['text']['body'];
            }
            // data.message como string
            if (isset($d['message']) && is_string($d['message'])) {
                $candidates[] = $d['message'];
            }
            // data.content
            if (isset($d['content']) && is_string($d['content'])) {
                $candidates[] = $d['content'];
            }
        }

        // ============================================
        // FORMATOS ALTERNATIVOS (fallback)
        // ============================================

        // text.body
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

        // message como array
        if (isset($msg['message']) && is_array($msg['message'])) {
            foreach (['text', 'body', 'content'] as $k) {
                if (isset($msg['message'][$k]) && is_string($msg['message'][$k])) {
                    $candidates[] = $msg['message'][$k];
                }
            }
            if (isset($msg['message']['text']['body']) && is_string($msg['message']['text']['body'])) {
                $candidates[] = $msg['message']['text']['body'];
            }
        }

        // Retornar primeira string não vazia
        foreach ($candidates as $text) {
            $text = trim($text);
            if ($text !== '') {
                return $text;
            }
        }

        // Mensagens especiais (mídia)
        if (isset($msg['data']['type'])) {
            $type = $msg['data']['type'];
            if ($type === 'audio' || $type === 'voice') return '[Mensagem de áudio]';
            if ($type === 'image') return '[Imagem enviada]';
            if ($type === 'document') return '[Documento enviado]';
            if ($type === 'video') return '[Vídeo enviado]';
            if ($type === 'sticker') return '[Sticker]';
        }
        if (isset($msg['audio']) || isset($msg['voice'])) return '[Mensagem de áudio]';
        if (isset($msg['image'])) return '[Imagem enviada]';

        return '';
    }

    /**
     * Verificar se mensagem é do cliente (inbound)
     * SendPulse: direction 1 = incoming (cliente), 2 = outgoing (bot)
     */
    protected function isInbound(array $msg): bool
    {
        if (empty($msg)) {
            return false;
        }

        $direction = $msg['direction'] ?? null;

        // SendPulse usa inteiro: 1 = incoming, 2 = outgoing
        if (is_int($direction)) {
            return $direction === 1;
        }

        if (is_string($direction)) {
            $dir = strtolower(trim($direction));
            if (in_array($dir, ['in', 'incoming', 'inbound', 'client', 'user', 'subscriber', 'contact'])) {
                return true;
            }
            if (in_array($dir, ['out', 'outgoing', 'bot', 'agent', 'operator'])) {
                return false;
            }
        }

        if (isset($msg['is_incoming'])) {
            return (bool) $msg['is_incoming'];
        }

        return false;
    }

    /**
     * Processar histórico de chat com OpenAI — Especialista em Marketing Jurídico
     */
    public function processWithOpenAI(array $chatHistory): array
    {
        $gclid = '';
        $clientLines = [];
        $allLines = [];

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

        Log::info('Texto extraido para OpenAI', [
            'total_linhas' => count($allLines),
            'linhas_cliente' => count($clientLines),
            'tamanho_texto' => strlen($conversationText)
        ]);

        // Se conversa muito curta, retornar defaults
        if (strlen(trim(preg_replace('/\s+/', ' ', $conversationText))) < 20) {
            Log::warning('Conversa muito curta para análise IA', ['tamanho' => strlen($conversationText)]);
            return [
                'success' => true,
                'resumo_demanda' => '',
                'palavras_chave' => '',
                'intencao_contratar' => 'não',
                'intencao_justificativa' => 'Conversa muito curta para análise',
                'area_direito' => '',
                'sub_area' => '',
                'complexidade' => '',
                'urgencia' => '',
                'objecoes' => '',
                'gatilho_emocional' => '',
                'perfil_socioeconomico' => '',
                'potencial_honorarios' => '',
                'cidade' => '',
                'origem_canal' => 'nao_identificado',
                'gclid_extracted' => $gclid,
                'conversation_text' => $conversationText
            ];
        }

        // ========================================================
        // PROMPT ESPECIALISTA EM MARKETING JURÍDICO
        // ========================================================
        try {
            $prompt = <<<PROMPT
Você é um ANALISTA SÊNIOR DE PERFORMANCE E TRÁFEGO PAGO especializado em marketing digital para escritórios de advocacia brasileiros. Sua função é analisar conversas de triagem jurídica via WhatsApp e extrair inteligência acionável para: otimização de campanhas Google Ads, criação de audiências Customer Match, segmentação de público-alvo, e mensuração de qualidade de leads (lead scoring).

## CONTEXTO DO NEGÓCIO
O escritório Mayer Advogados investe em Google Ads para captar leads via WhatsApp. Cada conversa abaixo é de um potencial cliente que chegou através de algum canal de marketing. Sua análise alimenta dashboards de BI e decisões de investimento em mídia paga.

## REGRAS ABSOLUTAS
1. Use SOMENTE informações presentes nas mensagens — não invente NADA
2. Se uma informação não existir na conversa, retorne string VAZIA ("")
3. Palavras-chave DEVEM ser termos de busca reais que uma pessoa digitaria no Google (ex: "advogado trabalhista blumenau", "como processar empresa por dano moral")
4. NÃO use termos técnicos jurídicos como palavras-chave — use linguagem LEIGA de quem busca no Google
5. Cada campo deve ter valor específico conforme os ENUMs definidos — sem variações

## CRITÉRIOS DE INTENÇÃO DE CONTRATAR (lead scoring)
- "sim": Cliente expressou AÇÃO CONCRETA — pediu orçamento/valor, perguntou como contratar, disse "quero resolver isso", "preciso de um advogado", "pode me ajudar?", "vamos fechar", pediu reunião/consulta
- "talvez": Cliente busca informação mas demonstra dor — descreve problema pessoal, pergunta se tem direito, demonstra emoção mas não pediu contratação explicitamente
- "não": Conversa genérica sem problema concreto, pergunta teórica, já tem advogado, explicitamente disse que não quer contratar, conversa incompleta/truncada

## PALAVRAS-CHAVE PARA GOOGLE ADS
Gere entre 5 e 10 termos de busca no padrão que um LEIGO digitaria no Google:
- BOM: "advogado para demissão injusta", "quanto custa processo trabalhista", "advogado dívida banco blumenau"
- RUIM: "rescisão contratual", "litígio cível", "demanda consumerista" (termos técnicos que leigos não buscam)
- INCLUA variações com cidade se mencionada (ex: "advogado blumenau", "advogado trabalhista itajaí")
- INCLUA termos de cauda longa (ex: "como processar empresa que não pagou horas extras")

## ANÁLISE DE PERFIL SOCIOECONÔMICO (para segmentação de audiência)
- "A": Menciona empresa própria, patrimônio alto, holdings, investimentos, imóveis múltiplos, funcionários
- "B": Profissional qualificado, CLT com salário médio-alto, problema de valor médio (R$ 10k-100k)
- "C": Trabalhador comum, problemas trabalhistas/consumidor de valores menores, linguagem simples
- "D": Dificuldade de expressão, problemas básicos, menciona não ter recursos, linguagem muito informal

## ANÁLISE DE ORIGEM (atribuição de canal)
- "google_ads": Qualquer menção a anúncio, "vi no Google", contém GCLID, encontrou pesquisando online
- "indicacao": "Fulano indicou", "me passaram seu contato", "minha amiga recomendou"
- "redes_sociais": Menciona Instagram, Facebook, TikTok, "vi nas redes", "vi seu post"
- "organico": "Achei no Google Maps", "vi o site de vocês", sem menção a anúncio
- "nao_identificado": Impossível determinar a origem pelas mensagens

## ÁREA DO DIREITO (classificação rigorosa)
Use EXATAMENTE um destes valores:
Trabalhista | Cível | Penal | Previdenciário | Empresarial | Família | Consumidor | Tributário | Imobiliário | Contratual | Bancário | Trânsito | Mediação | Outra

Critérios:
- Trabalhista: demissão, salário, FGTS, horas extras, assédio no trabalho, acidente de trabalho, vínculo empregatício
- Cível: dano moral (fora relação de consumo), responsabilidade civil, cobranças entre particulares
- Consumidor: problemas com empresas/bancos/lojas, produto defeituoso, cobrança indevida, negativação indevida, financiamento
- Bancário: dívida com banco, renegociação, busca e apreensão de veículo, revisão de contrato bancário
- Família: divórcio, pensão, guarda, inventário, testamento
- Contratual: revisão de contrato, distrato, inadimplemento contratual
- Empresarial: abertura/fechamento de empresa, sócio, holding, contrato social
- Previdenciário: aposentadoria, auxílio-doença, INSS, BPC/LOAS
- Imobiliário: compra/venda imóvel, locação, usucapião, despejo

## MENSAGENS DA CONVERSA:
$conversationText

## RETORNE APENAS JSON VÁLIDO (sem markdown, sem backticks, sem explicações):
{
    "resumo_demanda": "Resumo claro e objetivo do problema jurídico em 2-4 frases, na perspectiva de qualificação comercial do lead (qual o problema, qual o interesse, qual a urgência percebida)",
    "area_direito": "Trabalhista|Cível|Penal|Previdenciário|Empresarial|Família|Consumidor|Tributário|Imobiliário|Contratual|Bancário|Trânsito|Mediação|Outra",
    "sub_area": "Sub-área específica (ex: Demissão sem justa causa, Negativação indevida, Busca e apreensão, Divórcio consensual)",
    "palavras_chave": "5-10 termos de busca Google separados por vírgula, linguagem leiga, incluir cidade se mencionada",
    "intencao_contratar": "sim|talvez|não",
    "intencao_justificativa": "Justificativa baseada em evidência concreta do que o cliente disse (citar trecho ou comportamento)",
    "complexidade": "baixa|média|alta",
    "urgencia": "baixa|média|alta|crítica",
    "objecoes": "Objeções identificadas: preco|tempo|desconfianca|quer_informacao|comparando_escritorios|ja_tem_advogado (separar por vírgula) ou vazio",
    "gatilho_emocional": "raiva|medo|urgência|frustração|esperança|oportunidade|desespero|indignação|preocupação",
    "perfil_socioeconomico": "A|B|C|D",
    "potencial_honorarios": "baixo|médio|alto",
    "cidade": "Cidade mencionada ou inferida pelo DDD ou vazio",
    "origem_canal": "google_ads|indicacao|redes_sociais|organico|nao_identificado"
}
PROMPT;
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.openai.api_key'),
                'Content-Type' => 'application/json'
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-5',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é um analista sênior de marketing jurídico brasileiro. Retorne APENAS JSON válido sem markdown, sem backticks, sem explicações. Não invente informações.'
                    ],
                    ['role' => 'user', 'content' => $prompt]
                ],
                // 'temperature' => 0.1, // GPT-5 usa default only
                'response_format' => ['type' => 'json_object']
            ]);

            if (!$response->successful()) {
                Log::error('Erro OpenAI', [
                    'status' => $response->status(),
                    'response' => substr($response->body(), 0, 500)
                ]);

                return [
                    'success' => false,
                    'error' => 'OpenAI HTTP ' . $response->status(),
                    'gclid_extracted' => $gclid,
                    'conversation_text' => $conversationText
                ];
            }

            $content = $response->json('choices.0.message.content', '');
            Log::info('OpenAI resposta raw', ['content' => substr($content, 0, 500)]);

            $parsed = json_decode($content, true);

            if (!$parsed || !is_array($parsed)) {
                Log::error('JSON inválido da OpenAI', ['content' => $content]);
                return [
                    'success' => false,
                    'error' => 'JSON inválido da OpenAI',
                    'gclid_extracted' => $gclid,
                    'conversation_text' => $conversationText
                ];
            }

            return [
                'success' => true,
                'resumo_demanda' => $parsed['resumo_demanda'] ?? '',
                'palavras_chave' => $parsed['palavras_chave'] ?? '',
                'intencao_contratar' => $parsed['intencao_contratar'] ?? 'talvez',
                'intencao_justificativa' => $parsed['intencao_justificativa'] ?? '',
                'area_direito' => $parsed['area_direito'] ?? '',
                'sub_area' => $parsed['sub_area'] ?? '',
                'complexidade' => $parsed['complexidade'] ?? '',
                'urgencia' => $parsed['urgencia'] ?? '',
                'objecoes' => $parsed['objecoes'] ?? '',
                'gatilho_emocional' => $parsed['gatilho_emocional'] ?? '',
                'perfil_socioeconomico' => $parsed['perfil_socioeconomico'] ?? '',
                'potencial_honorarios' => $parsed['potencial_honorarios'] ?? '',
                'cidade' => $parsed['cidade'] ?? '',
                'origem_canal' => $parsed['origem_canal'] ?? 'nao_identificado',
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
            $description = "[ÁREA JURÍDICA]: " . ($lead->area_interesse ?? 'Não informado') . "\n";
            if ($lead->sub_area) {
                $description .= "[SUB-ÁREA]: " . $lead->sub_area . "\n";
            }
            $description .= "\n" . $lead->resumo_demanda . "\n\n";
            $description .= "INTENÇÃO DE CONTRATAR: " . $lead->intencao_contratar;
            if ($lead->intencao_justificativa) {
                $description .= " — " . $lead->intencao_justificativa;
            }
            $description .= "\n\n";
            if ($lead->palavras_chave) {
                $description .= "Palavras-chave: " . $lead->palavras_chave . "\n";
            }
            if ($lead->urgencia) {
                $description .= "Urgência: " . $lead->urgencia . "\n";
            }
            if ($lead->potencial_honorarios) {
                $description .= "Potencial de honorários: " . $lead->potencial_honorarios . "\n";
            }

            $telefone = preg_replace('/[^0-9]/', '', $lead->telefone);

            $nomeParts = explode(' ', trim($lead->nome), 2);
            $firstName = $nomeParts[0] ?? '';
            $lastName = $nomeParts[1] ?? '';

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

            // Detectar tipo de mídia
            $dataType = $msg['data']['type'] ?? $msg['type'] ?? 'text';
            if (in_array($dataType, ['audio', 'voice'])) {
                $messageType = 'audio';
            } elseif ($dataType === 'image') {
                $messageType = 'image';
            } elseif ($dataType === 'document') {
                $messageType = 'document';
            } elseif ($dataType === 'video') {
                $messageType = 'video';
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

            // Normalizar payload: SendPulse envia array [{...}]
            $data = $webhookData;
            if (isset($webhookData[0]) && is_array($webhookData[0])) {
                $data = $webhookData[0];
            }

            // Extrair dados — suporte a formato SendPulse e testes curl
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

            // Idempotência
            $existingLead = Lead::where('telefone', $telefone)->first();

            if ($existingLead) {
                // Atualizar contact_id se veio vazio
                if (empty($existingLead->contact_id) && !empty($contactId)) {
                    $existingLead->contact_id = $contactId;
                    $existingLead->timestamps = false;
                    $existingLead->save();
                }
                Log::info('Lead ja existe', ['lead_id' => $existingLead->id]);
                return $existingLead;
            }

            // Token SendPulse
            Log::info('Obtendo token SendPulse...');
            $accessToken = $this->getSendPulseToken();
            if (!$accessToken) {
                throw new Exception('Token SendPulse falhou');
            }
            Log::info('Token obtido');

            sleep(3);

            // Buscar mensagens
            Log::info('Buscando mensagens...');
            $chatMessages = $this->getChatMessages($accessToken, $contactId);
            $msgCount = is_array($chatMessages) ? count($chatMessages) : 0;
            Log::info('Mensagens recuperadas', ['total' => $msgCount]);

            // Extrair área das tags
            $area = '';
            foreach ($tags as $tag) {
                if (in_array($tag, ['Trabalhista', 'Previdenciário', 'Civil', 'Cível', 'Outras áreas', 'Penal', 'Empresarial', 'Família', 'Consumidor'])) {
                    $area = $tag;
                    break;
                }
            }

            $cidade = $variables['Cidade'] ?? $variables['cidade'] ?? '';
            $gclid = $variables['GCLID'] ?? $variables['gclid'] ?? '';

            // Se sem mensagens, criar lead básico
            if (!$chatMessages || $msgCount === 0) {
                Log::warning('Sem mensagens, criando lead sem IA');
                $lead = Lead::create([
                    'nome' => $nome,
                    'telefone' => $telefone,
                    'contact_id' => $contactId,
                    'area_interesse' => $area ?: 'não identificado',
                    'cidade' => $cidade,
                    'gclid' => $gclid,
                    'status' => 'novo',
                    'intencao_contratar' => 'não',
                    'erro_processamento' => 'Mensagens não recuperadas da API SendPulse',
                    'data_entrada' => now()
                ]);
                $crmLeadId = $this->sendToEspoCRM($lead);
                if ($crmLeadId) {
                    $lead->update(['espocrm_id' => $crmLeadId]);
                }
                return $lead;
            }

            // Processar com OpenAI
            Log::info('Processando com OpenAI...');
            $aiResult = $this->processWithOpenAI($chatMessages);
            Log::info('Resultado OpenAI', [
                'success' => $aiResult['success'] ?? false,
                'resumo' => substr($aiResult['resumo_demanda'] ?? '', 0, 100),
                'palavras' => $aiResult['palavras_chave'] ?? '',
                'intencao' => $aiResult['intencao_contratar'] ?? '',
                'area_ia' => $aiResult['area_direito'] ?? '',
                'sub_area' => $aiResult['sub_area'] ?? '',
                'cidade_ia' => $aiResult['cidade'] ?? '',
                'error' => $aiResult['error'] ?? null
            ]);

            // Prioridade: webhook > IA > vazio
            if (empty($cidade) && !empty($aiResult['cidade'])) {
                $cidade = $aiResult['cidade'];
            }
            if (empty($area) && !empty($aiResult['area_direito'])) {
                $area = $aiResult['area_direito'];
            }

            // Criar lead com todos os campos de marketing jurídico
            $lead = Lead::create([
                'nome' => $nome,
                'telefone' => $telefone,
                'contact_id' => $contactId,
                'area_interesse' => $area ?: 'não identificado',
                'sub_area' => $aiResult['sub_area'] ?? '',
                'complexidade' => $aiResult['complexidade'] ?? '',
                'urgencia' => $aiResult['urgencia'] ?? '',
                'cidade' => $cidade,
                'resumo_demanda' => $aiResult['resumo_demanda'] ?? '',
                'palavras_chave' => $aiResult['palavras_chave'] ?? '',
                'intencao_contratar' => $aiResult['intencao_contratar'] ?? 'não',
                'intencao_justificativa' => $aiResult['intencao_justificativa'] ?? '',
                'objecoes' => $aiResult['objecoes'] ?? '',
                'gatilho_emocional' => $aiResult['gatilho_emocional'] ?? '',
                'perfil_socioeconomico' => $aiResult['perfil_socioeconomico'] ?? '',
                'potencial_honorarios' => $aiResult['potencial_honorarios'] ?? '',
                'origem_canal' => $aiResult['origem_canal'] ?? 'nao_identificado',
                'gclid' => $gclid ?: ($aiResult['gclid_extracted'] ?? ''),
                'status' => 'novo',
                'erro_processamento' => ($aiResult['success'] ?? false) ? null : ($aiResult['error'] ?? 'Erro OpenAI'),
                'data_entrada' => now()
            ]);

            Log::info('Lead criado', ['lead_id' => $lead->id, 'cidade' => $cidade, 'area' => $lead->area_interesse]);
            // === TRACKING DE ORIGEM (GCLID/UTM) ===
            try { \App\Services\LeadTrackingService::applyToLead($lead); } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning("[Lead] Tracking: " . $e->getMessage()); }

            // Salvar mensagens
            $this->saveMessages($lead, $chatMessages);

            // Enviar para EspoCRM
            $crmLeadId = $this->sendToEspoCRM($lead);
            if ($crmLeadId) {
                $lead->update(['espocrm_id' => $crmLeadId]);
            }

            Log::info('Lead processado com sucesso', [
                'lead_id' => $lead->id,
                'nome' => $nome,
                'telefone' => $telefone
            ]);

            return $lead;

        } catch (Exception $e) {
            Log::error('Erro ao processar lead', ['error' => $e->getMessage()]);

            try {
                return Lead::create([
                    'nome' => $nome ?: 'Desconhecido',
                    'telefone' => $telefone ?: 'Desconhecido',
                    'contact_id' => $contactId,
                    'status' => 'novo',
                    'erro_processamento' => $e->getMessage(),
                    'data_entrada' => now()
                ]);
            } catch (Exception $e2) {
                Log::error('Erro ao salvar lead com erro', ['error' => $e2->getMessage()]);
                return null;
            }
        }
    }

    /**
     * Reprocessar lead existente
     */
    public function reprocessLead(Lead $lead): bool
    {
        try {
            $accessToken = $this->getSendPulseToken();
            if (!$accessToken) {
                $lead->update(['erro_processamento' => 'Não foi possível obter token do SendPulse']);
                return false;
            }

            if (!$lead->contact_id) {
                $contactId = $this->getContactIdByPhone($accessToken, $lead->telefone);
                if (!$contactId) {
                    $lead->update(['erro_processamento' => 'Contact ID não encontrado']);
                    return false;
                }
                $lead->update(['contact_id' => $contactId]);
            }

            $chatMessages = $this->getChatMessages($accessToken, $lead->contact_id);
            if (!$chatMessages) {
                $lead->update(['erro_processamento' => 'Não foi possível buscar mensagens']);
                return false;
            }

            $aiResult = $this->processWithOpenAI($chatMessages);
            if (!($aiResult['success'] ?? false)) {
                $lead->update(['erro_processamento' => $aiResult['error'] ?? 'Erro ao processar com OpenAI']);
                return false;
            }

            // Atualizar lead com todos os campos de marketing jurídico
            $updateData = [
                'resumo_demanda' => $aiResult['resumo_demanda'] ?? '',
                'palavras_chave' => $aiResult['palavras_chave'] ?? '',
                'intencao_contratar' => $aiResult['intencao_contratar'] ?? 'talvez',
                'intencao_justificativa' => $aiResult['intencao_justificativa'] ?? '',
                'sub_area' => $aiResult['sub_area'] ?? '',
                'complexidade' => $aiResult['complexidade'] ?? '',
                'urgencia' => $aiResult['urgencia'] ?? '',
                'objecoes' => $aiResult['objecoes'] ?? '',
                'gatilho_emocional' => $aiResult['gatilho_emocional'] ?? '',
                'perfil_socioeconomico' => $aiResult['perfil_socioeconomico'] ?? '',
                'potencial_honorarios' => $aiResult['potencial_honorarios'] ?? '',
                'origem_canal' => $aiResult['origem_canal'] ?? $lead->origem_canal,
                'erro_processamento' => null
            ];

            // Atualizar cidade e área apenas se vieram da IA e estão vazios
            if (empty($lead->cidade) && !empty($aiResult['cidade'])) {
                $updateData['cidade'] = $aiResult['cidade'];
            }
            if ((!$lead->area_interesse || $lead->area_interesse === 'não identificado') && !empty($aiResult['area_direito'])) {
                $updateData['area_interesse'] = $aiResult['area_direito'];
            }
            if (empty($lead->gclid) && !empty($aiResult['gclid_extracted'])) {
                $updateData['gclid'] = $aiResult['gclid_extracted'];
            }

            $lead->update($updateData);

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
