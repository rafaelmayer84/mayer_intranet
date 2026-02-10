<?php

namespace App\Services\OpenAI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.model', 'gpt-4o-mini');
        $this->baseUrl = 'https://api.openai.com/v1';
    }

    public function gerarRespostaStatusProcesso(array $andamentos, string $numeroProcesso, string $nomeCliente): string
    {
        $prompt = $this->montarPromptStatusProcesso($andamentos, $numeroProcesso, $nomeCliente);

        try {
            $inicio = microtime(true);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é uma secretária experiente do escritório Mayer Albanez Sociedade de Advogados. Seu papel é explicar andamentos processuais de forma clara, profissional e acessível para clientes leigos. Use linguagem simples, seja empática e objetiva.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 500
            ]);

            $tempoResposta = (int)((microtime(true) - $inicio) * 1000);

            if ($response->successful()) {
                $resultado = $response->json();
                $texto = $resultado['choices'][0]['message']['content'] ?? '';
                
                Log::info('OpenAI resposta gerada', [
                    'tempo_ms' => $tempoResposta,
                    'tokens' => $resultado['usage']['total_tokens'] ?? 0
                ]);

                return trim($texto);
            }

            Log::error('Erro OpenAI API', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return $this->respostaFallback($andamentos, $numeroProcesso, $nomeCliente);

        } catch (\Exception $e) {
            Log::error('Exceção OpenAI', [
                'erro' => $e->getMessage()
            ]);

            return $this->respostaFallback($andamentos, $numeroProcesso, $nomeCliente);
        }
    }

    private function montarPromptStatusProcesso(array $andamentos, string $numeroProcesso, string $nomeCliente): string
    {
        $andamentosTexto = '';
        
        foreach ($andamentos as $index => $andamento) {
            $andamentosTexto .= sprintf(
                "%d. Data: %s - %s\n",
                $index + 1,
                $andamento['data'] ?? 'Sem data',
                $andamento['descricao'] ?? 'Sem descrição'
            );
        }

        return <<<PROMPT
O cliente {$nomeCliente} solicitou informações sobre o processo {$numeroProcesso}.

Últimos andamentos processuais:
{$andamentosTexto}

Por favor:
1. Cumprimente o cliente pelo nome
2. Confirme que consultou o processo (mencione o número)
3. Liste os principais andamentos de forma clara e cronológica (use emoji 📅 antes das datas)
4. Explique o que cada andamento significa em linguagem simples
5. Informe a situação atual do processo
6. Seja breve (máximo 6 linhas)
7. Não use saudações de despedida (o fluxo continua)

Importante: Use tom profissional mas acessível. Evite termos jurídicos complexos.
PROMPT;
    }

    private function respostaFallback(array $andamentos, string $numeroProcesso, string $nomeCliente): string
    {
        $ultimoAndamento = $andamentos[0] ?? null;
        
        if (!$ultimoAndamento) {
            return "Olá {$nomeCliente}! Consultei seu processo {$numeroProcesso}, mas não há andamentos recentes registrados. Se tiver dúvidas, nossa equipe está à disposição.";
        }

        return sprintf(
            "Olá %s! Consultei seu processo %s.\n\n📅 Último andamento em %s:\n%s\n\nSeu processo está em andamento normal.",
            $nomeCliente,
            $numeroProcesso,
            $ultimoAndamento['data'] ?? 'data não informada',
            $ultimoAndamento['descricao'] ?? 'Movimentação processual'
        );
    }
}
