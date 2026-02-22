<?php

namespace App\Services\NexoQa;

use App\Models\NexoQaCampaign;
use App\Models\NexoQaSampledTarget;
use App\Services\SendPulseWhatsAppService;
use Illuminate\Support\Facades\Log;

class NexoQaSurveyService
{
    private SendPulseWhatsAppService $sendPulse;

    public function __construct(SendPulseWhatsAppService $sendPulse)
    {
        $this->sendPulse = $sendPulse;
    }

    /**
     * Envia a pesquisa de qualidade para um alvo específico.
     * Usa sendMessageByPhone com mensagem direta (sem flow SendPulse).
     *
     * @return bool true se enviou com sucesso
     */
    public function sendSurvey(NexoQaSampledTarget $target): bool
    {
        if (!$target->isPending()) {
            Log::warning('[NexoQA] Tentativa de envio para target não-PENDING', [
                'target_id' => $target->id,
                'status' => $target->send_status,
            ]);
            return false;
        }

        $campaign = $target->campaign;
        $questions = $campaign->getEffectiveQuestions();

        // Montar mensagem da pesquisa
        $message = $this->buildSurveyMessage($target, $questions);

        try {
            $result = $this->sendPulse->sendMessageByPhone($target->phone_e164, $message);

            // Verificar se SendPulse retornou sucesso
            if (isset($result['success']) && $result['success'] === true) {
                $messageId = $result['data']['id'] ?? $result['id'] ?? null;
                $target->markSent($messageId);

                Log::info('[NexoQA] Pesquisa enviada', [
                    'target_id' => $target->id,
                    'phone' => $target->masked_phone,
                ]);

                return true;
            }

            // Falha controlada
            $errorMsg = $result['message'] ?? json_encode($result);
            $target->markFailed('SendPulse: ' . $errorMsg);

            Log::error('[NexoQA] Falha no envio da pesquisa', [
                'target_id' => $target->id,
                'error' => $errorMsg,
            ]);

            return false;
        } catch (\Throwable $e) {
            $target->markFailed('Exception: ' . $e->getMessage());

            Log::error('[NexoQA] Exceção no envio da pesquisa', [
                'target_id' => $target->id,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Monta a mensagem de texto da pesquisa.
     * Inclui token para correlacionar resposta (sem expor advogado).
     */
    private function buildSurveyMessage(NexoQaSampledTarget $target, array $questions): string
    {
        $tokenShort = strtoupper(substr($target->token, 0, 8));

        $lines = [];
        $lines[] = '📊 *Pesquisa de Qualidade — Mayer Albanez Advogados*';
        $lines[] = '';
        $lines[] = 'Olá! Gostaríamos de saber sua opinião sobre o atendimento do nosso escritório.';
        $lines[] = '';
        $lines[] = "Seu código de pesquisa: *{$tokenShort}*";
        $lines[] = '';

        $stepNum = 1;
        foreach ($questions as $q) {
            $key = $q['key'] ?? '';
            $text = $q['text'] ?? '';
            $min = $q['min'] ?? 0;
            $max = $q['max'] ?? 10;

            $lines[] = "➡️ *Pergunta {$stepNum}:* {$text}";
            $lines[] = "Responda com um número de {$min} a {$max}.";
            $lines[] = '';
            $stepNum++;
        }

        $lines[] = '💬 Se desejar, envie também um comentário sobre o atendimento.';
        $lines[] = '';
        $lines[] = 'Para responder, envie uma mensagem no formato:';
        $lines[] = "*{$tokenShort} [nota1] [nota2] [comentário opcional]*";
        $lines[] = '';
        $lines[] = "Exemplo: *{$tokenShort} 5 9 Excelente atendimento!*";
        $lines[] = '';
        $lines[] = 'Para não receber mais pesquisas, responda: *SAIR*';
        $lines[] = '';
        $lines[] = '_Suas respostas são confidenciais._';

        return implode("\n", $lines);
    }
}
