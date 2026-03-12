<?php

namespace App\Services;

use App\Models\PricingProposal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SIPEX v2.0 — ProposalClaudeService
 *
 * Gera propostas de honorarios persuasivas via API Anthropic (Claude Sonnet).
 * Substituiu a geracao via OpenAI (PricingAIService::gerarTextoPropostaCliente)
 * que possuia bug no parse de resposta (acessava choices[0] em retorno ja parseado).
 *
 * Fluxo:
 * 1. sugerirConfiguracao() — IA pre-preenche modal de configuracao
 * 2. gerarProposta() — Com configs confirmadas pelo advogado, gera JSON estruturado
 *
 * Dependencias: JUSTUS_ANTHROPIC_API_KEY, JUSTUS_CLAUDE_MODEL (.env)
 * Tabela: pricing_proposals (coluna texto_proposta_cliente)
 *
 * @since 12/03/2026
 */
class ProposalClaudeService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = env('JUSTUS_ANTHROPIC_API_KEY', '');
        $this->model = env('JUSTUS_CLAUDE_MODEL', 'claude-sonnet-4-5-20250929');
    }

    /**
     * Etapa 1: Sugere configuracoes para o modal (IA pre-preenche, advogado confirma)
     */
    public function sugerirConfiguracao(PricingProposal $proposta): array
    {
        $escolhida = $proposta->proposta_escolhida ?? 'equilibrada';
        $propostaData = $proposta->{'proposta_' . $escolhida} ?? [];
        $valor = $propostaData['valor_honorarios'] ?? 0;
        $parcelas = $propostaData['parcelas_sugeridas'] ?? 1;
        $tipoCobranca = $propostaData['tipo_cobranca'] ?? 'fixo';

        $rapida = $proposta->proposta_rapida ?? [];
        $equilibrada = $proposta->proposta_equilibrada ?? [];
        $premium = $proposta->proposta_premium ?? [];

        $dados = [
            'area_direito' => $proposta->area_direito,
            'tipo_acao' => $proposta->tipo_acao,
            'descricao_demanda' => $proposta->descricao_demanda,
            'valor_causa' => $proposta->valor_causa,
            'valor_economico' => $proposta->valor_economico,
            'tipo_pessoa' => $proposta->tipo_pessoa,
            'contexto_adicional' => $proposta->contexto_adicional,
        ];

        $systemPrompt = 'Voce e um consultor senior de precificacao juridica. Analise os dados do caso e sugira configuracoes para a proposta de honorarios. Responda APENAS em JSON valido, sem markdown, sem texto fora do JSON.

Formato obrigatorio:
{
  "valor_honorarios": 0.00,
  "tipo_cobranca": "fixo|mensal|misto",
  "parcelas": 1,
  "incluir_exito": true,
  "exito_tipo": "percentual|fixo",
  "exito_valor": "20%",
  "exito_condicao": "Descricao da condicao de exito",
  "horas_estimadas_min": 0,
  "horas_estimadas_max": 0,
  "escopo": "Descricao do escopo sugerido",
  "escopo_opcoes": ["1a instancia ate sentenca", "Recurso 2a instancia", "Cumprimento de sentenca", "Consultoria extrajudicial", "Acao completa todas instancias"],
  "estrategia_resumo": "1-2 frases sobre a abordagem juridica",
  "despesas_sugeridas": ["Custas judiciais", "Honorarios periciais", "Deslocamentos fora da comarca", "Correios e reprografia"],
  "vigencia_dias": 15,
  "incluir_tabela_horas": false
}';

        $userPrompt = "Dados do caso:\n" . json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $userPrompt .= "\n\nProposta escolhida pelo advogado: {$escolhida}";
        $userPrompt .= "\nValor da proposta escolhida: R$ " . number_format($valor, 2, ',', '.');
        $userPrompt .= "\nParcelas sugeridas: {$parcelas}";
        $userPrompt .= "\nTipo cobranca: {$tipoCobranca}";
        $userPrompt .= "\n\nFaixas disponiveis:";
        $userPrompt .= "\n- Rapida: R$ " . number_format($rapida['valor_honorarios'] ?? 0, 2, ',', '.');
        $userPrompt .= "\n- Equilibrada: R$ " . number_format($equilibrada['valor_honorarios'] ?? 0, 2, ',', '.');
        $userPrompt .= "\n- Premium: R$ " . number_format($premium['valor_honorarios'] ?? 0, 2, ',', '.');

        $result = $this->callClaude($systemPrompt, $userPrompt, 2000);

        if (isset($result['erro'])) {
            // Fallback: retorna defaults razoaveis
            return [
                'valor_honorarios' => $valor,
                'tipo_cobranca' => $tipoCobranca,
                'parcelas' => $parcelas,
                'incluir_exito' => false,
                'exito_tipo' => 'percentual',
                'exito_valor' => '20%',
                'exito_condicao' => '',
                'horas_estimadas_min' => 0,
                'horas_estimadas_max' => 0,
                'escopo' => '1a instancia ate sentenca',
                'escopo_opcoes' => ['1a instancia ate sentenca', 'Recurso 2a instancia', 'Cumprimento de sentenca', 'Consultoria extrajudicial'],
                'estrategia_resumo' => '',
                'despesas_sugeridas' => ['Custas judiciais', 'Honorarios periciais'],
                'vigencia_dias' => 15,
                'incluir_tabela_horas' => false,
                'faixa_min' => $rapida['valor_honorarios'] ?? 0,
                'faixa_max' => $premium['valor_honorarios'] ?? 0,
                '_fallback' => true,
                '_erro' => $result['erro'],
            ];
        }

        $parsed = $this->parseJson($result['content']);
        $parsed['faixa_min'] = $rapida['valor_honorarios'] ?? 0;
        $parsed['faixa_max'] = $premium['valor_honorarios'] ?? 0;

        return $parsed;
    }

    /**
     * Etapa 2: Gera o texto completo da proposta com as configs confirmadas
     */
    public function gerarProposta(PricingProposal $proposta, array $config): array
    {
        $systemPrompt = $this->buildProposalSystemPrompt();

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
        ];

        $userPrompt = "DADOS DO CASO:\n" . json_encode($dadosCaso, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $userPrompt .= "\n\nCONFIGURACOES CONFIRMADAS PELO ADVOGADO:\n" . json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $result = $this->callClaude($systemPrompt, $userPrompt, 8000);

        if (isset($result['erro'])) {
            return ['error' => $result['erro']];
        }

        $parsed = $this->parseJson($result['content']);

        if (empty($parsed) || !isset($parsed['saudacao'])) {
            Log::error('SIPEX ProposalClaude: JSON sem campo saudacao', ['raw' => $result['content']]);
            return ['error' => 'A IA retornou formato invalido. Tente novamente.'];
        }

        return $parsed;
    }

    private function buildProposalSystemPrompt(): string
    {
        return 'Voce e um redator juridico senior do escritorio Mayer Sociedade de Advogados (OAB/SC 2097), com sede em Itajai/SC e atuacao em Florianopolis e Sao Paulo. Sua funcao e redigir propostas de honorarios que convertem leads em clientes.

SOBRE O ESCRITORIO (use naturalmente no texto):
- Escritorio fundado com foco em Direito do Trabalho, Civel e Empresarial
- Equipe multidisciplinar com advogados especializados
- Atuacao em Santa Catarina (Itajai, Florianopolis, Blumenau, Balneario Camboriu) e Sao Paulo
- Utilizacao de tecnologia e inteligencia artificial para analise juridica e gestao de processos
- Atendimento personalizado com acompanhamento proximo de cada caso
- Compromisso com transparencia nos honorarios e prestacao de contas

REGRAS DE ESTILO:
1. Tom: confiante, tecnico mas acessivel, empatico com a dor do cliente, transmitindo autoridade e seguranca.
2. NUNCA use bullet points, listas numeradas ou markdown. Redija em prosa fluida, em paragrafos completos.
3. Escrita em 3a pessoa ("O Escritorio", "A equipe juridica"). Nunca "nos" ou "eu".
4. Use gatilhos de persuasao: escassez de tempo (prazos legais), autoridade (experiencia), prova social (resultados na area), reciprocidade (diagnostico ja entregue), urgencia (consequencias da inacao).
5. O diagnostico deve demonstrar que o escritorio JA entendeu profundamente o caso.
6. A secao de diferenciais deve ser sutil e integrada, nao uma lista de autoelogio.
7. O valor dos honorarios deve ser apresentado com naturalidade e justificado pelo valor que entrega.
8. Se o advogado confirmou "incluir_tabela_horas": true, gere o campo "fases_horas" com tabela detalhada de atividades e horas por fase (como no exemplo abaixo). Se false, gere apenas "fases" simplificado.

FORMATO DE RESPOSTA - JSON com estas chaves (responda APENAS JSON, sem markdown, sem texto fora):
{
  "saudacao": "Paragrafo de abertura cordial e personalizado",
  "contexto_demanda": "Paragrafo(s) mostrando que o escritorio entendeu a situacao",
  "diagnostico": "Analise tecnica preliminar demonstrando dominio da materia",
  "escopo_servicos": "Descricao detalhada do que esta incluido. Mencione o escopo confirmado pelo advogado.",
  "fases": [
    {"nome": "Fase 1 - Titulo", "descricao": "O que sera feito nesta fase"}
  ],
  "fases_horas": [
    {
      "nome": "Fase 1 - Preparacao e distribuicao",
      "atividades": [
        {"atividade": "Dossiê final para inicial", "descricao": "Consolidacao de documentos e evidencias", "horas_min": 4, "horas_max": 6},
        {"atividade": "Peticao inicial com tutela", "descricao": "Fatos, direito, pedidos e documentos", "horas_min": 5, "horas_max": 7}
      ],
      "subtotal_min": 9,
      "subtotal_max": 13
    }
  ],
  "estrategia": "Abordagem juridica que sera adotada",
  "honorarios": {
    "descricao_valor": "Pro-labore: R$ X.XXX,XX",
    "forma_pagamento": "Condicoes de pagamento detalhadas",
    "observacao": "Nota sobre o que esta incluso no valor e sobre as horas estimadas"
  },
  "honorarios_exito": "Paragrafo sobre honorarios de exito (ou null se nao aplicavel)",
  "despesas": "Paragrafo sobre custas e despesas processuais reembolsaveis",
  "diferenciais": "Por que este escritorio e a melhor escolha (sutil, integrado ao contexto do caso)",
  "vigencia": "Condicoes de vigencia, confidencialidade e proximos passos",
  "encerramento": "Paragrafo final cordial com call-to-action"
}

REGRAS CRITICAS:
- Se "incluir_tabela_horas" for false, NAO inclua o campo "fases_horas" no JSON.
- Se "incluir_tabela_horas" for true, inclua "fases_horas" COM atividades detalhadas e horas min/max, e tambem inclua "fases" simplificado.
- O campo "honorarios.descricao_valor" deve usar o valor EXATO confirmado pelo advogado em "valor_honorarios".
- Se "incluir_exito" for true, preencha "honorarios_exito" com os dados de "exito_tipo", "exito_valor" e "exito_condicao". Se false, retorne null.
- Parcelas e tipo de cobranca devem refletir EXATAMENTE o que o advogado confirmou.
- O escopo deve refletir o que o advogado selecionou em "escopo".
- "despesas" deve mencionar os itens confirmados em "despesas_selecionadas".
- "vigencia" deve usar "vigencia_dias" para definir a validade da proposta.
- Se houver "observacoes_advogado", integre naturalmente no texto.';
    }

    /**
     * Chama a API Anthropic (Claude)
     */
    private function callClaude(string $systemPrompt, string $userPrompt, int $maxTokens = 4000): array
    {
        if (empty($this->apiKey)) {
            return ['erro' => 'JUSTUS_ANTHROPIC_API_KEY nao configurada no .env'];
        }

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->model,
                    'max_tokens' => $maxTokens,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            if ($response->successful()) {
                $content = $response->json('content.0.text') ?? '';
                $usage = $response->json('usage') ?? [];
                Log::info('SIPEX ProposalClaude: OK', [
                    'model' => $this->model,
                    'input_tokens' => $usage['input_tokens'] ?? 0,
                    'output_tokens' => $usage['output_tokens'] ?? 0,
                ]);
                return ['content' => $content];
            }

            $status = $response->status();
            $body = $response->body();
            Log::error('SIPEX ProposalClaude: API erro', ['status' => $status, 'body' => $body]);
            return ['erro' => "API Anthropic retornou status {$status}"];

        } catch (\Exception $e) {
            Log::error('SIPEX ProposalClaude: Exception', ['msg' => $e->getMessage()]);
            return ['erro' => 'Erro de conexao com API Anthropic: ' . $e->getMessage()];
        }
    }

    private function parseJson(string $raw): array
    {
        $clean = trim($raw);
        $clean = preg_replace('/^```json\s*/', '', $clean);
        $clean = preg_replace('/^```\s*/', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);
        $clean = trim($clean);

        $parsed = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('SIPEX ProposalClaude: JSON parse error', ['error' => json_last_error_msg(), 'raw' => substr($raw, 0, 500)]);
            return [];
        }

        return $parsed;
    }
}
