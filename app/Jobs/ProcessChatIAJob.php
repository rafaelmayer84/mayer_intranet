<?php

namespace App\Jobs;

use App\Models\Cliente;
use App\Services\Nexo\NexoAutoatendimentoService;
use App\Services\SendPulseWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessChatIAJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 55;

    private string $telefone;
    private string $pergunta;
    private ?string $processoPasta;

    public function __construct(string $telefone, string $pergunta, ?string $processoPasta = null)
    {
        $this->telefone = $telefone;
        $this->pergunta = $pergunta;
        $this->processoPasta = $processoPasta;
        $this->onQueue('chatia');
    }

    public function handle(NexoAutoatendimentoService $service, SendPulseWhatsAppService $sendPulse): void
    {
        $inicio = microtime(true);

        try {
            $resultado = $service->chatIA($this->telefone, $this->pergunta, $this->processoPasta);

            // Se retornou erro (sessão expirada, bloqueio, etc), usar mensagem de erro
            if (isset($resultado['erro'])) {
                $resposta = $resultado['erro'];
                Log::warning('ChatIA Job: erro do service', ['telefone' => $this->telefone, 'erro' => $resposta]);
            } else {
                $resposta = $resultado['resposta'] ?? 'Desculpe, não consegui processar sua pergunta no momento. Por favor, tente novamente ou digite *menu* para voltar às opções. Se preferir, escolha 👤 Falar com equipe.';
            }

            $sendResult = $sendPulse->sendMessageByPhone($this->telefone, $resposta);

            $tempoMs = (int)((microtime(true) - $inicio) * 1000);
            Log::info('ChatIA Job concluido', [
                'telefone' => $this->telefone,
                'tempo_ms' => $tempoMs,
                'send_ok' => $sendResult['success'] ?? false,
            ]);
        } catch (\Throwable $e) {
            Log::error('ChatIA Job falhou', [
                'telefone' => $this->telefone,
                'erro' => $e->getMessage(),
            ]);

            try {
                $sendPulse = app(SendPulseWhatsAppService::class);
                $sendPulse->sendMessageByPhone(
                    $this->telefone,
                    'Desculpe, não consegui processar sua pergunta neste momento. Por favor, tente novamente ou fale com nossa equipe.'
                );
            } catch (\Throwable $e2) {
                Log::error('ChatIA Job falhou ao enviar fallback', ['erro' => $e2->getMessage()]);
            }
        }
    }
}
