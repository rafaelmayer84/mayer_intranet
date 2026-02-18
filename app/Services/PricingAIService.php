<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PricingProposal;

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

PROPORCIONALIDADE OBRIGATÓRIA: O valor dos honorários deve ser PROPORCIONAL ao valor econômico da demanda e ao perfil do cliente.
- PF inadimplente com causa de R$40k NÃO pode ter mesma precificação que PJ capitalizada com causa de R$500k.
- Use como referência: honorários entre 5% a 20% do valor econômico, ajustando conforme complexidade e perfil.
- Para PF com dificuldade financeira: tenda para 5-10%. Para PJ ou causas complexas: tenda para 10-20%.
- Os dados de conversão do CRM são a âncora de REALIDADE, mas devem ser ponderados pelo caso específico, não aplicados como média genérica.
- NUNCA aplique o ticket médio geral como valor base sem considerar o valor econômico e perfil do caso concreto.

Regras absolutas:
1. Sempre produza EXATAMENTE 3 propostas: "rapida", "equilibrada" e "premium"
2. Sempre indique qual das 3 você RECOMENDA e por quê
3. Valores em Reais (BRL). NUNCA valores redondos/cheios (ex: 5000, 8000, 10000). SEMPRE valores quebrados com números ímpares que transmitam cálculo preciso (ex: 3.847, 5.173, 7.291, 2.637). Isso passa credibilidade de que o valor foi calculado, não arbitrado
4. Para cada proposta, informe: valor_honorarios, tipo_cobranca (fixo/mensal/percentual/misto), parcelas_sugeridas, e uma justificativa_estrategica
5. A justificativa deve ser profissional e utilizável em proposta ao cliente
6. Responda EXCLUSIVAMENTE em JSON válido, sem markdown, sem texto fora do JSON

Formato de resposta obrigatório:
{
  "proposta_rapida": {
    "valor_honorarios": 2.847,
    "tipo_cobranca": "fixo",
    "parcelas_sugeridas": 3,
    "justificativa_estrategica": "..."
  },
  "proposta_equilibrada": {
    "valor_honorarios": 4.173,
    "tipo_cobranca": "fixo",
    "parcelas_sugeridas": 5,
    "justificativa_estrategica": "..."
  },
  "proposta_premium": {
    "valor_honorarios": 6.391,
    "tipo_cobranca": "misto",
    "parcelas_sugeridas": 7,
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

REGRA CRÍTICA DE CALIBRAÇÃO:
- Se existirem dados de propostas perdidas por preço, use o ticket médio dessas perdas como SINAL DE ALERTA (não como teto absoluto).
- A proposta RÁPIDA deve ser competitiva para o perfil específico deste cliente, considerando proporcionalidade ao valor econômico.
- A proposta EQUILIBRADA deve maximizar probabilidade de fechamento PARA ESTE PERFIL, usando a faixa de maior conversão como referência.
- A proposta PREMIUM pode ultrapassar médias quando a complexidade justificar, mas deve ter justificativa sólida.
- NÃO aplique médias gerais de forma mecânica. Um caso cível de R$40k para PF é DIFERENTE de um cível de R$200k para PJ.

ETAPA 2 - ANÁLISE COMPLEMENTAR:
1. Perfil do proponente (PF/PJ, histórico, capacidade de pagamento via SIRIC se disponível)
2. Complexidade da demanda (área, valor envolvido, urgência)
3. Histórico do escritório em processos similares (valores de causa, tempo médio, contratos)
4. Parâmetros de calibração estratégica da administração (cada eixo vai de 0 a 100)
5. Momento do escritório (meta vs realizado, pipeline, capacidade)

ETAPA 3 - GERAÇÃO DAS PROPOSTAS (PROPORCIONALIDADE OBRIGATÓRIA):

REGRA DE FASE PROCESSUAL (CRÍTICA):
Analise o tipo_acao e descricao_demanda para identificar se é:
- AÇÃO COMPLETA (distribuição até sentença): usar faixa 8-20% do valor econômico
- RECURSO/CONTRARAZÕES (2ª instância): usar faixa 3-8% do valor econômico — escopo pontual e limitado
- CUMPRIMENTO DE SENTENÇA: usar faixa 3-6% do valor econômico — execução de decisão já obtida
- CAUTELAR/TUTELA AVULSA: usar faixa 3-8% do valor econômico
- FASE ESPECÍFICA (audiência avulsa, parecer, petição intercorrente): usar faixa 2-5% do valor econômico

O piso OAB/SC informado é para AÇÃO COMPLETA. Para fases pontuais (recurso, cumprimento, cautelar), 
o piso real é 30-50% do piso informado. NÃO use o piso de ação completa para trabalhos pontuais.

Calcule a FAIXA DE REFERÊNCIA baseada no valor econômico da demanda E na fase processual:
- Piso: percentual mínimo da fase (veja acima), respeitando no mínimo R$ 1.500
- Centro: percentual médio da fase, ajustado por complexidade
- Teto: percentual máximo da fase, para alta complexidade

Depois cruze com os dados de conversão do CRM para calibrar:
- RÁPIDA: piso da faixa proporcional, ajustado para baixo se o perfil indicar sensibilidade a preço (PF inadimplente, histórico de perda por preço)
- EQUILIBRADA: centro da faixa proporcional, validado pela faixa de maior conversão do CRM
- PREMIUM: teto da faixa proporcional, justificado por escopo expandido e valor agregado diferenciado

VALORES: Sempre quebrados e ímpares (ex: 2.847, 4.173, 6.391). NUNCA redondos.
Recomende a mais adequada para ESTE contexto específico, explicando o raciocínio de proporcionalidade.

ETAPA 4 - PISO OAB/SC (REFERÊNCIA, NÃO ABSOLUTA):
O campo "referencia_oab_sc" contém os pisos de honorários da OAB/SC para AÇÕES COMPLETAS.
Para FASES PONTUAIS (recurso, cumprimento, cautelar, petição avulsa), aplique 30-50% do piso_padrao como referência.

REGRA DE CALIBRAÇÃO COM DADOS REAIS:
Os dados de "historico_crm_conversao" → "faixas_preco_conversao" mostram em qual faixa de valor o escritório 
TEM MAIOR TAXA DE CONVERSÃO. Esta informação é MAIS IMPORTANTE que o piso OAB para definir a proposta RÁPIDA.
Se a faixa com maior win_rate é "ate_2000" ou "2001_a_5000", a proposta RÁPIDA DEVE estar nessa faixa 
(ou próxima dela), especialmente para PF com sensibilidade a preço.

A proposta RÁPIDA deve priorizar CONVERSÃO: ficar na faixa de maior win rate do histórico real.
A proposta EQUILIBRADA deve equilibrar MARGEM e CONVERSÃO: entre a faixa de maior win rate e o ticket médio won.
A proposta PREMIUM pode ultrapassar o ticket médio won quando a complexidade justificar.

Inclua no JSON o campo "piso_oab_aplicado" com o valor do piso ajustado que você usou (já considerando fase processual).

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

    /**
     * Gera texto persuasivo da proposta de honorários para envio ao cliente.
     * Retorna JSON estruturado com seções do documento.
     */
    public function gerarTextoPropostaCliente(PricingProposal $proposta, string $tipoEscolhido): array
    {
        $propostaData = $proposta->{'proposta_' . $tipoEscolhido} ?? [];
        $valor = $propostaData['valor_honorarios'] ?? 0;
        $parcelas = $propostaData['parcelas_sugeridas'] ?? 1;
        $tipoCobranca = $propostaData['tipo_cobranca'] ?? 'fixo';
        $justificativa = $propostaData['justificativa_estrategica'] ?? '';

        $systemPrompt = <<<'SYSTEM'
Você é um redator jurídico sênior do escritório Mayer Sociedade de Advogados (OAB/SC 2097), especializado em redigir propostas de honorários que convertem leads em clientes. Seu objetivo é produzir um documento persuasivo, profissional e coercitivo (no sentido positivo) que faça o destinatário desejar contratar imediatamente.

REGRAS DE ESTILO:
1. Tom: confiante, técnico mas acessível, empático com a dor do cliente, transmitindo autoridade e segurança.
2. NUNCA use bullet points, listas numeradas ou markdown. Redija em prosa fluida, em parágrafos completos.
3. Escrita em 3ª pessoa ("O Escritório", "A equipe jurídica"). Nunca "nós" ou "eu".
4. Use gatilhos de persuasão: escassez de tempo (prazos legais), autoridade (experiência do escritório), prova social (resultados anteriores na área), reciprocidade (diagnóstico gratuito já entregue), urgência (consequências da inação).
5. O diagnóstico deve demonstrar que o escritório JÁ entendeu profundamente o caso, gerando confiança.
6. A seção de diferenciais deve ser sutil e integrada, não uma lista de autoelogio.
7. O valor dos honorários deve ser apresentado com naturalidade e justificado pelo valor que entrega, não pelo custo.
8. Inclua uma seção sobre consequências de NÃO agir (sem ser alarmista, mas realista).

FORMATO DE RESPOSTA - JSON com estas chaves:
{
  "saudacao": "Parágrafo de abertura cordial e personalizado",
  "contexto_demanda": "Parágrafo(s) mostrando que o escritório entendeu a situação do cliente",
  "diagnostico": "Análise técnica preliminar demonstrando domínio da matéria",
  "escopo_servicos": "Descrição detalhada do que está incluído na prestação de serviços",
  "fases": [
    {"nome": "Fase 1 — Título", "descricao": "O que será feito nesta fase"},
    {"nome": "Fase 2 — Título", "descricao": "..."}
  ],
  "estrategia": "Abordagem jurídica que será adotada (transmitir competência)",
  "honorarios": {
    "descricao_valor": "Pró-labore: R$ X.XXX,XX",
    "forma_pagamento": "Condições de pagamento",
    "observacao": "Nota sobre o que está incluso no valor"
  },
  "honorarios_exito": "Parágrafo sobre honorários de êxito, se aplicável (ou null)",
  "despesas": "Parágrafo sobre custas e despesas processuais",
  "diferenciais": "Por que este escritório é a melhor escolha (sutil, integrado ao contexto)",
  "vigencia": "Condições de vigência, confidencialidade e próximos passos",
  "encerramento": "Parágrafo final cordial com call-to-action sutil"
}

Responda APENAS com o JSON, sem texto adicional, sem backticks, sem markdown.
SYSTEM;

        $dadosCaso = [
            'destinatario' => $proposta->nome_proponente,
            'tipo_pessoa' => $proposta->tipo_pessoa,
            'documento' => $proposta->documento_proponente,
            'area_direito' => $proposta->area_direito,
            'tipo_acao' => $proposta->tipo_acao,
            'descricao_demanda' => $proposta->descricao_demanda,
            'valor_causa' => $proposta->valor_causa,
            'valor_economico' => $proposta->valor_economico,
            'contexto_adicional' => $proposta->contexto_adicional,
            'siric_rating' => $proposta->siric_rating,
            'proposta_tipo' => $tipoEscolhido,
            'valor_honorarios' => $valor,
            'parcelas' => $parcelas,
            'tipo_cobranca' => $tipoCobranca,
            'justificativa_estrategica' => $justificativa,
            'valor_final_advogado' => $proposta->valor_final,
            'observacao_advogado' => $proposta->observacao_advogado,
        ];

        $userPrompt = "Gere a proposta de honorários para o seguinte caso:\n\n" .
            json_encode($dadosCaso, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $response = $this->callOpenAI($systemPrompt, $userPrompt);

        $content = $response['choices'][0]['message']['content'] ?? '{}';
        $content = trim($content);
        $content = preg_replace('/^```json\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::error('SIPEX PropostaCliente: JSON inválido da IA', ['raw' => $content]);
            return ['error' => 'Resposta inválida da IA. Tente novamente.'];
        }

        return $parsed;
    }
}
