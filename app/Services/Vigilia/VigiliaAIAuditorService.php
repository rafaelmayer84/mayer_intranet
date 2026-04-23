<?php

namespace App\Services\Vigilia;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Machine B — usa Claude Sonnet para auditar atividades marcadas como 'suspeito'
 * no vigilia_cruzamentos e emitir veredicto fundamentado.
 *
 * Veredictos possíveis:
 *   VERIFICADO   — evidência encontrada nos andamentos, falso alarme
 *   SUSPEITO     — sem evidência; advogado deve ser cobrado
 *   INCONCLUSIVO — contexto insuficiente para julgar
 */
class VigiliaAIAuditorService
{
    private string $apiKey;
    private string $model = 'claude-sonnet-4-6';
    private string $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key', env('JUSTUS_ANTHROPIC_API_KEY'));
    }

    /**
     * @param  array  $casos  [ ['cruzamento_id' => int, 'atividade' => obj, 'andamentos' => array], ... ]
     * @return array          [ cruzamento_id => ['verdict' => string, 'justificativa' => string] ]
     */
    public function auditarLote(array $casos): array
    {
        if (empty($casos)) {
            return [];
        }

        $lista = collect($casos)->map(function ($caso, $i) {
            $a = $caso['atividade'];
            $andamentosTexto = collect($caso['andamentos'])->map(fn($and) =>
                "  • [{$and->data_andamento}] {$and->descricao}"
            )->implode("\n");

            return ($i + 1) . ". [CID:{$caso['cruzamento_id']}]\n" .
                "   Atividade: {$a->assunto} ({$a->tipo_atividade}) — Concluída em " . ($a->data_conclusao ?? $a->data_hora) . "\n" .
                "   Processo: {$a->processo_pasta}\n" .
                "   Andamentos do escritório nos últimos 60 dias:\n" .
                ($andamentosTexto ?: "  (nenhum andamento do escritório encontrado)");
        })->implode("\n\n");

        $prompt = <<<PROMPT
Você é um auditor de compliance de escritório de advocacia brasileiro.

Sua tarefa: para cada atividade marcada como SUSPEITA (advogado marcou Concluído mas sistema não encontrou andamento compatível no tribunal), analise os andamentos disponíveis e emita um veredicto.

Regras de julgamento:
- VERIFICADO: há andamento do escritório no processo (petição, recurso, manifestação, etc.) próximo à conclusão — provavelmente ação correta
- SUSPEITO: não há evidência de ação do escritório nos andamentos — necessário cobrar o advogado
- INCONCLUSIVO: andamentos existem mas são de outra parte, ou contexto insuficiente

Casos para auditar:
{$lista}

Responda APENAS com JSON válido (sem markdown):
{
  "auditorias": [
    {"cruzamento_id": <ID>, "verdict": "<VERIFICADO|SUSPEITO|INCONCLUSIVO>", "justificativa": "<razão em até 20 palavras>"},
    ...
  ]
}
PROMPT;

        try {
            $response = Http::timeout(90)->withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->post($this->apiUrl, [
                'model'      => $this->model,
                'max_tokens' => 2048,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('[VIGÍLIA AI] Auditor HTTP error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return [];
            }

            $content = $response->json('content.0.text', '');
            $clean   = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
            $clean   = preg_replace('/\s*```$/', '', $clean);
            $decoded = json_decode($clean, true);

            if (!isset($decoded['auditorias'])) {
                if (preg_match('/\{.*\}/s', $clean, $m)) {
                    $decoded = json_decode($m[0], true);
                }
            }

            if (!isset($decoded['auditorias'])) {
                Log::error('[VIGÍLIA AI] Auditor resposta sem auditorias', ['content' => $content]);
                return [];
            }

            $resultado = [];
            foreach ($decoded['auditorias'] as $item) {
                $resultado[$item['cruzamento_id']] = [
                    'verdict'       => $this->validarVerdict($item['verdict'] ?? 'INCONCLUSIVO'),
                    'justificativa' => $item['justificativa'] ?? '',
                ];
            }

            return $resultado;

        } catch (\Throwable $e) {
            Log::error('[VIGÍLIA AI] Exceção no auditor', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function validarVerdict(string $v): string
    {
        return in_array($v, ['VERIFICADO', 'SUSPEITO', 'INCONCLUSIVO']) ? $v : 'INCONCLUSIVO';
    }
}
