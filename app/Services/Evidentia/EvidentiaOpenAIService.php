<?php

namespace App\Services\Evidentia;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\EvidentiaSearch;

class EvidentiaOpenAIService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key', env('OPENAI_API_KEY', ''));
    }

    /**
     * Verifica se o budget diário foi excedido.
     */
    public function isBudgetExceeded(): bool
    {
        $today = now()->toDateString();
        $spent = (float) Cache::get("evidentia_budget_{$today}", 0);
        return $spent >= config('evidentia.daily_budget_usd', 5.00);
    }

    /**
     * Registra custo no budget diário.
     */
    public function trackCost(float $cost): void
    {
        $today = now()->toDateString();
        $key = "evidentia_budget_{$today}";
        $current = (float) Cache::get($key, 0);
        Cache::put($key, $current + $cost, now()->endOfDay());
    }

    /**
     * Query Understanding: extrai termos, sinônimos e filtros sugeridos.
     */
    public function queryUnderstanding(string $userQuery): array
    {
        $model = config('evidentia.openai_model_query');

        $systemPrompt = <<<'PROMPT'
Você é um assistente especializado em busca de jurisprudência brasileira.
Dada uma consulta do usuário, extraia:
1. "termos": array de até 12 termos substanciais para busca fulltext (sem stopwords processuais genéricas como "processo", "recurso", "decisão")
2. "expansoes": array de até 12 sinônimos ou termos juridicamente equivalentes
3. "filtros": objeto com campos opcionais: "tribunal" (sigla), "classe" (sigla), "periodo_inicio" (YYYY-MM-DD), "periodo_fim" (YYYY-MM-DD), "area_direito", "orgao_julgador", "relator" — apenas se o usuário explicitamente mencionou. NÃO invente filtros.

Responda SOMENTE com JSON válido, sem markdown, sem explicação.
Exemplo de resposta:
{"termos":["dano moral","consumidor","banco"],"expansoes":["dano extrapatrimonial","instituição financeira","CDC"],"filtros":{"tribunal":"TJSC","area_direito":"cível"}}
PROMPT;

        $response = $this->chat($model, $systemPrompt, $userQuery, 0.1);

        if (!$response['success']) {
            Log::warning('Evidentia: query understanding falhou', ['error' => $response['error']]);
            return $this->fallbackQueryUnderstanding($userQuery);
        }

        $parsed = json_decode($response['content'], true);
        if (!$parsed || !isset($parsed['termos'])) {
            Log::warning('Evidentia: query understanding retornou JSON inválido');
            return $this->fallbackQueryUnderstanding($userQuery);
        }

        $parsed['_tokens_in'] = $response['tokens_in'];
        $parsed['_tokens_out'] = $response['tokens_out'];
        $parsed['_model'] = $model;

        return $parsed;
    }

    /**
     * Fallback: extrai termos simples sem IA.
     */
    private function fallbackQueryUnderstanding(string $query): array
    {
        $stopwords = ['de','do','da','dos','das','o','a','os','as','em','no','na','nos','nas',
            'por','para','com','sem','que','se','um','uma','uns','umas','ao','aos',
            'processo','recurso','decisão','julgamento','tribunal','acórdão','sentença',
            'apelação','agravo','embargos','ação','autor','réu','requerente','requerido'];

        $words = preg_split('/[\s,;.]+/', mb_strtolower($query));
        $terms = array_values(array_filter($words, function ($w) use ($stopwords) {
            return mb_strlen($w) > 2 && !in_array($w, $stopwords);
        }));

        return [
            'termos'    => array_slice($terms, 0, 12),
            'expansoes' => [],
            'filtros'   => [],
            '_tokens_in'  => 0,
            '_tokens_out' => 0,
            '_model'      => 'fallback',
        ];
    }

    /**
     * Rerank: recebe candidatos e retorna ranking com scores.
     */
    public function rerank(string $originalQuery, array $candidates): array
    {
        $model = config('evidentia.openai_model_rerank');

        $candidateText = '';
        foreach ($candidates as $i => $c) {
            $ementa = mb_substr($c['ementa'] ?? '', 0, 800);
            $chunk = mb_substr($c['best_chunk'] ?? '', 0, 600);
            $candidateText .= "---\nID: {$c['id']}\nTribunal: {$c['tribunal']}\nClasse: {$c['sigla_classe']}\nRelator: {$c['relator']}\nData: {$c['data_decisao']}\nEmenta (trecho): {$ementa}\nTrecho relevante: {$chunk}\n";
        }

        $systemPrompt = <<<'PROMPT'
Você é um especialista em ranqueamento de jurisprudência brasileira.
Dada uma consulta de busca e uma lista de candidatos, ordene-os por relevância.
Para cada candidato, atribua um score de 0.0 a 1.0 e uma justificativa de NO MÁXIMO 1 frase.

Responda SOMENTE com JSON válido:
{"ranking":[{"id":123,"score":0.95,"justificativa":"Trata diretamente do tema X"},{"id":456,"score":0.7,"justificativa":"Relacionado mas sobre Y"}]}

Ordene do mais relevante ao menos relevante. Avalie a pertinência temática, jurisdicional e temporal.
PROMPT;

        $userMessage = "Consulta do usuário: \"{$originalQuery}\"\n\nCandidatos:\n{$candidateText}";

        $response = $this->chat($model, $systemPrompt, $userMessage, 0.1);

        if (!$response['success']) {
            Log::warning('Evidentia: rerank falhou', ['error' => $response['error']]);
            return ['ranking' => [], '_tokens_in' => 0, '_tokens_out' => 0, '_model' => $model];
        }

        $parsed = json_decode($response['content'], true);
        if (!$parsed || !isset($parsed['ranking'])) {
            Log::warning('Evidentia: rerank retornou JSON inválido');
            return ['ranking' => [], '_tokens_in' => 0, '_tokens_out' => 0, '_model' => $model];
        }

        $parsed['_tokens_in'] = $response['tokens_in'];
        $parsed['_tokens_out'] = $response['tokens_out'];
        $parsed['_model'] = $model;

        return $parsed;
    }

    /**
     * Gera bloco de citação para petição.
     */
    public function generateCitationBlock(string $originalQuery, array $topResults): array
    {
        $model = config('evidentia.openai_model_writer');

        $context = '';
        foreach ($topResults as $r) {
            $ementa = mb_substr($r['ementa'] ?? '', 0, 1500);
            $context .= "---\n";
            $context .= "Tribunal: {$r['tribunal']}\n";
            $context .= "Classe: {$r['sigla_classe']} ({$r['descricao_classe']})\n";
            $context .= "Processo: {$r['numero_processo']}\n";
            $context .= "Relator: {$r['relator']}\n";
            $context .= "Órgão Julgador: {$r['orgao_julgador']}\n";
            $context .= "Data Julgamento: {$r['data_decisao']}\n";
            $context .= "Ementa:\n{$ementa}\n";
            if (!empty($r['best_chunk'])) {
                $context .= "Trecho relevante:\n" . mb_substr($r['best_chunk'], 0, 800) . "\n";
            }
        }

        $systemPrompt = <<<'PROMPT'
Você é um redator jurídico do escritório Mayer Advogados.
Com base EXCLUSIVAMENTE nos acórdãos fornecidos abaixo, redija:

1. "sintese_objetiva": Uma síntese argumentativa de até 12 linhas, em linguagem técnica de 3ª pessoa, adequada para inserção em petição judicial. Cite explicitamente o número do processo e tribunal de cada acórdão utilizado.

2. "bloco_precedentes": Um bloco formatado de precedentes no padrão:
TRIBUNAL, CLASSE NúmeroProcesso, Rel. Relator, Órgão Julgador, j. DD/MM/AAAA.
Ementa: [trecho mais relevante da ementa, parafraseado]

REGRAS INVIOLÁVEIS:
- NUNCA cite acórdão que não esteja na lista fornecida.
- NUNCA invente números de processo, relatores ou datas.
- Se algum campo estiver incompleto, omita-o em vez de inventar.

Responda SOMENTE com JSON válido:
{"sintese_objetiva":"...","bloco_precedentes":"..."}
PROMPT;

        $userMessage = "Consulta original: \"{$originalQuery}\"\n\nAcórdãos selecionados:\n{$context}";

        $response = $this->chat($model, $systemPrompt, $userMessage, 0.3);

        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error'],
                '_tokens_in' => 0,
                '_tokens_out' => 0,
            ];
        }

        $parsed = json_decode($response['content'], true);
        if (!$parsed || !isset($parsed['sintese_objetiva'])) {
            return [
                'success' => false,
                'error' => 'JSON inválido na resposta',
                '_tokens_in' => $response['tokens_in'],
                '_tokens_out' => $response['tokens_out'],
            ];
        }

        return [
            'success'             => true,
            'sintese_objetiva'    => $parsed['sintese_objetiva'],
            'bloco_precedentes'   => $parsed['bloco_precedentes'] ?? '',
            '_tokens_in'          => $response['tokens_in'],
            '_tokens_out'         => $response['tokens_out'],
            '_model'              => $model,
        ];
    }

    /**
     * Gera embedding para texto(s).
     */
    public function generateEmbeddings(array $texts): array
    {
        $model = config('evidentia.openai_embedding_model');

        if ($this->isBudgetExceeded()) {
            Log::warning('Evidentia: budget diário excedido para embeddings');
            return ['success' => false, 'error' => 'Budget diário excedido'];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(60)
                ->post("{$this->baseUrl}/embeddings", [
                    'model' => $model,
                    'input' => $texts,
                ]);

            if (!$response->successful()) {
                return ['success' => false, 'error' => $response->body()];
            }

            $data = $response->json();
            $embeddings = [];

            foreach ($data['data'] as $item) {
                $vector = $item['embedding'];
                $norm = $this->vectorNorm($vector);
                $embeddings[] = [
                    'vector' => $vector,
                    'norm'   => $norm,
                ];
            }

            $tokensUsed = $data['usage']['total_tokens'] ?? 0;
            $cost = ($tokensUsed / 1_000_000) * (config("evidentia.pricing.{$model}.input") ?? 0.02);
            $this->trackCost($cost);

            return [
                'success'    => true,
                'embeddings' => $embeddings,
                'tokens'     => $tokensUsed,
                'cost'       => $cost,
                'model'      => $model,
                'dims'       => config('evidentia.openai_embedding_dims'),
            ];
        } catch (\Exception $e) {
            Log::error('Evidentia: erro ao gerar embeddings', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Gera embedding para uma única query de busca.
     */
    public function generateQueryEmbedding(string $query): ?array
    {
        $result = $this->generateEmbeddings([$query]);

        if (!$result['success'] || empty($result['embeddings'])) {
            return null;
        }

        return $result['embeddings'][0];
    }

    /**
     * Chat completion genérico.
     */
    private function chat(string $model, string $system, string $user, float $temperature = 0.3): array
    {
        if ($this->isBudgetExceeded()) {
            return ['success' => false, 'error' => 'Budget diário excedido', 'tokens_in' => 0, 'tokens_out' => 0];
        }

        try {
            $payload = [
                'model'    => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
                'max_completion_tokens' => 4000,
            ];

            // gpt-5.2 suporta temperature
            if (str_contains($model, 'gpt-5') || str_contains($model, 'gpt-4.1')) {
                $payload['temperature'] = $temperature;
            }

            $response = Http::withToken($this->apiKey)
                ->timeout(90)
                ->post("{$this->baseUrl}/chat/completions", $payload);

            if (!$response->successful()) {
                return [
                    'success'    => false,
                    'error'      => $response->body(),
                    'tokens_in'  => 0,
                    'tokens_out' => 0,
                ];
            }

            $data = $response->json();
            $content   = $data['choices'][0]['message']['content'] ?? '';
            $tokensIn  = $data['usage']['prompt_tokens'] ?? 0;
            $tokensOut = $data['usage']['completion_tokens'] ?? 0;

            // Limpa possíveis fences de markdown
            $content = trim($content);
            if (str_starts_with($content, '```json')) {
                $content = trim(mb_substr($content, 7));
            }
            if (str_starts_with($content, '```')) {
                $content = trim(mb_substr($content, 3));
            }
            if (str_ends_with($content, '```')) {
                $content = trim(mb_substr($content, 0, -3));
            }

            $cost = ($tokensIn / 1_000_000) * (config("evidentia.pricing.{$model}.input") ?? 0)
                  + ($tokensOut / 1_000_000) * (config("evidentia.pricing.{$model}.output") ?? 0);
            $this->trackCost($cost);

            return [
                'success'    => true,
                'content'    => $content,
                'tokens_in'  => $tokensIn,
                'tokens_out' => $tokensOut,
                'cost'       => $cost,
            ];
        } catch (\Exception $e) {
            Log::error("Evidentia: OpenAI chat error [{$model}]", ['error' => $e->getMessage()]);
            return [
                'success'    => false,
                'error'      => $e->getMessage(),
                'tokens_in'  => 0,
                'tokens_out' => 0,
            ];
        }
    }

    /**
     * Calcula norma L2 de um vetor.
     */
    public function vectorNorm(array $vector): float
    {
        $sum = 0.0;
        foreach ($vector as $v) {
            $sum += $v * $v;
        }
        return sqrt($sum);
    }
}
