<?php

namespace App\Services\BscInsights\V2;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BscOpenAiClient
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey   = config('bsc_insights.openai_api_key');
        $this->model    = config('bsc_insights.openai_model', 'gpt-4.1-mini');
        $this->maxTokens = config('bsc_insights.openai_max_tokens', 16000);
        $this->timeout  = config('bsc_insights.openai_timeout', 120);
    }

    public function call(string $systemPrompt, string $userPrompt, array $jsonSchema): array
    {
        $startMs = (int)(microtime(true) * 1000);

        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'max_completion_tokens' => $this->maxTokens,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'bsc_insights_v2',
                    'strict' => true,
                    'schema' => $jsonSchema,
                ],
            ],
        ];

        $response = Http::timeout($this->timeout)
            ->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey, 'Content-Type' => 'application/json'])
            ->post('https://api.openai.com/v1/chat/completions', $body);

        $durationMs = (int)(microtime(true) * 1000) - $startMs;

        if (!$response->successful()) {
            $err = $response->body();
            Log::error('BscOpenAiClient: HTTP error', ['status' => $response->status(), 'body' => substr($err, 0, 500)]);
            throw new \RuntimeException('OpenAI HTTP ' . $response->status() . ': ' . substr($err, 0, 300));
        }

        $data = $response->json();
        $usage = $data['usage'] ?? [];
        $inputTokens  = $usage['prompt_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? 0;
        $reasoningTokens = $usage['completion_tokens_details']['reasoning_tokens'] ?? 0;

        if ($reasoningTokens > 0 && $reasoningTokens >= $outputTokens * 0.9) {
            Log::warning('BscOpenAiClient: >90% tokens em reasoning', ['reasoning' => $reasoningTokens, 'total' => $outputTokens, 'model' => $this->model]);
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        if (empty(trim($content))) {
            throw new \RuntimeException("OpenAI content vazio (reasoning={$reasoningTokens}, output={$outputTokens}). Modelo '{$this->model}' gastando budget em raciocinio.");
        }

        $costIn  = config('bsc_insights.cost_per_1m_input_tokens', 10.00);
        $costOut = config('bsc_insights.cost_per_1m_output_tokens', 30.00);
        $cost = round(($inputTokens / 1_000_000) * $costIn + ($outputTokens / 1_000_000) * $costOut, 5);

        Log::info('BscOpenAiClient: OK', ['model'=>$this->model,'in'=>$inputTokens,'out'=>$outputTokens,'reasoning'=>$reasoningTokens,'cost'=>$cost,'ms'=>$durationMs]);

        return ['content'=>$content,'input_tokens'=>$inputTokens,'output_tokens'=>$outputTokens,'cost_usd'=>$cost,'model'=>$this->model,'duration_ms'=>$durationMs];
    }
}
