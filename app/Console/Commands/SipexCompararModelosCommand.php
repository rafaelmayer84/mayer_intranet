<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SipexCompararModelosCommand extends Command
{
    protected $signature = 'sipex:comparar-modelos
        {--cenario=todos : Cenario especifico (1,2,3) ou "todos"}
        {--gpt-model=gpt-5.4 : Modelo OpenAI para comparar}
        {--claude-model=claude-opus-4-6 : Modelo Claude para comparar}';

    protected $description = 'Compara respostas de precificacao entre GPT e Claude Opus para decidir migracao';

    private array $cenarios = [];

    public function handle()
    {
        $this->cenarios = $this->buildCenarios();

        $cenarioArg = $this->option('cenario');
        $gptModel = $this->option('gpt-model');
        $claudeModel = $this->option('claude-model');

        $this->info("=== SIPEX Comparador de Modelos ===");
        $this->info("GPT: {$gptModel} | Claude: {$claudeModel}");
        $this->newLine();

        $cenariosParaRodar = [];
        if ($cenarioArg === 'todos') {
            $cenariosParaRodar = $this->cenarios;
        } else {
            $indices = explode(',', $cenarioArg);
            foreach ($indices as $i) {
                $idx = (int) $i - 1;
                if (isset($this->cenarios[$idx])) {
                    $cenariosParaRodar[] = $this->cenarios[$idx];
                }
            }
        }

        if (empty($cenariosParaRodar)) {
            $this->error('Nenhum cenario valido selecionado.');
            return 1;
        }

        $systemPrompt = $this->buildSystemPrompt();
        $resultados = [];

        foreach ($cenariosParaRodar as $idx => $cenario) {
            $num = $idx + 1;
            $this->info("━━━ Cenario {$num}: {$cenario['titulo']} ━━━");
            $this->info("Area: {$cenario['dados']['area_direito']} | Tipo: {$cenario['dados']['tipo_pessoa']} | Valor causa: R$ " . number_format($cenario['dados']['valor_causa'] ?? 0, 2, ',', '.'));
            $this->newLine();

            $userPrompt = $this->buildUserPrompt($cenario['dados']);

            // --- GPT ---
            $this->info("[GPT {$gptModel}] Enviando...");
            $startGpt = microtime(true);
            $resGpt = $this->callOpenAI($systemPrompt, $userPrompt, $gptModel);
            $timeGpt = round(microtime(true) - $startGpt, 1);

            if (isset($resGpt['erro'])) {
                $this->error("  GPT erro: {$resGpt['erro']}");
                $parsedGpt = null;
            } else {
                $parsedGpt = $this->parseJson($resGpt['content']);
                $this->info("  GPT OK ({$timeGpt}s, tokens: " . ($resGpt['tokens'] ?? '?') . ")");
            }

            // --- Claude ---
            $this->info("[Claude {$claudeModel}] Enviando...");
            $startClaude = microtime(true);
            $resClaude = $this->callClaude($systemPrompt, $userPrompt, $claudeModel);
            $timeClaude = round(microtime(true) - $startClaude, 1);

            if (isset($resClaude['erro'])) {
                $this->error("  Claude erro: {$resClaude['erro']}");
                $parsedClaude = null;
            } else {
                $parsedClaude = $this->parseJson($resClaude['content']);
                $this->info("  Claude OK ({$timeClaude}s, tokens: " . ($resClaude['tokens'] ?? '?') . ")");
            }

            $this->newLine();

            // --- Comparacao ---
            $this->info("┌─────────────────────────────────────────────────────────────────┐");
            $this->info("│ COMPARACAO CENARIO {$num}: {$cenario['titulo']}");
            $this->info("├─────────────────────┬─────────────────────┬─────────────────────┤");
            $this->info("│                     │ GPT {$gptModel}     │ Claude {$claudeModel}│");
            $this->info("├─────────────────────┼─────────────────────┼─────────────────────┤");

            if ($parsedGpt && $parsedClaude) {
                $this->printComparacaoLinha('Rapida', $parsedGpt['proposta_rapida'] ?? [], $parsedClaude['proposta_rapida'] ?? []);
                $this->printComparacaoLinha('Equilibrada', $parsedGpt['proposta_equilibrada'] ?? [], $parsedClaude['proposta_equilibrada'] ?? []);
                $this->printComparacaoLinha('Premium', $parsedGpt['proposta_premium'] ?? [], $parsedClaude['proposta_premium'] ?? []);

                $this->info("├─────────────────────┼─────────────────────┼─────────────────────┤");
                $recGpt = $parsedGpt['recomendacao'] ?? '?';
                $recClaude = $parsedClaude['recomendacao'] ?? '?';
                $this->info("│ Recomendacao        │ " . str_pad($recGpt, 19) . " │ " . str_pad($recClaude, 19) . " │");

                $this->info("│ Tempo               │ " . str_pad("{$timeGpt}s", 19) . " │ " . str_pad("{$timeClaude}s", 19) . " │");
            }
            $this->info("└─────────────────────┴─────────────────────┴─────────────────────┘");
            $this->newLine();

            // Justificativas
            if ($parsedGpt) {
                $this->info("[GPT] Justificativa: " . mb_substr($parsedGpt['justificativa_recomendacao'] ?? '', 0, 200));
            }
            if ($parsedClaude) {
                $this->info("[Claude] Justificativa: " . mb_substr($parsedClaude['justificativa_recomendacao'] ?? '', 0, 200));
            }
            $this->newLine();

            $resultados[] = [
                'cenario' => $cenario['titulo'],
                'gpt' => [
                    'model' => $gptModel,
                    'time' => $timeGpt,
                    'tokens' => $resGpt['tokens'] ?? null,
                    'response' => $parsedGpt,
                    'raw' => $resGpt['content'] ?? null,
                ],
                'claude' => [
                    'model' => $claudeModel,
                    'time' => $timeClaude,
                    'tokens' => $resClaude['tokens'] ?? null,
                    'response' => $parsedClaude,
                    'raw' => $resClaude['content'] ?? null,
                ],
            ];
        }

        // Salvar resultados
        $filename = 'sipex_comparacao_' . date('Y-m-d_His') . '.json';
        $path = storage_path("app/{$filename}");
        file_put_contents($path, json_encode($resultados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info("Resultados salvos em: {$path}");

        return 0;
    }

    private function printComparacaoLinha(string $tier, array $gpt, array $claude): void
    {
        $valGpt = isset($gpt['valor_honorarios']) ? 'R$' . number_format($gpt['valor_honorarios'], 0, ',', '.') : '?';
        $valClaude = isset($claude['valor_honorarios']) ? 'R$' . number_format($claude['valor_honorarios'], 0, ',', '.') : '?';

        $parcGpt = '?';
        if (isset($gpt['parcelas'])) {
            $p = is_array($gpt['parcelas']) ? ($gpt['parcelas']['total'] ?? '?') : $gpt['parcelas'];
            $parcGpt = "{$p}x";
        } elseif (isset($gpt['parcelas_sugeridas'])) {
            $parcGpt = "{$gpt['parcelas_sugeridas']}x";
        }

        $parcClaude = '?';
        if (isset($claude['parcelas'])) {
            $p = is_array($claude['parcelas']) ? ($claude['parcelas']['total'] ?? '?') : $claude['parcelas'];
            $parcClaude = "{$p}x";
        } elseif (isset($claude['parcelas_sugeridas'])) {
            $parcClaude = "{$claude['parcelas_sugeridas']}x";
        }

        $gptStr = "{$valGpt} ({$parcGpt})";
        $claudeStr = "{$valClaude} ({$parcClaude})";

        $this->info("│ " . str_pad($tier, 19) . " │ " . str_pad($gptStr, 19) . " │ " . str_pad($claudeStr, 19) . " │");
    }

    private function buildCenarios(): array
    {
        return [
            [
                'titulo' => 'PF Trabalhista Simples',
                'dados' => [
                    'tipo_pessoa' => 'PF',
                    'area_direito' => 'Direito do Trabalho',
                    'tipo_acao' => 'Reclamacao Trabalhista',
                    'descricao_demanda' => 'Representacao juridica para ingresso de reclamacao trabalhista com pedido de rescisao indireta do contrato de trabalho, cobranca de verbas rescisórias (FGTS, ferias, 13o, aviso-previo) e horas extras.',
                    'valor_causa' => 45000,
                    'valor_economico' => 45000,
                    'nome_proponente' => 'Joao da Silva',
                    'historico_crm_conversao' => [
                        'resumo_geral' => ['total_oportunidades' => 85, 'ganhas' => 52, 'perdidas' => 33, 'win_rate_geral' => 61.2, 'ticket_medio_ganhas' => 4850, 'ticket_medio_perdidas' => 7200],
                        'faixas_preco_conversao' => [
                            'ate_2000' => ['total' => 15, 'ganhas' => 12, 'win_rate' => 80.0],
                            '2001_a_5000' => ['total' => 30, 'ganhas' => 22, 'win_rate' => 73.3],
                            '5001_a_10000' => ['total' => 25, 'ganhas' => 13, 'win_rate' => 52.0],
                            '10001_a_20000' => ['total' => 10, 'ganhas' => 4, 'win_rate' => 40.0],
                            'acima_20000' => ['total' => 5, 'ganhas' => 1, 'win_rate' => 20.0],
                        ],
                        'motivos_perda' => [
                            ['motivo' => 'Preco dos honorarios', 'qtd' => 12, 'ticket_medio' => 8500],
                            ['motivo' => 'Desistencia do cliente', 'qtd' => 8, 'ticket_medio' => 4200],
                            ['motivo' => 'Contratou outro escritorio', 'qtd' => 7, 'ticket_medio' => 6100],
                        ],
                    ],
                    'macro_escritorio' => ['receita_mes_atual' => 42000, 'meta_mes_atual' => 65000, 'percentual_meta' => 64.6, 'processos_ativos' => 180, 'pipeline_aberto' => 95000],
                    'historico_contratos_reais' => 'Trabalhista PF fixo: tipico R$4.000-R$11.000. Quota litis e predominante nesta area (R$1-R$30 entrada + % do exito). Contratos fixos trabalhistas tem ticket medio de R$6.000.',
                ],
            ],
            [
                'titulo' => 'PJ Civel Complexo',
                'dados' => [
                    'tipo_pessoa' => 'PJ',
                    'area_direito' => 'Direito Civil e Empresarial',
                    'tipo_acao' => 'Acao de Cancelamento/Retificacao de Registro',
                    'descricao_demanda' => 'Ajuizamento e conducao de acao de cancelamento/retificacao de registro imobiliario (Lei 6.015/73, art. 214), relativa a retificacao ja averbada que alterou confrontacao/perimetro em prejuizo da empresa, com tutela de urgencia, prova pericial e medidas correlatas ate sentenca. Adicionalmente, atuacao extrajudicial para unificacao de matriculas.',
                    'valor_causa' => 350000,
                    'valor_economico' => 350000,
                    'nome_proponente' => 'CMA Administradora de Bens LTDA',
                    'historico_crm_conversao' => [
                        'resumo_geral' => ['total_oportunidades' => 85, 'ganhas' => 52, 'perdidas' => 33, 'win_rate_geral' => 61.2, 'ticket_medio_ganhas' => 4850, 'ticket_medio_perdidas' => 7200],
                        'area_especifica' => ['area' => 'Direito Civil e Empresarial', 'total' => 20, 'ganhas' => 13, 'perdidas' => 7, 'win_rate' => 65.0, 'ticket_medio_won' => 5800, 'ticket_medio_lost' => 9200],
                        'faixas_preco_conversao' => [
                            'ate_2000' => ['total' => 15, 'ganhas' => 12, 'win_rate' => 80.0],
                            '2001_a_5000' => ['total' => 30, 'ganhas' => 22, 'win_rate' => 73.3],
                            '5001_a_10000' => ['total' => 25, 'ganhas' => 13, 'win_rate' => 52.0],
                            '10001_a_20000' => ['total' => 10, 'ganhas' => 4, 'win_rate' => 40.0],
                            'acima_20000' => ['total' => 5, 'ganhas' => 1, 'win_rate' => 20.0],
                        ],
                        'motivos_perda' => [
                            ['motivo' => 'Preco dos honorarios', 'qtd' => 12, 'ticket_medio' => 8500],
                            ['motivo' => 'Desistencia do cliente', 'qtd' => 8, 'ticket_medio' => 4200],
                        ],
                    ],
                    'macro_escritorio' => ['receita_mes_atual' => 58000, 'meta_mes_atual' => 65000, 'percentual_meta' => 89.2, 'processos_ativos' => 180, 'pipeline_aberto' => 95000],
                    'historico_contratos_reais' => 'Civel comum PJ: tipico R$5.000-R$36.000. Casos imobiliarios complexos com pericia: R$25.000-R$36.000. Escritorio ja fechou contrato semelhante (CMA) por R$36.000 com 2 objetos (acao + extrajudicial).',
                ],
            ],
            [
                'titulo' => 'PF Previdenciario Administrativo',
                'dados' => [
                    'tipo_pessoa' => 'PF',
                    'area_direito' => 'Direito Previdenciario',
                    'tipo_acao' => 'Requerimento administrativo de auxilio-acidente',
                    'descricao_demanda' => 'Representacao para demanda administrativa no INSS para concessao do auxilio-acidente. Caso indeferido, sera demandada acao judicial requerendo a concessao de forma judicial. Inclui organizacao de documentacao medica e acompanhamento de pericias.',
                    'valor_causa' => 80000,
                    'valor_economico' => 80000,
                    'nome_proponente' => 'Carlos Roberto da Silva',
                    'historico_crm_conversao' => [
                        'resumo_geral' => ['total_oportunidades' => 85, 'ganhas' => 52, 'perdidas' => 33, 'win_rate_geral' => 61.2, 'ticket_medio_ganhas' => 4850, 'ticket_medio_perdidas' => 7200],
                        'faixas_preco_conversao' => [
                            'ate_2000' => ['total' => 15, 'ganhas' => 12, 'win_rate' => 80.0],
                            '2001_a_5000' => ['total' => 30, 'ganhas' => 22, 'win_rate' => 73.3],
                            '5001_a_10000' => ['total' => 25, 'ganhas' => 13, 'win_rate' => 52.0],
                        ],
                        'motivos_perda' => [
                            ['motivo' => 'Preco dos honorarios', 'qtd' => 12, 'ticket_medio' => 8500],
                        ],
                    ],
                    'macro_escritorio' => ['receita_mes_atual' => 42000, 'meta_mes_atual' => 65000, 'percentual_meta' => 64.6, 'processos_ativos' => 180, 'pipeline_aberto' => 95000],
                    'historico_contratos_reais' => 'Previdenciario fixo: tipico R$2.000-R$7.000. Quota litis e comum (R$1 entrada + % do beneficio). Contratos fixos tem ticket medio de R$3.500. Auxilio-acidente geralmente R$2.000-R$3.857 fixo ou quota litis.',
                ],
            ],
        ];
    }

    private function buildSystemPrompt(): string
    {
        return <<<'SYSTEM'
Voce e um consultor senior de precificacao estrategica para escritorios de advocacia no Brasil, especializado em YIELD MANAGEMENT (gestao de rendimento) — a mesma ciencia de precificacao usada por companhias aereas, hoteis e plataformas de e-commerce.

PRINCIPIOS DE YIELD MANAGEMENT APLICADOS:
1. ELASTICIDADE DE DEMANDA: Clientes diferentes tem sensibilidades a preco diferentes.
   PF inadimplente = alta elasticidade (precificar para fechar). PJ capitalizada = baixa elasticidade (precificar para margem).
2. SEGMENTACAO DE PRECOS: As 3 propostas representam segmentos como classes de voo:
   - RAPIDA = "Economica": Maximiza CONVERSAO. Preco otimizado para fechar o contrato. Escopo enxuto, essencial.
   - EQUILIBRADA = "Executiva": SWEET SPOT entre receita e conversao. Escopo completo padrao.
   - PREMIUM = "Primeira Classe": Maximiza RECEITA por caso. Escopo expandido com diferenciais.
3. PRECIFICACAO DINAMICA: O preco varia conforme:
   - Load factor do escritorio (meta vs realizado — se abaixo da meta, precificar para volume)
   - Momento da demanda (urgencia do cliente = menor elasticidade = preco mais alto)
   - Historico de conversao real (faixas de preco com maior win rate do CRM)
   - Historico de contratos reais fechados pelo escritorio (ancora de realidade)
4. REVENUE OPTIMIZATION:
   - RAPIDA: probabilidade de conversao estimada >= 70%
   - EQUILIBRADA: probabilidade estimada 40-60%, maximiza Expected Revenue (preco x probabilidade)
   - PREMIUM: probabilidade estimada 15-30%, alta margem para quem aceitar
   - O Expected Revenue de cada proposta deve ser calculado e informado
5. PARCELAMENTO (sugestao psicologica, NAO analise financeira do cliente):
   - Parcelas menores parecem mais acessiveis psicologicamente
   - Se escritorio esta abaixo da meta, preferir entrada mais alta
   - Casos longos permitem mais parcelas; casos curtos, menos
   - PF tende a preferir mais parcelas (3-6x); PJ menos (1-3x)
   - Oferecer desconto a vista (3-8%)
   - Padrao: 3-6 parcelas PF, 1-3 PJ

PROPORCIONALIDADE OBRIGATORIA:
- Honorarios entre 5% a 20% do valor economico, ajustando conforme complexidade e perfil.
- PF com dificuldade financeira: 5-10%. PJ ou causas complexas: 10-20%.
- NUNCA aplique ticket medio geral como valor base sem considerar o caso concreto.

REGRA DE FASE PROCESSUAL:
- ACAO COMPLETA (distribuicao ate sentenca): 8-20%
- RECURSO/CONTRARRAZOES (2a instancia): 3-8%
- CUMPRIMENTO DE SENTENCA: 3-6%
- CAUTELAR/TUTELA AVULSA: 3-8%
- FASE ESPECIFICA (audiencia, parecer, peticao): 2-5%
- ADMINISTRATIVO (INSS, PROCON, etc): 3-8%

Regras absolutas:
1. Sempre produza EXATAMENTE 3 propostas: "rapida", "equilibrada" e "premium"
2. Sempre indique qual das 3 voce RECOMENDA e por que
3. Valores em Reais (BRL). NUNCA valores redondos/cheios (ex: 5000, 8000). SEMPRE valores quebrados impares (ex: 3.847, 5.173, 7.291)
4. Para cada proposta, incluir: valor_honorarios, tipo_cobranca, parcelas (objeto), probabilidade_conversao_estimada, expected_revenue, justificativa_estrategica
5. Responda EXCLUSIVAMENTE em JSON valido, sem markdown, sem texto fora do JSON

Formato de resposta obrigatorio:
{
  "proposta_rapida": {
    "valor_honorarios": 2847,
    "tipo_cobranca": "fixo",
    "parcelas": {
      "total": 6,
      "entrada": 847,
      "valor_parcela": 400,
      "periodicidade": "mensal",
      "desconto_avista_percentual": 7,
      "valor_avista": 2647,
      "justificativa": "Parcelamento estendido para facilitar adesao de PF sensivel a preco"
    },
    "probabilidade_conversao_estimada": 75,
    "expected_revenue": 2135,
    "justificativa_estrategica": "..."
  },
  "proposta_equilibrada": { ... },
  "proposta_premium": { ... },
  "recomendacao": "equilibrada",
  "justificativa_recomendacao": "...",
  "analise_yield": {
    "segmento_cliente": "PF_sensivel_preco | PF_padrao | PJ_pequena | PJ_media_grande",
    "elasticidade_estimada": "alta | media | baixa",
    "load_factor_escritorio": "abaixo_meta | na_meta | acima_meta",
    "estrategia_dominante": "volume | equilibrio | margem",
    "faixa_historica_aplicada": "R$X-R$Y",
    "expected_revenue_recomendada": 3500
  },
  "analise_risco": "...",
  "observacoes_estrategicas": "...",
  "piso_oab_aplicado": 3906.73
}
SYSTEM;
    }

    private function buildUserPrompt(array $dados): string
    {
        $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
Analise o seguinte pacote de dados e produza 3 propostas de honorarios advocaticios.

DADOS DO CASO:
{$json}

INSTRUCOES DE RACIOCINIO:

ETAPA 1 - ANALISE DE DADOS REAIS DE CONVERSAO:
O campo "historico_crm_conversao" contem dados REAIS de oportunidades ganhas e perdidas.
Use como ANCORA PRINCIPAL: faixas_preco_conversao, motivos_perda, ticket_medio.

ETAPA 2 - SEGMENTACAO DO CLIENTE:
Classifique o cliente em segmento (PF_sensivel_preco, PF_padrao, PJ_pequena, PJ_media_grande).
Determine a elasticidade de demanda (alta/media/baixa).

ETAPA 3 - YIELD MANAGEMENT:
Avalie o load factor do escritorio via "macro_escritorio" (percentual_meta).
Se abaixo de 80%: estrategia "volume" (precos mais competitivos para fechar).
Se entre 80-120%: estrategia "equilibrio".
Se acima de 120%: estrategia "margem" (precos mais altos, selecionar casos).

ETAPA 4 - GERACAO COM PARCELAMENTO:
Use "historico_contratos_reais" como referencia dos precos praticados pelo escritorio.
Gere valores PROPORCIONAIS ao valor economico E coerentes com o historico real.
Sugira parcelamento com base em criterios psicologicos e metricas do escritorio (NAO analise financeira do cliente).
Calcule Expected Revenue para cada proposta (valor x probabilidade / 100).

ETAPA 5 - PISO OAB/SC:
Respeite os pisos da OAB/SC como referencia minima.

Responda APENAS com o JSON, sem nenhum texto adicional.
PROMPT;
    }

    private function callOpenAI(string $systemPrompt, string $userPrompt, string $model): array
    {
        $apiKey = env('OPENAI_API_KEY') ?: env('JUSTUS_OPENAI_API_KEY');

        if (!$apiKey) {
            return ['erro' => 'OPENAI_API_KEY nao configurada'];
        }

        try {
            $response = Http::timeout(200)
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
                $usage = $response->json('usage');
                $tokens = ($usage['prompt_tokens'] ?? 0) + ($usage['completion_tokens'] ?? 0);
                return ['content' => $content, 'tokens' => $tokens];
            }

            return ['erro' => "OpenAI status {$response->status()}: " . mb_substr($response->body(), 0, 300)];
        } catch (\Exception $e) {
            return ['erro' => 'OpenAI exception: ' . $e->getMessage()];
        }
    }

    private function callClaude(string $systemPrompt, string $userPrompt, string $model): array
    {
        $apiKey = env('JUSTUS_ANTHROPIC_API_KEY');

        if (!$apiKey) {
            return ['erro' => 'JUSTUS_ANTHROPIC_API_KEY nao configurada'];
        }

        try {
            $response = Http::connectTimeout(15)->timeout(200)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 4000,
                    'temperature' => 0.7,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            if ($response->successful()) {
                $content = $response->json('content.0.text') ?? '';
                $usage = $response->json('usage');
                $tokens = ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0);
                return ['content' => $content, 'tokens' => $tokens];
            }

            return ['erro' => "Claude status {$response->status()}: " . mb_substr($response->body(), 0, 300)];
        } catch (\Exception $e) {
            return ['erro' => 'Claude exception: ' . $e->getMessage()];
        }
    }

    private function parseJson(string $raw): ?array
    {
        $clean = trim($raw);

        // Remover markdown wrappers
        if (preg_match('/```(?:json)?\s*\n?([\s\S]+?)\n?\s*```/s', $clean, $m)) {
            $clean = trim($m[1]);
        }

        // Extrair JSON se houver texto antes/depois
        if (substr(ltrim($clean), 0, 1) !== '{') {
            $s = strpos($clean, '{');
            $e = strrpos($clean, '}');
            if ($s !== false && $e !== false && $e > $s) {
                $clean = substr($clean, $s, $e - $s + 1);
            }
        }

        $parsed = json_decode(trim($clean), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->warn("  JSON parse error: " . json_last_error_msg());
            return null;
        }

        return $parsed;
    }
}
