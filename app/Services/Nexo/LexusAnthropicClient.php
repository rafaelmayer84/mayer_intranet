<?php

namespace App\Services\Nexo;

use App\Exceptions\LexusAnthropicException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LexusAnthropicClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-4-5-20250929',
    ) {}

    public function messages(array $messages, string $systemPrompt, int $maxTokens = 800): array
    {
        $payload = [
            'model'      => $this->model,
            'max_tokens' => $maxTokens,
            'system'     => $systemPrompt,
            'messages'   => $messages,
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
                'content-type'      => 'application/json',
            ])
            ->timeout(12)
            ->connectTimeout(3)
            ->post(self::API_URL, $payload);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new LexusAnthropicException('Timeout ou falha de conexão com Anthropic: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            throw new LexusAnthropicException('Erro inesperado na chamada Anthropic: ' . $e->getMessage(), 0, $e);
        }

        if (!$response->successful()) {
            throw new LexusAnthropicException(
                "Anthropic retornou HTTP {$response->status()}: " . $response->body()
            );
        }

        $data = $response->json();

        $contentText = $data['content'][0]['text'] ?? '';
        $usage       = $data['usage'] ?? [];

        return [
            'content_text'  => $contentText,
            'input_tokens'  => $usage['input_tokens'] ?? 0,
            'output_tokens' => $usage['output_tokens'] ?? 0,
            'stop_reason'   => $data['stop_reason'] ?? 'unknown',
            'raw'           => $data,
        ];
    }
}
