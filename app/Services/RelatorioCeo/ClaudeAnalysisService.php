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

        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt   = $this->buildUserPrompt($dados);

        $response = Http::connectTimeout(30)->timeout(600)
            ->withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model'         => 'claude-opus-4-7',
                'max_tokens'    => 16000,
                'thinking'      => ['type' => 'adaptive'],
                'output_config' => ['effort' => 'high'],
                'system'   => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if (!$response->successful()) {
            $body = $response->body();
            Log::error('RelatorioCeo ClaudeAnalysis: API error', ['status' => $response->status(), 'body' => substr($body, 0, 500)]);
            throw new \RuntimeException("Claude API retornou HTTP {$response->status()}: " . substr($body, 0, 200));
        }

        $json = $response->json();

        // Extended thinking: filtrar bloco 'text'
        $texto = '';
        foreach ($json['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $texto = $block['text'];
                break;
            }
        }

        if (empty($texto)) {
            throw new \RuntimeException('Claude não retornou bloco text na resposta.');
        }

        $analise = $this->parseJson($texto);

        if (empty($analise)) {
            throw new \RuntimeException('Falha ao parsear JSON da análise: ' . substr($texto, 0, 300));
        }

        return $analise;
    }

    private function buildSystemPrompt(): string
    {
        return 'Você é o consultor estratégico sênior do escritório Mayer Sociedade de Advogados, localizado em Itajaí, SC. Sua função é produzir relatórios executivos quinzenais de alta qualidade para o CEO Rafael Mayer.

Contexto do escritório:
- Especializado em Direito do Trabalho, Cível e Empresarial
- Atuação em Itajaí, Florianópolis, Balneário Camboriú e São Paulo
- Equipe de advogados com sistema de gestão de desempenho (GDP)
- Comunicação com clientes via WhatsApp (plataforma NEXO)
- Carteira ativa com mais de 1.600 processos

Você DEVE analisar os dados fornecidos com profundidade e retornar EXCLUSIVAMENTE um JSON válido no formato especificado, sem texto antes ou depois do JSON.';
    }

    private function buildUserPrompt(array $dados): string
    {
        $dadosJson = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return "Analise os dados abaixo e retorne um JSON com a análise executiva completa.\n\n" .
            "DADOS COLETADOS:\n{$dadosJson}\n\n" .
            "Retorne SOMENTE o seguinte JSON (sem markdown, sem explicações):\n" .
            $this->buildJsonSchema();
    }

    private function buildJsonSchema(): string
    {
        return '{
  "resumo_executivo": {
    "score_geral": <número 1-10>,
    "titulo": "<título executivo do período>",
    "destaques_positivos": ["<destaque 1>", "<destaque 2>", "<destaque 3>"],
    "alertas_criticos": ["<alerta 1>", "<alerta 2>"],
    "visao_geral": "<parágrafo executivo de 3-4 frases resumindo o período>"
  },
  "financeiro": {
    "situacao": "<excelente|bom|neutro|atenção|crítico>",
    "analise": "<análise financeira detalhada em 2-3 parágrafos>",
    "pontos_atencao": ["<ponto 1>", "<ponto 2>"],
    "recomendacoes": ["<recomendação 1>", "<recomendação 2>"]
  },
  "atendimentos": {
    "qualidade_geral": "<excelente|boa|regular|ruim>",
    "perfil_publico": "<descrição do perfil do público que está chegando>",
    "analise_canais": "<análise dos canais de comunicação>",
    "analise_qualidade": "<análise da qualidade dos atendimentos>",
    "destaques_atendentes": "<quem mais atendeu e como foi a performance>",
    "recomendacoes": ["<recomendação 1>", "<recomendação 2>"]
  },
  "performance_advogados": {
    "analise_geral": "<análise da equipe jurídica>",
    "destaque_positivo": "<nome e o que fez bem>",
    "ponto_melhoria": "<nome e o que precisa melhorar>",
    "recomendacoes": ["<recomendação 1>", "<recomendação 2>"]
  },
  "carteira_processos": {
    "situacao": "<saudável|atenção|crítica>",
    "analise": "<análise da carteira de processos em 2 parágrafos>",
    "riscos": ["<risco 1>", "<risco 2>"],
    "oportunidades": ["<oportunidade 1>", "<oportunidade 2>"],
    "recomendacoes": ["<recomendação 1>", "<recomendação 2>"]
  },
  "mercado": {
    "contexto_regional": "<análise do contexto do mercado jurídico em Itajaí/SC>",
    "tendencias": ["<tendência 1>", "<tendência 2>", "<tendência 3>"],
    "oportunidades_negocio": ["<oportunidade 1>", "<oportunidade 2>"],
    "ameacas": ["<ameaça 1>"]
  },
  "marketing": {
    "analise": "<análise do marketing e aquisição de clientes cruzando dados GA4 + WhatsApp>",
    "perfil_leads": "<perfil dos potenciais clientes chegando (fontes web + WhatsApp)>",
    "canais_efetivos": ["<canal 1>", "<canal 2>"],
    "tendencia_trafego": "<resumo da tendência de tráfego web no período vs anterior>",
    "recomendacoes": ["<recomendação 1>", "<recomendação 2>"]
  },
  "recomendacoes_gerenciais": [
    {
      "prioridade": 1,
      "area": "<área>",
      "decisao": "<decisão recomendada clara e objetiva>",
      "justificativa": "<por quê esta decisão agora>",
      "impacto_esperado": "<impacto esperado>",
      "prazo_sugerido": "<imediato|15 dias|30 dias|60 dias>"
    }
  ]
}';
    }

    private function parseJson(string $texto): array
    {
        // Remove blocos markdown se presentes
        $texto = preg_replace('/^```(?:json)?\s*/m', '', $texto);
        $texto = preg_replace('/\s*```$/m', '', $texto);
        $texto = trim($texto);

        // Tenta parsear diretamente
        $parsed = json_decode($texto, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            return $parsed;
        }

        // Tenta extrair JSON entre chaves
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
