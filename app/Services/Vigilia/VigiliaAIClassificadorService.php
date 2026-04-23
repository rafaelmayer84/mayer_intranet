<?php

namespace App\Services\Vigilia;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Machine C — classifica andamentos do tribunal via Claude Haiku.
 *
 * Cada andamento recebe um tipo_ai que permite lógica estruturada
 * em cima de texto livre dos tribunais (eproc, TRT12, TRF4, STJ).
 *
 * Categorias:
 *   SENTENÇA               — sentença de mérito (procedente/improcedente/parcial)
 *   ACÓRDÃO                — decisão colegiada de tribunal
 *   DECISÃO_SIGNIFICATIVA  — decisão interlocutória que exige resposta do escritório
 *   RECURSO_ESCRITÓRIO     — petição de recurso protocolada pelo escritório
 *   PETIÇÃO_ESCRITÓRIO     — qualquer petição/manifestação protocolada pelo escritório
 *   AUDIÊNCIA              — audiência realizada ou designada
 *   DESPACHO_ROTINA        — despacho de mero expediente, publicação, certidão
 *   OUTRO                  — não classificado nas categorias acima
 */
class VigiliaAIClassificadorService
{
    private string $apiKey;
    private string $model = 'claude-haiku-4-5-20251001';
    private string $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key', env('JUSTUS_ANTHROPIC_API_KEY'));
    }

    /**
     * Classifica um lote de andamentos.
     *
     * @param  array  $andamentos  [ ['id' => int, 'descricao' => string], ... ]
     * @return array               [ andamento_id => ['tipo_ai' => string, 'confianca' => float, 'motivo' => string] ]
     */
    public function classificarLote(array $andamentos): array
    {
        if (empty($andamentos)) {
            return [];
        }

        $lista = collect($andamentos)->map(fn($a, $i) =>
            ($i + 1) . '. [ID:' . $a['id'] . '] ' . mb_substr($a['descricao'], 0, 200)
        )->implode("\n");

        $prompt = <<<PROMPT
Você é um assistente especialista em análise de movimentos processuais brasileiros (eproc, TRT, TRF, STJ, TJSC).

Classifique cada movimento abaixo em exatamente uma categoria:

SENTENÇA               — sentença de mérito (procedente, improcedente, parcial, homologatória)
ACÓRDÃO                — decisão colegiada de tribunal de segunda instância ou superior
DECISÃO_SIGNIFICATIVA  — decisão interlocutória que impõe prazo ou obrigação ao escritório (tutela, execução, multa, penhora, bloqueio)
RECURSO_ESCRITÓRIO     — recurso protocolado pelo próprio escritório (apelação, embargos, agravo, RR, REsp)
PETIÇÃO_ESCRITÓRIO     — petição, contestação, manifestação ou protocolo do escritório
AUDIÊNCIA              — audiência realizada, designada ou cancelada
DESPACHO_ROTINA        — despacho de expediente, publicação de intimação, certidão, juntada administrativa, conclusos, remessa
OUTRO                  — não se enquadra nas categorias acima

Movimentos para classificar:
{$lista}

Responda APENAS com JSON válido neste formato exato (sem markdown, sem texto adicional):
{
  "classificacoes": [
    {"id": <ID do movimento>, "tipo_ai": "<CATEGORIA>", "confianca": <0.0 a 1.0>, "motivo": "<razão em até 15 palavras>"},
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
                'max_tokens' => 4096,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('[VIGÍLIA AI] Classificador HTTP error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return [];
            }

            $content = $response->json('content.0.text', '');

            // Strip markdown code fences if present
            $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
            $clean = preg_replace('/\s*```$/', '', $clean);

            $decoded = json_decode($clean, true);

            // Fallback: extract first {...} block from response
            if (!isset($decoded['classificacoes'])) {
                if (preg_match('/\{.*\}/s', $clean, $m)) {
                    $decoded = json_decode($m[0], true);
                }
            }

            if (!isset($decoded['classificacoes'])) {
                Log::error('[VIGÍLIA AI] Resposta sem classificacoes', ['content' => $content]);
                return [];
            }

            $resultado = [];
            foreach ($decoded['classificacoes'] as $item) {
                $resultado[$item['id']] = [
                    'tipo_ai'   => $this->validarTipo($item['tipo_ai'] ?? 'OUTRO'),
                    'confianca' => (float) ($item['confianca'] ?? 0.5),
                    'motivo'    => $item['motivo'] ?? '',
                ];
            }

            return $resultado;

        } catch (\Throwable $e) {
            Log::error('[VIGÍLIA AI] Exceção no classificador', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function validarTipo(string $tipo): string
    {
        $validos = [
            'SENTENÇA', 'ACÓRDÃO', 'DECISÃO_SIGNIFICATIVA',
            'RECURSO_ESCRITÓRIO', 'PETIÇÃO_ESCRITÓRIO',
            'AUDIÊNCIA', 'DESPACHO_ROTINA', 'OUTRO',
        ];

        return in_array($tipo, $validos) ? $tipo : 'OUTRO';
    }

    /**
     * Tipos que exigem obrigação do advogado.
     */
    public static function tiposQueGeram0brigacao(): array
    {
        return ['SENTENÇA', 'ACÓRDÃO', 'DECISÃO_SIGNIFICATIVA'];
    }
}
