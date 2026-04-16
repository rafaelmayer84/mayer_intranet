<?php

namespace App\Services\RelatorioCeo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeAnalysisService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = env('JUSTUS_ANTHROPIC_API_KEY', '');
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
                'model'      => 'claude-opus-4-7',
                'max_tokens' => 20000,
                'thinking'   => ['type' => 'adaptive', 'budget_tokens' => 10000],
                'system'     => $this->systemPrompt(),
                'messages'   => [
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
2. Sempre que possível, cruze fontes: WhatsApp × GDP × Financeiro × Leads × Processos. Os cruzamentos revelam o que os silos escondem.
3. Nomeie pessoas e situações concretas quando os dados permitirem. "A equipe melhorou" é inútil. "João subiu 12 pontos no GDP puxado pelo indicador de retorno de clientes" é acionável.
4. Identifique pelo menos 2 alertas que o gestor provavelmente não percebeu ainda.
5. As recomendações devem ser decisões reais — não sugestões vagas. "Avaliar X" não é recomendação. "Renegociar contrato com fornecedor Y até 30/05" é.
6. O relatório deve ser útil para quem não viu os dados — contextualize os números.

RETORNE EXCLUSIVAMENTE um JSON válido, sem texto antes ou depois, no formato especificado na mensagem do usuário.
PROMPT;
    }

    private function userPrompt(array $dados): string
    {
        $periodo   = $dados['periodo']['label'] ?? 'período não informado';
        $dadosJson = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Instrução para análise das conversas WhatsApp
        $instrucaoWhatsApp = '';
        $totalConversas = $dados['whatsapp']['total_conversas_analisadas'] ?? 0;
        if ($totalConversas > 0) {
            $instrucaoWhatsApp = "\n\nATENÇÃO: O campo 'whatsapp.conversas' contém o conteúdo REAL das mensagens enviadas pelos clientes no período. Leia cada conversa, identifique padrões, problemas recorrentes, situações urgentes não resolvidas, e sentimentos predominantes. Isso é a voz dos clientes — use-a.";
        }

        $instrucaoLeads = '';
        $totalLeads = $dados['leads']['total'] ?? 0;
        if ($totalLeads > 0) {
            $instrucaoLeads = "\n\nO campo 'leads.leads_com_demanda' contém resumos reais das demandas de cada lead, com gatilho emocional, potencial de honorários e intenção de contratar. Analise o perfil qualitativo desses leads para identificar oportunidades de posicionamento e gargalos de conversão.";
        }

        return <<<JSON
Período analisado: {$periodo}{$instrucaoWhatsApp}{$instrucaoLeads}

DADOS COLETADOS:
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
    "analise": "<2-3 parágrafos densos. Receita, margem, inadimplência, tendência. O que está crescendo e o que está encolhendo? Há concentração de receita em poucos clientes? A variação frente ao período anterior é estrutural ou circunstancial?>",
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
