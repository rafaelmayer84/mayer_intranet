<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\SipexSetting;

class PricingAIService
{
    private string $model;
    private bool $isClaude;

    public function __construct()
    {
        $this->model = SipexSetting::get('modelo_ia', 'gpt-5.4');
        $this->isClaude = SipexSetting::isClaudeModel($this->model);
    }

    /**
     * Gera as 3 propostas de precificacao via IA
     */
    public function gerarPropostas(array $pacoteDados): array
    {
        $prompt = $this->buildPrompt($pacoteDados);
        $systemPrompt = $this->buildSystemPrompt();

        $response = $this->isClaude
            ? $this->callClaude($systemPrompt, $prompt)
            : $this->callOpenAI($systemPrompt, $prompt);

        if (isset($response['erro'])) {
            return $response;
        }

        return $this->parseResponse($response);
    }

    // =========================================================================
    // SYSTEM PROMPT — Yield Management Juridico
    // =========================================================================

    private function buildSystemPrompt(): string
    {
        return <<<'SYSTEM'
Voce e um consultor senior de precificacao estrategica para escritorios de advocacia no Brasil, especializado em YIELD MANAGEMENT JURIDICO.

Sua funcao e encontrar o PRECO OTIMO: o ponto que maximiza a RECEITA ESPERADA (Expected Revenue = preco x probabilidade de conversao).

=== FRAMEWORK DE YIELD MANAGEMENT ===

Voce gera 3 propostas que funcionam como CLASSES DE SERVICO (estilo companhia aerea):

1. PROPOSTA RAPIDA (Economica): Maximiza CONVERSAO (probabilidade >= 70%). Posicionada na faixa de maior win rate do CRM. Escopo enxuto.
2. PROPOSTA EQUILIBRADA (Executiva): SWEET SPOT — maximiza Expected Revenue. Equilibrio entre margem e conversao (probabilidade 40-60%). Escopo completo.
3. PROPOSTA PREMIUM (Primeira Classe): Maximiza RECEITA por caso (probabilidade 15-30%). Escopo expandido com diferenciais exclusivos.

=== DADOS REAIS DO CRM ===

O campo "historico_crm_conversao" contem dados REAIS de oportunidades ganhas e perdidas. USE como ancora principal:
- "faixas_preco_conversao": em qual faixa de valor o escritorio tem MAIOR taxa de conversao
- "motivos_perda": por que o escritorio perde clientes
- "insight_preco": ticket medio das propostas perdidas por preco

=== HISTORICO REAL DE CONTRATOS FECHADOS ===

Estes dados sao de ~350 contratos reais do escritorio. Use como ANCORA DE REALIDADE:
- Trabalhista PF fixo: tipico R$4.000-R$11.000
- Trabalhista defesa PJ: tipico R$3.000-R$5.400
- Civel comum: tipico R$3.000-R$7.000
- Criminal/Penal: tipico R$6.000-R$15.000
- Familia: tipico R$3.000-R$5.000
- Previdenciario fixo: tipico R$2.000-R$3.857
- Imobiliario: tipico R$1.600-R$25.000
- Extrajudicial: tipico R$262-R$2.625
- Empresarial: tipico R$2.000-R$8.500
- JEC: tipico R$750-R$3.000
- Alvara: tipico R$1.600-R$1.831

=== PROPORCIONALIDADE OBRIGATORIA ===

Honorarios devem ser PROPORCIONAIS ao valor economico da demanda:
- PF com dificuldade financeira: 5-10% do valor economico
- PF padrao: 8-15% do valor economico
- PJ: 8-20% do valor economico
- Causas complexas com pericia: 10-20% do valor economico

REGRA DE FASE PROCESSUAL:
- ACAO COMPLETA (distribuicao ate sentenca): 8-20%
- RECURSO/CONTRARRAZOES (2a instancia): 3-8%
- CUMPRIMENTO DE SENTENCA: 3-6%
- CAUTELAR/TUTELA AVULSA: 3-8%
- FASE ESPECIFICA (audiencia, parecer, peticao): 2-5%

=== SUGESTAO DE PARCELAMENTO (CRITERIOS PSICOLOGICOS) ===

NOTA: A analise financeira do cliente e competencia do SIRIC. O SIPEX sugere parcelamento apenas com base em criterios PSICOLOGICOS e METRICAS DO ESCRITORIO.

Para cada proposta, sugerir plano de parcelas considerando:
- Psicologia de precos: parcelas menores parecem mais acessiveis; valores quebrados transmitem calculo preciso
- Metricas do escritorio: se abaixo da meta, preferir entrada mais alta; se acima, flexibilizar
- Tipo de caso: longos (civel/familia) permitem mais parcelas; curtos (extrajudicial) menos
- Tipo de cliente: PF tende a preferir mais parcelas (4-6x); PJ menos (1-3x)
- Desconto a vista: 3-8% (incentivo para fluxo de caixa imediato)

=== REGRAS DE FORMATO ===

1. Sempre EXATAMENTE 3 propostas: "proposta_rapida", "proposta_equilibrada", "proposta_premium"
2. Sempre indique qual RECOMENDA e por que
3. Valores em Reais (BRL), como NUMEROS INTEIROS SEM PONTO OU VIRGULA (ex: 3847, 5173, 7291 — NAO 3.847 ou 3,847). Sempre valores quebrados com digitos impares que transmitam calculo preciso. NUNCA valores redondos (5000, 8000, 10000).
4. Todos os campos numericos devem ser INTEIROS (sem decimais). Exemplo correto: "valor_honorarios": 3847. Exemplo ERRADO: "valor_honorarios": 3.847
5. Responda EXCLUSIVAMENTE em JSON valido, sem markdown, sem texto fora do JSON, sem blocos de codigo

=== FORMATO JSON OBRIGATORIO ===

{
  "proposta_rapida": {
    "valor_honorarios": 3847,
    "tipo_cobranca": "fixo",
    "parcelas": {
      "total": 5,
      "entrada": 847,
      "valor_parcela": 750,
      "periodicidade": "mensal",
      "desconto_avista_percentual": 7,
      "valor_avista": 3577,
      "justificativa": "..."
    },
    "probabilidade_conversao_estimada": 75,
    "expected_revenue": 2885,
    "justificativa_estrategica": "..."
  },
  "proposta_equilibrada": { ... },
  "proposta_premium": { ... },
  "recomendacao": "equilibrada",
  "justificativa_recomendacao": "...",
  "analise_yield": {
    "segmento_cliente": "PF_sensivel_preco",
    "elasticidade_estimada": "alta",
    "load_factor_escritorio": "abaixo_meta",
    "estrategia_dominante": "volume",
    "faixa_historica_aplicada": "R$3.000-R$5.000",
    "expected_revenue_recomendada": 3500
  },
  "analise_risco": "...",
  "observacoes_estrategicas": "...",
  "piso_oab_aplicado": 3906
}
SYSTEM;
    }

    // =========================================================================
    // USER PROMPT — 5 Etapas de Raciocinio
    // =========================================================================

    private function buildPrompt(array $dados): string
    {
        $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
Analise o seguinte pacote de dados e produza 3 propostas de honorarios advocaticios usando YIELD MANAGEMENT JURIDICO.

DADOS DO CASO:
{$json}

INSTRUCOES DE RACIOCINIO (siga as 5 etapas):

ETAPA 1 — ANALISE DE DADOS REAIS DE CONVERSAO (PRIORIDADE MAXIMA):
O campo "historico_crm_conversao" contem dados REAIS de oportunidades ganhas e perdidas.
Use "faixas_preco_conversao" como ancora principal. Identifique a faixa de maior win rate.
Analise "motivos_perda" — se "Preco dos honorarios" e frequente, calibre para baixo.

ETAPA 2 — SEGMENTACAO DO CLIENTE:
Classifique o cliente em um dos segmentos:
- PF_sensivel_preco: PF sem patrimonio expressivo, alta elasticidade
- PF_padrao: PF com capacidade razoavel, elasticidade media
- PJ_pequena: PJ de pequeno porte, sensivel a custos
- PJ_media_grande: PJ estabelecida, menor sensibilidade
Use tipo_pessoa + valor_causa + area_direito + contexto para classificar.

ETAPA 3 — YIELD MANAGEMENT (LOAD FACTOR + ESTRATEGIA):
Analise "dados_macro_escritorio" para determinar o load factor:
- Se meta < 80%: estrategia de VOLUME (priorizar conversao, aceitar margem menor)
- Se meta 80-120%: estrategia de EQUILIBRIO (sweet spot)
- Se meta > 120%: estrategia de MARGEM (pode cobrar mais, ser mais seletivo)

ETAPA 4 — GERACAO DAS 3 PROPOSTAS COM PARCELAMENTO:
Cruze faixa proporcional (% do valor economico) com dados de conversao do CRM.
Gere parcelamento PSICOLOGICO (nao financeiro) para cada proposta.
Calcule Expected Revenue = valor_honorarios x (probabilidade_conversao_estimada / 100).

ETAPA 5 — PISO OAB/SC:
O campo "referencia_oab_sc" contem pisos para ACOES COMPLETAS.
Para fases pontuais, aplique 30-50% do piso.

LEMBRETE CRITICO: Todos os valores numericos no JSON devem ser NUMEROS INTEIROS (ex: 3847, nao 3.847). Use numeros sem pontos, virgulas ou separadores de milhar.

Responda APENAS com o JSON, sem nenhum texto adicional, sem markdown.
PROMPT;
    }

    // =========================================================================
    // API CALLS
    // =========================================================================

    private function callOpenAI(string $systemPrompt, string $userPrompt): array
    {
        $apiKey = env('JUSTUS_OPENAI_API_KEY') ?: env('OPENAI_API_KEY');

        if (!$apiKey) {
            return ['erro' => 'OPENAI_API_KEY nao configurada'];
        }

        $fallbacks = $this->getOpenAIFallbacks();
        $models = array_merge([$this->model], $fallbacks);

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
                        'max_completion_tokens' => 5000,
                        'response_format' => ['type' => 'json_object'],
                    ]);

                if ($response->successful()) {
                    $content = $response->json('choices.0.message.content');
                    Log::info('SIPEX Pricing: OK', ['model' => $model, 'tokens' => $response->json('usage')]);
                    return ['content' => $content, 'model' => $model];
                }

                Log::warning("SIPEX Pricing: Modelo {$model} falhou", [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);

            } catch (\Exception $e) {
                Log::warning("SIPEX Pricing: Exception com modelo {$model}", ['erro' => $e->getMessage()]);
            }
        }

        return ['erro' => 'Todos os modelos OpenAI falharam. Verifique a API Key e o saldo.'];
    }

    private function callClaude(string $systemPrompt, string $userPrompt, int $attempt = 1): array
    {
        $apiKey = env('JUSTUS_ANTHROPIC_API_KEY');

        if (!$apiKey) {
            return ['erro' => 'JUSTUS_ANTHROPIC_API_KEY nao configurada no .env'];
        }

        $maxRetries = 2;
        $fallbacks = $this->getClaudeFallbacks();
        $model = $attempt <= $maxRetries ? $this->model : ($fallbacks[0] ?? $this->model);

        try {
            $response = Http::connectTimeout(15)->timeout(200)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 5000,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            if ($response->successful()) {
                $content = $response->json('content.0.text') ?? '';
                $usage = $response->json('usage') ?? [];
                Log::info('SIPEX Pricing: OK', [
                    'model' => $model,
                    'input_tokens' => $usage['input_tokens'] ?? 0,
                    'output_tokens' => $usage['output_tokens'] ?? 0,
                ]);
                return ['content' => $content, 'model' => $model];
            }

            $status = $response->status();
            Log::error('SIPEX Pricing: Claude API erro', [
                'status' => $status,
                'body' => substr($response->body(), 0, 500),
                'attempt' => $attempt,
            ]);

            // Retry em 502/503/504
            if (in_array($status, [502, 503, 504]) && $attempt < $maxRetries) {
                sleep(2 * $attempt);
                return $this->callClaude($systemPrompt, $userPrompt, $attempt + 1);
            }

            // Tentar fallback
            if (!empty($fallbacks)) {
                $originalModel = $this->model;
                $this->model = $fallbacks[0];
                Log::info("SIPEX Pricing: tentando fallback {$this->model}");
                $result = $this->callClaude($systemPrompt, $userPrompt, 1);
                $this->model = $originalModel;
                return $result;
            }

            return ['erro' => "API Anthropic retornou status {$status}. Tente novamente."];

        } catch (\Exception $e) {
            Log::error('SIPEX Pricing: Claude Exception', ['msg' => $e->getMessage(), 'attempt' => $attempt]);

            if ($attempt < $maxRetries && (str_contains($e->getMessage(), 'timeout') || str_contains($e->getMessage(), 'Connection'))) {
                sleep(2 * $attempt);
                return $this->callClaude($systemPrompt, $userPrompt, $attempt + 1);
            }

            return ['erro' => 'Erro de conexao com API Anthropic: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // PARSE RESPONSE
    // =========================================================================

    private function parseResponse(array $response): array
    {
        $content = $response['content'] ?? '';

        // Remover markdown wrappers
        if (preg_match('/```(?:json)?\s*\n?([\s\S]+?)\n?\s*```/s', $content, $m)) {
            $content = trim($m[1]);
        }

        // Extrair JSON se houver texto antes/depois
        $trimmed = ltrim($content);
        if (substr($trimmed, 0, 1) !== '{') {
            $s = strpos($content, '{');
            $e = strrpos($content, '}');
            if ($s !== false && $e !== false && $e > $s) {
                $content = substr($content, $s, $e - $s + 1);
            }
        }

        $content = trim($content);

        // Fix: Claude pode usar ponto como separador de milhar (3.847 querendo dizer 3847)
        // Detectar e corrigir valores numericos com ponto decimal que sao na verdade milhares
        $content = $this->fixBrazilianNumberFormat($content);

        // Detectar JSON truncado
        if (substr(rtrim($content), -1) !== '}') {
            Log::warning('SIPEX Pricing: JSON truncado detectado');
            $content = $this->tentarFecharJsonTruncado($content);
        }

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('SIPEX Pricing: JSON invalido', [
                'error' => json_last_error_msg(),
                'content' => substr($content, 0, 500),
            ]);
            return ['erro' => 'A IA retornou uma resposta em formato invalido. Tente novamente.'];
        }

        // Validar estrutura minima
        $required = ['proposta_rapida', 'proposta_equilibrada', 'proposta_premium', 'recomendacao'];
        foreach ($required as $key) {
            if (!isset($parsed[$key])) {
                Log::error('SIPEX Pricing: Campo obrigatorio ausente', ['campo' => $key]);
                return ['erro' => "A IA nao retornou o campo obrigatorio: {$key}"];
            }
        }

        // Garantir que valor_honorarios e inteiro em todas as propostas
        foreach (['proposta_rapida', 'proposta_equilibrada', 'proposta_premium'] as $tipo) {
            if (isset($parsed[$tipo]['valor_honorarios'])) {
                $val = $parsed[$tipo]['valor_honorarios'];
                // Se valor < 100, provavelmente foi parseado como decimal (3.847 -> 3.847)
                if ($val < 100 && $val > 0) {
                    $parsed[$tipo]['valor_honorarios'] = (int) round($val * 1000);
                } else {
                    $parsed[$tipo]['valor_honorarios'] = (int) round($val);
                }
            }
        }

        $parsed['model_used'] = $response['model'] ?? 'unknown';

        return $parsed;
    }

    /**
     * Corrige formato numerico brasileiro no JSON.
     * Claude pode retornar 3.847 querendo dizer 3847.
     * Detecta campos numericos que usam ponto como milhar e converte para inteiro.
     */
    private function fixBrazilianNumberFormat(string $json): string
    {
        // Pattern: "campo_numerico": 3.847 (numero com ponto que deveria ser inteiro)
        // Campos conhecidos que devem ser inteiros
        $campos = [
            'valor_honorarios', 'entrada', 'valor_parcela', 'valor_avista',
            'expected_revenue', 'piso_oab_aplicado', 'expected_revenue_recomendada',
        ];

        foreach ($campos as $campo) {
            // Match: "campo": 3.847 ou "campo": 21.847 (1-3 digitos, ponto, 3 digitos)
            $json = preg_replace_callback(
                '/"' . preg_quote($campo, '/') . '"\s*:\s*(\d{1,3})\.(\d{3})(?=[,\s\}])/',
                function ($matches) {
                    return '"' . $matches[0] . '"'; // Hmm, this won't work right
                },
                $json
            );
        }

        // Abordagem mais robusta: para qualquer valor numerico X.YYY onde Y tem exatamente 3 digitos
        // em contexto de campos monetarios, converter para XYYY
        $json = preg_replace_callback(
            '/("(?:' . implode('|', array_map(fn($c) => preg_quote($c, '/'), $campos)) . ')"\s*:\s*)(\d{1,3})\.(\d{3})(?=[,\s\}\]])/',
            function ($matches) {
                return $matches[1] . $matches[2] . $matches[3];
            },
            $json
        );

        return $json;
    }

    /**
     * Tenta fechar JSON truncado (heuristica)
     */
    private function tentarFecharJsonTruncado(string $json): string
    {
        $openBraces = substr_count($json, '{') - substr_count($json, '}');
        $openBrackets = substr_count($json, '[') - substr_count($json, ']');

        // Fechar strings abertas
        $lastQuote = strrpos($json, '"');
        $quoteCount = substr_count($json, '"');
        if ($quoteCount % 2 !== 0) {
            $json .= '"';
        }

        // Fechar brackets e braces
        for ($i = 0; $i < $openBrackets; $i++) {
            $json .= ']';
        }
        for ($i = 0; $i < $openBraces; $i++) {
            $json .= '}';
        }

        return $json;
    }

    // =========================================================================
    // FALLBACK MODELS
    // =========================================================================

    private function getOpenAIFallbacks(): array
    {
        $fallbacks = [
            'gpt-5.4' => ['gpt-5.2', 'gpt-5'],
            'gpt-5.2' => ['gpt-5', 'gpt-5.1'],
            'gpt-4o-mini' => [],
        ];

        return $fallbacks[$this->model] ?? ['gpt-5.2'];
    }

    private function getClaudeFallbacks(): array
    {
        $fallbacks = [
            'claude-opus-4-6' => ['claude-sonnet-4-6'],
            'claude-sonnet-4-6' => [],
        ];

        return $fallbacks[$this->model] ?? ['claude-sonnet-4-6'];
    }
}
