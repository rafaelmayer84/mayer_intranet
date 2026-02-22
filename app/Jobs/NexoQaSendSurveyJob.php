<?php

namespace App\Jobs;

use App\Models\NexoQaCampaign;
use App\Models\NexoQaSampledTarget;
use App\Services\SendPulseWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NexoQaSendSurveyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(
        private int $targetId
    ) {}

    public function handle(SendPulseWhatsAppService $sendPulse): void
    {
        $target = NexoQaSampledTarget::find($this->targetId);

        if (!$target || $target->send_status !== 'PENDING') {
            Log::info('[NexoQA Send] Target ignorado', [
                'target_id' => $this->targetId,
                'motivo' => !$target ? 'nao_encontrado' : 'status_' . $target->send_status,
            ]);
            return;
        }

        // Nome do contato para personalizar template
        $nomeContato = $target->contact_name ?? 'Cliente';

        try {
            $result = $sendPulse->sendTemplate(
                $target->phone_e164,
                'pesquisaqualidade',
                'pt_BR',
                [$nomeContato]
            );

            $messageId = $result['id'] ?? $result['message_id'] ?? null;

            $target->update([
                'send_status' => 'SENT',
                'sendpulse_message_id' => $messageId,
            ]);

            Log::info('[NexoQA Send] Template enviado', [
                'target_id' => $target->id,
                'phone_suffix' => substr($target->phone_e164, -4),
                'message_id' => $messageId,
            ]);

        } catch (\Exception $e) {
            $target->update([
                'send_status' => 'FAILED',
                'skip_reason' => substr($e->getMessage(), 0, 255),
            ]);

            Log::error('[NexoQA Send] Falha no envio', [
                'target_id' => $target->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
