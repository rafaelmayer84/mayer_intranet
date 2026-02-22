<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NexoQa\NexoQaResponseProcessor;
use App\Services\NexoQa\NexoQaResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NexoQaWebhookController extends Controller
{
    /**
     * IPs conhecidos do SendPulse (mesmo whitelist dos outros controllers NEXO).
     */
    private const SENDPULSE_IP_RANGES = [
        '185.23.85.',
        '185.23.86.',
        '185.23.87.',
        '91.229.95.',
        '178.32.',
        '2a02:4780:',   // IPv6 OBRIGATÓRIO
        '188.40.',
    ];

    /**
     * POST /webhooks/sendpulse/nexo-qa
     *
     * Recebe incoming messages do SendPulse e processa respostas de pesquisa QA.
     * Autenticação: Header X-Nexo-Qa-Token OU IP whitelist SendPulse.
     *
     * Payload esperado (formato SendPulse incoming_message):
     * {
     *   "service": "whatsapp",
     *   "event": "incoming_message",
     *   "contact": { "phone": "+554791314240", ... },
     *   "message": { "text": "ABC12345 5 9 Ótimo atendimento!", ... }
     * }
     *
     * OU formato simplificado (chamada interna):
     * {
     *   "phone": "554791314240",
     *   "text": "ABC12345 5 9 Ótimo atendimento!"
     * }
     */
    public function handle(Request $request): JsonResponse
    {
        // Autenticação: token OU IP whitelist
        if (!$this->isAuthorized($request)) {
            Log::warning('[NexoQA Webhook] Requisição não autorizada', [
                'ip' => $request->ip(),
                'has_token' => $request->hasHeader('X-Nexo-Qa-Token'),
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();

        // Extrair telefone e texto da mensagem
        $extracted = $this->extractMessageData($payload);

        if ($extracted === null) {
            Log::info('[NexoQA Webhook] Payload sem dados de mensagem válidos', [
                'keys' => array_keys($payload),
            ]);
            return response()->json(['status' => 'ignored', 'detail' => 'No message data'], 200);
        }

        $phone = $extracted['phone'];
        $text = $extracted['text'];

        // Ignorar mensagens vazias
        if (empty(trim($text))) {
            return response()->json(['status' => 'ignored', 'detail' => 'Empty message'], 200);
        }

        // Verificar se a mensagem parece ser uma resposta QA (começa com token de 8 chars ou é opt-out)
        if (!$this->looksLikeQaResponse($text)) {
            return response()->json(['status' => 'ignored', 'detail' => 'Not a QA response'], 200);
        }

        Log::info('[NexoQA Webhook] Processando resposta', [
            'phone_masked' => $this->maskPhone($phone),
            'text_length' => strlen($text),
        ]);

        // Processar a resposta
        $processor = app(NexoQaResponseProcessor::class);
        $result = $processor->process($text, $phone, $payload);

        Log::info('[NexoQA Webhook] Resultado do processamento', [
            'status' => $result['status'],
            'detail' => $result['detail'],
        ]);

        // Se foi opt-out, enviar confirmação via WhatsApp
        if ($result['status'] === 'opt_out') {
            $this->sendOptOutConfirmation($phone);
        }

        // Se foi resposta válida, enviar agradecimento
        if ($result['status'] === 'ok') {
            $this->sendThankYouMessage($phone);
        }

        return response()->json($result, 200);
    }

    /**
     * Valida autenticação: token OU IP whitelist.
     */
    private function isAuthorized(Request $request): bool
    {
        // Opção 1: Header token
        $token = $request->header('X-Nexo-Qa-Token');
        $expectedToken = config('services.nexo_qa.webhook_token', env('NEXO_QA_WEBHOOK_TOKEN'));

        if (!empty($token) && !empty($expectedToken) && hash_equals($expectedToken, $token)) {
            return true;
        }

        // Opção 2: IP whitelist SendPulse
        $ip = $request->ip();
        foreach (self::SENDPULSE_IP_RANGES as $range) {
            if (str_starts_with($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extrai telefone e texto do payload (suporta formato SendPulse e simplificado).
     */
    private function extractMessageData(array $payload): ?array
    {
        // Formato SendPulse incoming_message
        if (isset($payload['message']['text']) && isset($payload['contact']['phone'])) {
            return [
                'phone' => $payload['contact']['phone'],
                'text' => $payload['message']['text'],
            ];
        }

        // Formato SendPulse alternativo (message.body)
        if (isset($payload['message']['body']) && isset($payload['contact']['phone'])) {
            return [
                'phone' => $payload['contact']['phone'],
                'text' => $payload['message']['body'],
            ];
        }

        // Formato simplificado (chamada interna ou teste)
        if (isset($payload['phone']) && isset($payload['text'])) {
            return [
                'phone' => $payload['phone'],
                'text' => $payload['text'],
            ];
        }

        // Formato SendPulse com telefone no nível raiz
        if (isset($payload['telefone']) && isset($payload['mensagem'])) {
            return [
                'phone' => $payload['telefone'],
                'text' => $payload['mensagem'],
            ];
        }

        return null;
    }

    /**
     * Verifica se a mensagem parece ser uma resposta QA.
     * Token QA: primeiros 8 chars alfanuméricos do UUID.
     * Ou palavras-chave de opt-out.
     */
    private function looksLikeQaResponse(string $text): bool
    {
        $trimmed = trim($text);

        // Opt-out keywords
        $optOutKeywords = ['SAIR', 'PARAR', 'STOP', 'CANCELAR'];
        if (in_array(strtoupper($trimmed), $optOutKeywords, true)) {
            return true;
        }

        // Token pattern: 8 chars alfanuméricos no início (com ou sem espaço depois)
        if (preg_match('/^[A-Za-z0-9]{8}(\s|$)/', $trimmed)) {
            return true;
        }

        return false;
    }

    /**
     * Envia confirmação de opt-out via WhatsApp.
     */
    private function sendOptOutConfirmation(string $phone): void
    {
        try {
            $normalized = NexoQaResolverService::normalizePhone($phone);
            $service = app(\App\Services\SendPulseWhatsAppService::class);
            $service->sendMessageByPhone($normalized, '✅ Sua solicitação foi registrada. Você não receberá mais pesquisas de qualidade. Obrigado!');
        } catch (\Throwable $e) {
            Log::error('[NexoQA Webhook] Falha ao enviar confirmação opt-out', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envia agradecimento após resposta válida.
     */
    private function sendThankYouMessage(string $phone): void
    {
        try {
            $normalized = NexoQaResolverService::normalizePhone($phone);
            $service = app(\App\Services\SendPulseWhatsAppService::class);
            $service->sendMessageByPhone($normalized, '✅ Obrigado pela sua avaliação! Sua opinião é muito importante para melhorarmos nossos serviços. — Equipe Mayer Albanez');
        } catch (\Throwable $e) {
            Log::error('[NexoQA Webhook] Falha ao enviar agradecimento', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mascara telefone para log (sem expor número completo).
     */
    private function maskPhone(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($clean) < 6) {
            return '***';
        }
        return substr($clean, 0, 4) . str_repeat('*', max(0, strlen($clean) - 8)) . substr($clean, -4);
    }
}
