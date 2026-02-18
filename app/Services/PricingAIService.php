<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PricingAIService
{
    private string $model = 'gpt-5.2';
    private array $fallbackModels = ['gpt-5', 'gpt-5.1'];

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

DIFERENCIAL: Você tem acesso a dados REAIS de oportunidades ganhas e perdidas do escritório (campo "historico_crm_conversao").
Esses dados mostram em quais faixas de preço e tipos de demanda o escritório efetivamente FECHA contratos.
USE ESSES DADOS como âncora principal. O objetivo é encontrar o MELHOR PREÇO para o escritório que seja VIÁVEL para o cliente pagar.

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

ETAPA 1 - ANÁLISE DE DADOS REAIS DE CONVERSÃO (PRIORIDADE MÁXIMA):
O campo "historico_crm_conversao" contém dados REAIS de oportunidades ganhas e perdidas pelo escritório.
Use esses dados como ÂNCORA PRINCIPAL de precificação:
- "resumo_geral": win rate geral, ticket médio de propostas ganhas vs perdidas
- "conversao_por_tipo_demanda": win rate e tickets por área do direito (Cível, Trabalhista, etc.)
- "area_especifica": se disponível, dados filtrados para a área exata desta demanda
- "motivos_perda": por que o escritório perde clientes (atenção especial a "Preço dos honorários")
- "faixas_preco_conversao": em qual faixa de valor o escritório tem MAIOR taxa de conversão
- "insight_preco": ticket médio das propostas perdidas especificamente por preço

REGRA CRÍTICA: Se existirem dados de propostas perdidas por preço, a proposta RÁPIDA deve ficar
ABAIXO desse ticket médio. A proposta EQUILIBRADA deve ficar na faixa de maior conversão.

ETAPA 2 - ANÁLISE COMPLEMENTAR:
1. Perfil do proponente (PF/PJ, histórico, capacidade de pagamento via SIRIC se disponível)
2. Complexidade da demanda (área, valor envolvido, urgência)
3. Histórico do escritório em processos similares (valores de causa, tempo médio, contratos)
4. Parâmetros de calibração estratégica da administração (cada eixo vai de 0 a 100)
5. Momento do escritório (meta vs realizado, pipeline, capacidade)

ETAPA 3 - GERAÇÃO DAS PROPOSTAS:
Produza 3 propostas calibradas pelos dados reais:
- RÁPIDA: valor que facilita fechamento imediato, baseado no piso da faixa de maior conversão
- EQUILIBRADA: melhor relação valor/probabilidade, baseado no ticket médio de propostas ganhas na área
- PREMIUM: valor que maximiza receita, para clientes que valorizam exclusividade e qualidade
Recomende a mais adequada para ESTE contexto específico

ETAPA 4 - PISO OAB/SC (REGRA ABSOLUTA):
O campo "referencia_oab_sc" contém os pisos mínimos de honorários da OAB/SC (Resolução CP 04/2025, atualizada IPCA 12/2024).
NENHUMA das 3 propostas pode ter valor_honorarios ABAIXO do "piso_padrao" da área.
A proposta RÁPIDA deve ser >= piso_padrao. A EQUILIBRADA deve ser significativamente acima. A PREMIUM mais ainda.
Se "area_encontrada" for false, use o bom senso e valores de mercado, mas nunca abaixo de R$ 2.000.
Inclua no JSON de resposta o campo "piso_oab_aplicado" com o valor do piso que você usou como referência.

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
                        'max_completion_tokens' => 4000,
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
