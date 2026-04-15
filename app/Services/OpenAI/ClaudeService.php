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
            $andamentosTexto .= sprintf("- %s: %s\n", $a['data'] ?? 'Sem data', $a['descricao'] ?? 'Sem descrição');
        }

        $system = <<<SYSTEM
Você é secretária do escritório Mayer Advogados respondendo via WhatsApp.

FORMATO OBRIGATÓRIO:
- Texto corrido, sem listas, sem numeração, sem asteriscos, sem markdown de qualquer tipo
- Máximo 2 frases curtas
- Tom tranquilizador: o escritório está acompanhando, tudo sob controle

PROIBIÇÕES ABSOLUTAS — se violar qualquer uma, a resposta será descartada:
- NUNCA mencione prazos vencidos, encerrados ou que já passaram
- NUNCA diga que o processo "pode levar tempo", "depende de" ou "é importante aguardar"
- NUNCA crie expectativa de demora ou incerteza
- NUNCA use "SITUAÇÃO ATUAL", "SIGNIFICADO PRÁTICO", "PRÓXIMOS PASSOS" ou qualquer subtítulo
- NUNCA exponha dados pessoais de terceiros (CPF, endereço, dados bancários)
- NUNCA faça promessas de resultado
SYSTEM;

        $primeiro = explode(' ', trim($nomeCliente))[0];
        $user = "Resuma a última movimentação do processo {$numeroProcesso} para o cliente {$primeiro}.\n\nMovimentações recentes:\n{$andamentosTexto}\nEscreva no máximo 2 frases. Não use listas nem marcadores. Tom tranquilizador. Finalize com: 'Qualquer dúvida, nossa equipe está à disposição.'";

        $texto = $this->chamar($system, $user, 150, 0.4);

        return $texto ?? $this->fallbackStatus($andamentos, $numeroProcesso, $nomeCliente);
    }

    /**
     * Gera resumo leigo do processo para o autoatendimento (NexoAutoatendimentoService::resumoLeigo).
     */
    public function gerarResumoLeigo(array $andamentos, string $numeroProcesso, string $nomeCliente): string
    {
        $andamentosTexto = '';
        foreach ($andamentos as $i => $a) {
            $andamentosTexto .= sprintf("- %s: %s\n", $a['data'] ?? 'Sem data', $a['descricao'] ?? 'Sem descrição');
        }

        $system = <<<SYSTEM
Você é secretária do escritório Mayer Advogados respondendo via WhatsApp.

FORMATO OBRIGATÓRIO:
- Texto corrido, sem listas, sem numeração, sem asteriscos, sem markdown de qualquer tipo
- Máximo 2 frases curtas
- Tom tranquilizador: o escritório está acompanhando, tudo caminhando

PROIBIÇÕES ABSOLUTAS — se violar qualquer uma, a resposta será descartada:
- NUNCA mencione prazos vencidos, encerrados ou que já passaram
- NUNCA diga que o processo "pode levar tempo", "depende de" ou "é importante aguardar"
- NUNCA crie expectativa de demora, incerteza ou preocupação
- NUNCA use "SITUAÇÃO ATUAL", "SIGNIFICADO PRÁTICO", "PRÓXIMOS PASSOS" ou qualquer subtítulo
- NUNCA use asteriscos, hífens como marcadores, numeração ou qualquer formatação
- NUNCA exponha dados pessoais de terceiros
- NUNCA faça promessas de resultado ou sugira estratégia
SYSTEM;

        $primeiro = explode(' ', trim($nomeCliente))[0];
        $user = "Resuma a última movimentação do processo {$numeroProcesso} para o cliente {$primeiro}.\n\nMovimentações recentes:\n{$andamentosTexto}\nEscreva no máximo 2 frases em texto corrido. Tom tranquilizador. Termine com: 'Toque no link abaixo para ver todos os detalhes.'";

        $texto = $this->chamar($system, $user, 150, 0.4);

        return $texto ?? $this->fallbackResumo($andamentos, $nomeCliente);
    }

    /**
     * Enriquece cada andamento com uma explicação leiga curta.
     * Retorna o array original com campo `explicacao` adicionado em cada item.
     */
    public function explicarAndamentos(array $andamentos): array
    {
        if (empty($andamentos)) return $andamentos;

        $lista = '';
        foreach ($andamentos as $i => $a) {
            $lista .= sprintf("%d. %s\n", $i + 1, $a['descricao'] ?? '');
        }

        $system = 'Você é secretária jurídica do escritório Mayer Advogados. Explique cada andamento processual em linguagem completamente leiga, como se estivesse falando com alguém que nunca entrou em um tribunal. Seja simples, direto e tranquilizador.';

        $user = "Para cada andamento abaixo, escreva UMA frase curta (máximo 18 palavras) explicando o que aconteceu em linguagem leiga. Retorne SOMENTE um JSON válido no formato: [{\"explicacao\":\"...\"},...]\n\nAndamentos:\n{$lista}";

        $texto = $this->chamar($system, $user, 400, 0.3);

        if (!$texto) return $andamentos;

        // Extrair JSON da resposta (Claude pode adicionar texto antes/depois)
        if (preg_match('/\[.*\]/s', $texto, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                foreach ($andamentos as $i => &$and) {
                    $and['explicacao'] = $decoded[$i]['explicacao'] ?? '';
                }
                unset($and);
            }
        }

        return $andamentos;
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
