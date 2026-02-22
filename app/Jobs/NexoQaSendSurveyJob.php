<?php

namespace App\Jobs;

use App\Models\NexoQaSampledTarget;
use App\Services\SendPulseWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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

        // Resolver nome do contato
        $nomeContato = $this->resolveContactName($target);

        try {
            $result = $sendPulse->sendTemplateByPhone(
                $target->phone_e164,
                [
                    'name' => 'pesquisaqualidade',
                    'language' => ['code' => 'pt_BR'],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $nomeContato],
                            ],
                        ],
                    ],
                ]
            );

            $messageId = $result['id'] ?? $result['message_id'] ?? null;

            $target->update([
                'send_status' => 'SENT',
                'sendpulse_message_id' => $messageId,
            ]);

            Log::info('[NexoQA Send] Template enviado', [
                'target_id' => $target->id,
                'phone_suffix' => substr($target->phone_e164, -4),
                'nome' => $nomeContato,
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

    /**
     * Resolve nome do contato por source_type:
     * CRM_EVENT/CRM_ACTIVITY → crm_accounts via crm_identities (phone)
     * NEXO → wa_conversations.name
     * Fallback → primeiro nome de crm_accounts pelo telefone
     */
    private function resolveContactName(NexoQaSampledTarget $target): string
    {
        // 1. Tentar via wa_conversations (NEXO)
        $waName = DB::table('wa_conversations')
            ->where('phone', $target->phone_e164)
            ->value('name');

        if ($waName && $waName !== '') {
            return $this->formatFirstName($waName);
        }

        // 2. Tentar via crm_accounts (CRM)
        $crmName = DB::table('crm_identities')
            ->join('crm_accounts', 'crm_accounts.id', '=', 'crm_identities.account_id')
            ->where('crm_identities.kind', 'phone')
            ->where('crm_identities.value', $target->phone_e164)
            ->value('crm_accounts.name');

        if ($crmName && $crmName !== '') {
            return $this->formatFirstName($crmName);
        }

        // 3. Tentar via clientes DataJuri
        $clienteName = DB::table('clientes')
            ->where(function ($q) use ($target) {
                $q->where('celular', 'LIKE', '%' . substr($target->phone_e164, -8) . '%')
                  ->orWhere('telefone', 'LIKE', '%' . substr($target->phone_e164, -8) . '%');
            })
            ->value('nome');

        if ($clienteName && $clienteName !== '') {
            return $this->formatFirstName($clienteName);
        }

        return 'Cliente';
    }

    /**
     * Retorna primeiro nome capitalizado.
     */
    private function formatFirstName(string $fullName): string
    {
        $first = explode(' ', trim($fullName))[0];
        return mb_convert_case(mb_strtolower($first), MB_CASE_TITLE, 'UTF-8');
    }
}
