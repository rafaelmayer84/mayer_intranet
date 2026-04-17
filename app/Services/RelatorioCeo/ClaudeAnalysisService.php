<?php
// ESTÁVEL desde 17/04/2026

namespace App\Services\RelatorioCeo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeAnalysisService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', env('JUSTUS_ANTHROPIC_API_KEY', ''));
    }

    public function analisar(array $dados): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('JUSTUS_ANTHROPIC_API_KEY não configurada.');
        }

        $response = Http::connectTimeout(30)->timeout(600)
            ->withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'         => 'claude-opus-4-7',
                'max_tokens'    => 20000,
                'temperature'   => 1,
                'thinking'      => ['type' => 'adaptive'],
                'output_config' => ['effort' => 'high'],
                'system'        => $this->systemPrompt(),
                'messages'      => [
                    ['role' => 'user', 'content' => $this->userPrompt($dados)],
                ],
            ]);

        if (!$response->successful()) {
            $body = $response->body();
            Log::error('RelatorioCeo ClaudeAnalysis: API error', [
                'status' => $response->status(),
                'body'   => substr($body, 0, 500),
            ]);
            throw new \RuntimeException("Claude API HTTP {$response->status()}: " . substr($body, 0, 200));
        }

        $json = $response->json();

        $texto = '';
        foreach ($json['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $texto = $block['text'];
                break;
            }
        }

        if (empty($texto)) {
            throw new \RuntimeException('Claude não retornou bloco text.');
        }

        $analise = $this->parseJson($texto);
        if (empty($analise)) {
            throw new \RuntimeException('Falha ao parsear JSON: ' . substr($texto, 0, 300));
        }

        return $analise;
    }

    private function gerarInventario(array $dados): string
    {
        $linhas = ["INVENTÁRIO DE DADOS DISPONÍVEIS NESTE CICLO:", ""];

        // Financeiro
        $fin = $dados['financeiro'] ?? [];
        if (isset($fin['erro'])) {
            $linhas[] = "✗ FINANCEIRO: falha na coleta — {$fin['erro']}";
        } else {
            $receita   = number_format($fin['dre_atual']['receita_total'] ?? 0, 0, ',', '.');
            $resultado = number_format($fin['dre_atual']['resultado'] ?? 0, 0, ',', '.');
            $var       = $fin['variacao_receita_pct'] ?? 0;
            $inadim    = number_format($fin['inadimplencia']['valor'] ?? 0, 0, ',', '.');
            $nTop      = count($fin['top_clientes_receita'] ?? []);
            $nDevedor  = count($fin['top_devedores'] ?? []);
            $linhas[] = "✓ FINANCEIRO: receita R\${$receita}, resultado R\${$resultado}, variação {$var}%, inadimplência R\${$inadim} | top {$nTop} clientes mapeados, {$nDevedor} devedores";
        }

        // GDP
        $gdp = $dados['gdp'] ?? [];
        if (isset($gdp['erro'])) {
            $linhas[] = "✗ GDP: falha na coleta — {$gdp['erro']}";
        } else {
            $nAdv   = count($gdp['snapshots'] ?? []);
            $nPen   = count($gdp['penalizacoes'] ?? []);
            $nMetas = count($gdp['metas_vs_realizado'] ?? []);
            $linhas[] = "✓ GDP: {$nAdv} advogados com snapshot, {$nPen} penalizações, {$nMetas} registros de metas vs. realizado";
        }

        // Processos
        $proc = $dados['processos'] ?? [];
        if (isset($proc['erro'])) {
            $linhas[] = "✗ PROCESSOS: falha na coleta — {$proc['erro']}";
        } else {
            $ativos   = number_format($proc['total_ativos'] ?? 0);
            $vencidos = $proc['prazos_vencidos'] ?? 0;
            $proximos = count($proc['prazos_proximos'] ?? []);
            $andamentos = number_format($proc['andamentos_periodo'] ?? 0);
            $linhas[] = "✓ PROCESSOS: {$ativos} ativos, {$vencidos} prazos vencidos, {$proximos} prazos críticos (30 dias), {$andamentos} andamentos no período";
        }

        // Leads
        $leads = $dados['leads'] ?? [];
        if (isset($leads['erro'])) {
            $linhas[] = "✗ LEADS: falha na coleta — {$leads['erro']}";
        } else {
            $total    = $leads['total'] ?? 0;
            $comResumo = count($leads['leads_com_demanda'] ?? []);
            $convertidos = $leads['convertidos_para_cliente'] ?? 0;
            $linhas[] = "✓ LEADS: {$total} total, {$comResumo} com resumo de demanda (qualitativo), {$convertidos} convertidos para cliente";
            if ($comResumo === 0 && $total > 0) {
                $linhas[] = "  ⚠ leads_com_demanda vazio — analise os agregados por_area, por_intencao_contratar, por_gatilho_emocional, por_potencial_honorarios";
            }
        }

        // WhatsApp
        $wa = $dados['whatsapp'] ?? [];
        if (isset($wa['erro'])) {
            $linhas[] = "✗ WHATSAPP: falha na coleta — {$wa['erro']}";
        } else {
            $nConv    = $wa['total_conversas_analisadas'] ?? 0;
            $nCritico = $wa['conversas_criticas_urgentes'] ?? 0;
            $linhas[] = "✓ WHATSAPP: {$nConv} conversas com conteúdo analisável, {$nCritico} críticas/urgentes";
            if ($nConv === 0) {
                $linhas[] = "  ⚠ sem conteúdo de mensagens — analise dados agregados do nexo (volume, status, qa_scores, tempo_resposta)";
            }
        }

        // NEXO
        $nexo = $dados['nexo'] ?? [];
        if (!isset($nexo['erro'])) {
            $nQa = count($nexo['qa_scores'] ?? []);
            $tempoResp = $nexo['tempo_medio_resposta_min'] ?? 0;
            $linhas[] = "✓ NEXO: {$nQa} registros de QA, tempo médio de resposta {$tempoResp}min";
        }

        // Mercado (notícias)
        $mercado = $dados['mercado'] ?? [];
        if (isset($mercado['erro'])) {
            $linhas[] = "✗ MERCADO: falha na coleta — {$mercado['erro']}";
        } else {
            $nNoticias = $mercado['total_noticias'] ?? 0;
            $linhas[] = "✓ MERCADO: {$nNoticias} notícias do setor jurídico coletadas";
        }

        // GA
        $ga = $dados['ga'] ?? [];
        if (($ga['configurado'] ?? false) === false) {
            $linhas[] = "✗ GOOGLE ANALYTICS: não configurado (ignorar esta fonte)";
        } elseif (isset($ga['erro'])) {
            $linhas[] = "✗ GOOGLE ANALYTICS: falha na coleta — {$ga['erro']}";
        } else {
            $sessoes  = number_format($ga['visao_geral']['sessions'] ?? 0);
            $usuarios = number_format($ga['visao_geral']['active_users'] ?? 0);
            $linhas[] = "✓ GOOGLE ANALYTICS: {$sessoes} sessões, {$usuarios} usuários ativos";
        }

        $linhas[] = "";
        $linhas[] = "INSTRUÇÃO CRÍTICA: Os dados marcados com ✓ ESTÃO DISPONÍVEIS e DEVEM ser analisados.";
        $linhas[] = "Nunca diga 'sem dados' para uma seção marcada com ✓. Se campos qualitativos estiverem vazios (⚠),";
        $linhas[] = "analise os dados quantitativos disponíveis e informe explicitamente o que está e o que não está disponível.";

        return implode("\n", $linhas);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Você é o principal conselheiro estratégico do escritório Mayer Sociedade de Advogados (Itajaí, SC).
Seu trabalho é produzir, a cada 15 dias, um relatório de inteligência executivo que Rafael Mayer — sócio-fundador e CEO — usa para tomar decisões reais de gestão.

CONTEXTO DO ESCRITÓRIO:
- Advocacia em Direito do Trabalho (área principal), Cível e Empresarial
- Atuação em Itajaí, Florianópolis, Balneário Camboriú e São Paulo
- Equipe de advogados avaliada pelo sistema GDP (score multidimensional: jurídico, financeiro, atendimento, desenvolvimento)
- Captação via Google Ads, WhatsApp e indicações
- Carteira ativa com mais de 1.600 processos
- Comunicação com clientes via WhatsApp (plataforma NEXO)

SEU PAPEL:
Você não resume planilhas. Você interpreta, cruza dados de fontes distintas, identifica tensões, detecta padrões que o gestor não veria sozinho, e traduz tudo em linguagem executiva direta — sem eufemismos, sem rodeios.

REGRAS OBRIGATÓRIAS:
1. Analise os dados com ceticismo: números bons podem esconder problemas estruturais; números ruins podem ser circunstanciais.
2. Sempre que possível, cruze fontes: WhatsApp × GDP × Financeiro × Leads × Processos × Mercado. Os cruzamentos revelam o que os silos escondem.
3. Nomeie pessoas e situações concretas quando os dados permitirem. "A equipe melhorou" é inútil. "João subiu 12 pontos no GDP puxado pelo indicador de retorno de clientes" é acionável.
4. Identifique pelo menos 2 alertas que o gestor provavelmente não percebeu ainda.
5. As recomendações devem ser decisões reais — não sugestões vagas. "Avaliar X" não é recomendação. "Renegociar contrato com fornecedor Y até 30/05" é.
6. O relatório deve ser útil para quem não viu os dados — contextualize os números.
7. NUNCA declare ausência de dados para uma seção que o inventário marcou como disponível (✓). Se campos qualitativos estiverem vazios, diga isso explicitamente mas analise os quantitativos que existem.
8. Use o campo 'financeiro.top_clientes_receita' para analisar concentração de receita por cliente. Use 'financeiro.top_devedores' para nomear quem deve.
9. Use o campo 'mercado.noticias' para contextualizar variações de volume de leads ou tipos de ação — eventos jurídicos explicam sazonalidade.
10. Se 'ga' estiver disponível, cruze ga.por_canal com leads.por_canal para calcular conversion rate por origem.

RETORNE EXCLUSIVAMENTE um JSON válido, sem texto antes ou depois, no formato especificado na mensagem do usuário.
PROMPT;
    }

    private function userPrompt(array $dados): string
    {
        $periodo   = $dados['periodo']['label'] ?? 'período não informado';
        $dadosJson = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $inventario = $this->gerarInventario($dados);

        return <<<JSON
Período analisado: {$periodo}

{$inventario}

DADOS COLETADOS (JSON completo):
{$dadosJson}

---

Produza o relatório de inteligência executivo no seguinte formato JSON (retorne SOMENTE o JSON, sem markdown):

{
  "score_geral": <inteiro 1-10 refletindo a saúde geral do escritório no período>,
  "titulo_periodo": "<título executivo conciso que capture a essência do período — ex: 'Crescimento de receita mascarado por inadimplência crescente'>",

  "resumo_executivo": "<3 a 5 parágrafos. Primeiro parágrafo: o quadro geral do período em linguagem direta. Segundo: os 2-3 movimentos mais importantes detectados. Terceiro: o que preocupa e por quê. Quarto (opcional): o que surpreendeu positivamente. Quinto: o que requer decisão imediata do CEO.>",

  "voz_dos_clientes": {
    "analise": "<Análise profunda do conteúdo real das conversas de WhatsApp. Quais temas repetem? Qual o tom predominante (urgência, insatisfação, gratidão, confusão)? Há situações críticas não resolvidas? Quais problemas operacionais estão aparecendo nas mensagens dos clientes? O que os clientes mais pedem que o escritório não está entregando bem?>",
    "temas_criticos": ["<tema 1 com evidência do dado>", "<tema 2>", "<tema 3>"],
    "oportunidade_identificada": "<O que o padrão de mensagens revela como oportunidade de melhoria ou novo serviço?>",
    "alertas": ["<situação urgente específica que precisa de ação>"]
  },

  "inteligencia_de_mercado": {
    "perfil_leads_periodo": "<Quem está chegando? Qual área jurídica domina? Qual gatilho emocional prevalece? Qual o perfil socioeconômico predominante? O que isso diz sobre o posicionamento atual do escritório?>",
    "qualidade_captacao": "<Os leads são qualificados? Intenção de contratar alta ou baixa? Comparar potencial de honorários com complexidade — vale o custo de captação?>",
    "campanhas_eficazes": "<Quais canais e campanhas trazem leads melhores (cruzar canal de origem com qualidade do lead)?>",
    "oportunidades": ["<oportunidade de posicionamento ou nicho subutilizado>"],
    "alertas": ["<problema de captação ou conversão identificado>"]
  },

  "financeiro": {
    "situacao": "<excelente|bom|neutro|atenção|crítico>",
    "analise": "<2-3 parágrafos densos. Receita, margem, inadimplência, tendência. Mencione explicitamente os top clientes por receita (financeiro.top_clientes_receita) e o percentual de concentração. Nomeie os maiores devedores (financeiro.top_devedores). A variação frente ao período anterior é estrutural ou circunstancial?>",
    "concentracao_receita": "<Análise de concentração: os top 3 clientes respondem por X% da receita — qual o risco disso? Quem são?>",
    "inadimplencia_detalhe": "<Quem deve? Nomeie os devedores com valor e quantidade de títulos. Quando venceu? Há risco de calote estrutural?>",
    "riscos_identificados": ["<risco específico com dado>"],
    "recomendacoes": ["<decisão concreta, não sugestão>"]
  },

  "performance_equipe": {
    "analise_geral": "<Avaliação da equipe como um todo: score médio GDP, tendência, dispersão entre melhores e piores. A equipe está evoluindo ou estagnando?>",
    "destaques": [
      {
        "nome": "<nome do advogado>",
        "tipo": "<destaque_positivo|ponto_critico>",
        "analise": "<o que fez bem ou mal, com dados concretos de GDP, QA, penalizações>"
      }
    ],
    "cruzamento_gdp_nexo": "<Há advogados com alto score jurídico mas baixa qualidade no atendimento (QA NEXO)? Ou o contrário? Esse cruzamento revela quem é completo vs. quem é especialista unilateral.>",
    "recomendacoes": ["<decisão de gestão de pessoas concreta>"]
  },

  "carteira_processos": {
    "situacao": "<saudável|atenção|crítica>",
    "analise": "<Dimensão da carteira, valor, concentração por tipo e por advogado. Há prazos fatais vencidos? Quem está sobrecarregado? Quais tipos de ação têm mais andamentos — sinal de onde o escritório está mais ativo?>",
    "riscos_prazos": "<Análise dos prazos críticos próximos e vencidos — isso é exposição jurídica e reputacional.>",
    "oportunidades": ["<oportunidade estratégica na carteira>"],
    "alertas": ["<risco concreto que precisa de ação>"]
  },

  "cruzamentos_estrategicos": [
    {
      "titulo": "<nome do cruzamento — ex: 'Leads trabalhistas vs. capacidade da equipe'>",
      "analise": "<Insight gerado pelo cruzamento de duas ou mais fontes de dados que não seria visível em nenhuma fonte isolada>",
      "implicacao": "<O que o gestor deve fazer com essa informação>"
    }
  ],

  "recomendacoes_priorizadas": [
    {
      "prioridade": 1,
      "area": "<área: financeiro|equipe|operacional|marketing|juridico|cliente>",
      "decisao": "<A decisão em uma frase imperativa: 'Fazer X até Y'>",
      "por_que_agora": "<Por que essa decisão é urgente neste momento específico>",
      "impacto_esperado": "<Resultado esperado em termos concretos>",
      "prazo": "<imediato|7 dias|15 dias|30 dias|60 dias>"
    }
  ],

  "contexto_mercado": {
    "noticias_relevantes": "<Há notícias do setor jurídico (mercado.noticias) que explicam variações observadas? Ex: nova lei, reforma, jurisprudência que aumentou volume de determinada área?>",
    "implicacao_estrategica": "<Como os eventos externos identificados devem influenciar decisões do escritório no próximo ciclo?>"
  },

  "o_que_monitorar_proximo_periodo": ["<métrica ou situação específica a acompanhar>"]
}
JSON;
    }

    private function parseJson(string $texto): array
    {
        $texto = preg_replace('/^```(?:json)?\s*/m', '', $texto);
        $texto = preg_replace('/\s*```$/m', '', $texto);
        $texto = trim($texto);

        $parsed = json_decode($texto, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            return $parsed;
        }

        if (preg_match('/\{[\s\S]*\}/m', $texto, $match)) {
            $parsed = json_decode($match[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return $parsed;
            }
        }

        Log::error('RelatorioCeo ClaudeAnalysis: falha ao parsear JSON', ['texto' => substr($texto, 0, 500)]);
        return [];
    }
}
