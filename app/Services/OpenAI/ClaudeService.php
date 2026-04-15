<?php

namespace App\Services\OpenAI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ClaudeService
 * Substitui OpenAIService para todos os serviços de autoatendimento Nexo.
 * Usa a API Anthropic (claude-sonnet-4-6).
 */
class ClaudeService
{
    private string $apiKey;
    private string $model;
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key');
        $this->model  = config('services.anthropic.model', 'claude-sonnet-4-6');
    }

    // ── Helpers internos ────────────────────────────────────────────────────

    /**
     * Chama a API Claude e retorna o texto da resposta, ou null em caso de erro.
     */
    private function chamar(string $system, string $userContent, int $maxTokens = 300, float $temperature = 0.5): ?string
    {
        try {
            $inicio = microtime(true);

            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type'      => 'application/json',
            ])->timeout(30)->post(self::API_URL, [
                'model'      => $this->model,
                'max_tokens' => $maxTokens,
                'system'     => $system,
                'messages'   => [
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

            $tempoMs = (int)((microtime(true) - $inicio) * 1000);

            if ($response->successful()) {
                $texto = $response->json('content.0.text', '');
                Log::info('ClaudeService: resposta gerada', [
                    'model'    => $this->model,
                    'tempo_ms' => $tempoMs,
                    'tokens'   => $response->json('usage.output_tokens', 0),
                ]);
                return trim($texto) ?: null;
            }

            Log::error('ClaudeService: erro na API', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ClaudeService: exceção', ['erro' => $e->getMessage()]);
        }

        return null;
    }

    // ── Métodos públicos (mesma interface do antigo OpenAIService) ──────────

    /**
     * Gera resumo de status do processo para o flow Nexo (NexoAutomationService).
     */
    public function gerarRespostaStatusProcesso(array $andamentos, string $numeroProcesso, string $nomeCliente): string
    {
        $andamentosTexto = '';
        foreach ($andamentos as $i => $a) {
            $andamentosTexto .= sprintf("%d. %s — %s\n", $i + 1, $a['data'] ?? 'Sem data', $a['descricao'] ?? 'Sem descrição');
        }

        $system = 'Você é secretária do escritório Mayer Advogados. Explique andamentos processuais em linguagem simples e acolhedora para clientes leigos. Sem markdown. Sem listas numeradas. No máximo 3 frases curtas (mensagem WhatsApp). NUNCA faça promessas ou exponha dados pessoais de terceiros.';

        $user = "O cliente {$nomeCliente} quer saber sobre o processo {$numeroProcesso}.\n\nÚltimos andamentos:\n{$andamentosTexto}\n\nInstruções:\n- Cumprimente pelo primeiro nome\n- Explique o que aconteceu em linguagem leiga\n- Máximo 3 frases\n- Não use asteriscos, números ou listas\n- Finalize com: 'Posso ajudar com mais alguma dúvida?'";

        $texto = $this->chamar($system, $user, 200, 0.5);

        return $texto ?? $this->fallbackStatus($andamentos, $numeroProcesso, $nomeCliente);
    }

    /**
     * Gera resumo leigo do processo para o autoatendimento (NexoAutoatendimentoService::resumoLeigo).
     */
    public function gerarResumoLeigo(array $andamentos, string $numeroProcesso, string $nomeCliente): string
    {
        $andamentosTexto = '';
        foreach ($andamentos as $i => $a) {
            $andamentosTexto .= sprintf("%d. %s — %s\n", $i + 1, $a['data'] ?? 'Sem data', $a['descricao'] ?? 'Sem descrição');
        }

        $system = 'Você é secretária do escritório Mayer Advogados. Resuma andamentos para clientes leigos em mensagens curtas de WhatsApp. Sem markdown. Sem listas. No máximo 3 frases. NUNCA faça promessas ou exponha dados pessoais de terceiros.';

        $user = "O cliente {$nomeCliente} quer um resumo do processo {$numeroProcesso}.\n\nÚltimos andamentos:\n{$andamentosTexto}\n\nRegras OBRIGATÓRIAS:\n- Máximo 3 frases curtas\n- Cumprimente pelo primeiro nome\n- Linguagem totalmente leiga, sem juridiquês\n- NUNCA use listas, asteriscos ou markdown\n- NUNCA prometa resultado, sugira estratégia ou mencione valores\n- NUNCA exponha dados pessoais de terceiros\n- Termine com: 'Toque no link abaixo para ver todos os detalhes.'";

        $texto = $this->chamar($system, $user, 180, 0.5);

        return $texto ?? $this->fallbackResumo($andamentos, $nomeCliente);
    }

    /**
     * Resume o contexto de mensagens recentes para abertura de ticket (resumirContexto).
     */
    public function resumirContexto(string $contextoTexto): string
    {
        $system = 'Você é assistente de um escritório de advocacia. Resuma em NO MÁXIMO 1 frase curta (até 150 caracteres) qual é o assunto/problema do cliente baseado nas mensagens. Seja direto. Use terceira pessoa. Exemplo: "Dúvida sobre andamento do processo trabalhista contra empresa X".';

        $user = "Mensagens recentes do cliente:\n{$contextoTexto}";

        $texto = $this->chamar($system, $user, 80, 0.3);

        if ($texto) {
            return mb_substr(trim($texto, '"\''), 0, 200);
        }

        return mb_substr($contextoTexto, 0, 200);
    }

    // ── Fallbacks ───────────────────────────────────────────────────────────

    private function fallbackStatus(array $andamentos, string $numeroProcesso, string $nomeCliente): string
    {
        $ultimo = $andamentos[0] ?? null;
        if (!$ultimo) {
            return "Olá {$nomeCliente}! Consultei seu processo {$numeroProcesso}, mas não há andamentos recentes. Nossa equipe está à disposição.";
        }
        return "Olá {$nomeCliente}! A última movimentação do seu processo foi em {$ultimo['data']}. Posso ajudar com mais alguma dúvida?";
    }

    private function fallbackResumo(array $andamentos, string $nomeCliente): string
    {
        $ultimo = $andamentos[0] ?? null;
        if (!$ultimo) {
            return "Olá {$nomeCliente}! Seu processo está ativo mas sem movimentações recentes. Toque no link abaixo para ver os detalhes.";
        }
        return "Olá {$nomeCliente}! A última movimentação do seu processo foi em {$ultimo['data']}. Toque no link abaixo para ver os detalhes completos.";
    }
}
