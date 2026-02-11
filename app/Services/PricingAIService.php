<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PricingAIService
{
    private string $model = 'gpt-4o';
    private array $fallbackModels = ['gpt-4o-mini'];

    /**
     * Gera as 3 propostas de precificação via IA
     */
    public function gerarPropostas(array $pacoteDados): array
    {
        $prompt = $this->buildPrompt($pacoteDados);
        $systemPrompt = $this->buildSystemPrompt();

        $response = $this->callOpenAI($systemPrompt, $prompt);

        if (isset($response['erro'])) {
            return $response;
        }

        return $this->parseResponse($response);
    }

    private function buildSystemPrompt(): string
    {
        return <<<SYSTEM
Você é um consultor sênior de precificação estratégica para escritórios de advocacia no Brasil.
Sua função é deliberar sobre o valor de honorários advocatícios de forma racional, multidimensional e estratégica.

Você NÃO calcula preços mecanicamente. Você RACIOCINA sobre o preço ideal considerando TODAS as variáveis fornecidas.

Regras absolutas:
1. Sempre produza EXATAMENTE 3 propostas: "rapida", "equilibrada" e "premium"
2. Sempre indique qual das 3 você RECOMENDA e por quê
3. Valores em Reais (BRL), sem centavos para honorários acima de R$ 1.000
4. Para cada proposta, informe: valor_honorarios, tipo_cobranca (fixo/mensal/percentual/misto), parcelas_sugeridas, e uma justificativa_estrategica
5. A justificativa deve ser profissional e utilizável em proposta ao cliente
6. Responda EXCLUSIVAMENTE em JSON válido, sem markdown, sem texto fora do JSON

Formato de resposta obrigatório:
{
  "proposta_rapida": {
    "valor_honorarios": 5000,
    "tipo_cobranca": "fixo",
    "parcelas_sugeridas": 3,
    "justificativa_estrategica": "..."
  },
  "proposta_equilibrada": {
    "valor_honorarios": 8000,
    "tipo_cobranca": "fixo",
    "parcelas_sugeridas": 4,
    "justificativa_estrategica": "..."
  },
  "proposta_premium": {
    "valor_honorarios": 12000,
    "tipo_cobranca": "misto",
    "parcelas_sugeridas": 6,
    "justificativa_estrategica": "..."
  },
  "recomendacao": "equilibrada",
  "justificativa_recomendacao": "...",
  "analise_risco": "...",
  "observacoes_estrategicas": "..."
}
SYSTEM;
    }

    private function buildPrompt(array $dados): string
    {
        $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
Analise o seguinte pacote de dados e produza 3 propostas de honorários advocatícios.

DADOS DO CASO:
{$json}

INSTRUÇÕES DE RACIOCÍNIO:
1. Analise o perfil do proponente (pessoa física ou jurídica, histórico, capacidade de pagamento)
2. Avalie a complexidade da demanda (área, valor envolvido, urgência)
3. Considere o histórico do escritório em casos similares (valores médios, tempo médio)
4. Pondere os parâmetros de calibração estratégica da administração (cada eixo vai de 0 a 100)
5. Considere o momento do escritório (meta vs realizado, pipeline, capacidade)
6. Se houver análise de crédito SIRIC, use como fator de risco/confiança
7. Produza as 3 propostas com estratégias distintas:
   - RÁPIDA: valor que facilita o fechamento imediato, margem menor
   - EQUILIBRADA: melhor relação valor/probabilidade de fechamento
   - PREMIUM: valor que maximiza a receita, para clientes que valorizam exclusividade
8. Recomende a mais adequada para ESTE contexto específico

Responda APENAS com o JSON, sem nenhum texto adicional.
PROMPT;
    }

    private function callOpenAI(string $systemPrompt, string $userPrompt): array
    {
        $apiKey = config('services.openai.api_key') ?: env('OPENAI_API_KEY');

        if (!$apiKey) {
            return ['erro' => 'OPENAI_API_KEY não configurada'];
        }

        $models = array_merge([$this->model], $this->fallbackModels);

        foreach ($models as $model) {
            try {
                $response = Http::timeout(120)
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $userPrompt],
                        ],
                        'temperature' => 0.7,
                        'max_tokens' => 4000,
                        'response_format' => ['type' => 'json_object'],
                    ]);

                if ($response->successful()) {
                    $content = $response->json('choices.0.message.content');
                    Log::info('PricingAI: Resposta recebida', ['model' => $model, 'tokens' => $response->json('usage')]);
                    return ['content' => $content, 'model' => $model];
                }

                Log::warning("PricingAI: Modelo {$model} falhou", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

            } catch (\Exception $e) {
                Log::warning("PricingAI: Exception com modelo {$model}", ['erro' => $e->getMessage()]);
            }
        }

        return ['erro' => 'Todos os modelos falharam. Verifique a API Key e o saldo da OpenAI.'];
    }

    private function parseResponse(array $response): array
    {
        $content = $response['content'] ?? '';

        // Limpar possíveis marcadores markdown
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        $content = trim($content);

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('PricingAI: JSON inválido na resposta', ['content' => $content]);
            return ['erro' => 'A IA retornou uma resposta em formato inválido. Tente novamente.'];
        }

        // Validar estrutura mínima
        $required = ['proposta_rapida', 'proposta_equilibrada', 'proposta_premium', 'recomendacao'];
        foreach ($required as $key) {
            if (!isset($parsed[$key])) {
                Log::error('PricingAI: Campo obrigatório ausente', ['campo' => $key]);
                return ['erro' => "A IA não retornou o campo obrigatório: {$key}"];
            }
        }

        $parsed['model_used'] = $response['model'] ?? 'unknown';

        return $parsed;
    }
}
