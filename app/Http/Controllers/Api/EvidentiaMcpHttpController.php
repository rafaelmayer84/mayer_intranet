<?php

// ESTÁVEL desde 16/04/2026
//
// ┌─────────────────────────────────────────────────────────────────────────┐
// │  EvidentiaMcpHttpController — Servidor MCP via HTTP  v1.0              │
// ├─────────────────────────────────────────────────────────────────────────┤
// │  Implementa o protocolo MCP Streamable HTTP (spec 2024-11-05) direto   │
// │  no Laravel. Claude Desktop conecta via URL — sem Node.js no servidor. │
// ├─────────────────────────────────────────────────────────────────────────┤
// │  Endpoint único                                                         │
// │  POST /api/mcp/evidentia  → JSON-RPC 2.0                               │
// ├─────────────────────────────────────────────────────────────────────────┤
// │  Métodos MCP suportados                                                 │
// │  initialize              → retorna capabilities do servidor             │
// │  tools/list              → lista as 3 tools disponíveis                │
// │  tools/call              → executa a tool solicitada                   │
// │  notifications/*         → aceita silenciosamente (204)                │
// ├─────────────────────────────────────────────────────────────────────────┤
// │  Segurança                                                              │
// │  - Bearer token: EVIDENTIA_MCP_TOKEN (middleware evidentia.mcp)        │
// │  - HTTPS enforçado pelo domínio                                        │
// │  - Throttle: 30 req/min                                                │
// │  - set_time_limit(180) para buscas longas (multi-step OpenAI)          │
// ├─────────────────────────────────────────────────────────────────────────┤
// │  Config Claude Desktop (claude_desktop_config.json)                    │
// │  {                                                                     │
// │    "mcpServers": {                                                     │
// │      "evidentia": {                                                    │
// │        "type": "http",                                                 │
// │        "url": "https://intranet.mayeradvogados.adv.br/api/mcp/evidentia"│
// │        "headers": { "Authorization": "Bearer <EVIDENTIA_MCP_TOKEN>" } │
// │      }                                                                 │
// │    }                                                                   │
// │  }                                                                     │
// └─────────────────────────────────────────────────────────────────────────┘

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvidentiaSearch;
use App\Services\Evidentia\EvidentiaCitationService;
use App\Services\Evidentia\EvidentiaSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class EvidentiaMcpHttpController extends Controller
{
    private const PROTOCOL_VERSION = '2024-11-05';
    private const SERVER_NAME      = 'evidentia';
    private const SERVER_VERSION   = '2.1.0';

    /**
     * Ponto de entrada único — aceita JSON-RPC 2.0 simples ou batch.
     */
    public function handle(Request $request, EvidentiaSearchService $searchService, EvidentiaCitationService $citationService): Response|JsonResponse
    {
        // Buscas completas podem levar até 60s (pipeline OpenAI multi-step)
        set_time_limit(180);

        $body = $request->json()->all();

        if (empty($body)) {
            return $this->errorResponse(null, -32700, 'Parse error: empty body');
        }

        // Batch request (array de mensagens)
        if (array_is_list($body) && isset($body[0]) && is_array($body[0])) {
            $responses = array_filter(
                array_map(fn($msg) => $this->dispatch($msg, $searchService, $citationService), $body)
            );
            return response()->json(array_values($responses))
                ->header('Content-Type', 'application/json');
        }

        // Mensagem única
        $result = $this->dispatch($body, $searchService, $citationService);

        // Notificações não têm resposta
        if ($result === null) {
            return response('', 204);
        }

        return response()->json($result)
            ->header('Content-Type', 'application/json');
    }

    // ─── Roteamento de métodos ─────────────────────────────────────────────

    private function dispatch(array $message, EvidentiaSearchService $searchService, EvidentiaCitationService $citationService): ?array
    {
        $id     = $message['id'] ?? null;
        $method = $message['method'] ?? '';
        $params = $message['params'] ?? [];

        // Notificações (sem id) — silenciosas
        if ($id === null && str_starts_with($method, 'notifications/')) {
            return null;
        }

        return match ($method) {
            'initialize'             => $this->handleInitialize($id, $params),
            'tools/list'             => $this->handleListTools($id),
            'tools/call'             => $this->handleCallTool($id, $params, $searchService, $citationService),
            'ping'                   => $this->okResponse($id, []),
            default                  => $this->errorResponse($id, -32601, "Method not found: {$method}"),
        };
    }

    // ─── Handlers MCP ─────────────────────────────────────────────────────

    private function handleInitialize(mixed $id, array $params): array
    {
        return $this->okResponse($id, [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities'    => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name'    => self::SERVER_NAME,
                'version' => self::SERVER_VERSION,
            ],
        ]);
    }

    private function handleListTools(mixed $id): array
    {
        return $this->okResponse($id, [
            'tools' => $this->toolDefinitions(),
        ]);
    }

    private function handleCallTool(mixed $id, array $params, EvidentiaSearchService $searchService, EvidentiaCitationService $citationService): array
    {
        $name      = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        try {
            $text = match ($name) {
                'evidentia_buscar'         => $this->toolBuscar($arguments, $searchService),
                'evidentia_resultado'      => $this->toolResultado($arguments),
                'evidentia_gerar_citacao'  => $this->toolCitacao($arguments, $citationService),
                default                    => throw new \InvalidArgumentException("Tool desconhecida: {$name}"),
            };

            return $this->okResponse($id, [
                'content' => [['type' => 'text', 'text' => $text]],
            ]);
        } catch (\Throwable $e) {
            return $this->okResponse($id, [
                'content' => [['type' => 'text', 'text' => '❌ Erro: ' . $e->getMessage()]],
                'isError'  => true,
            ]);
        }
    }

    // ─── Implementação das tools ───────────────────────────────────────────

    private function toolBuscar(array $args, EvidentiaSearchService $searchService): string
    {
        if (empty($args['query'])) {
            throw new \InvalidArgumentException('Parâmetro "query" obrigatório.');
        }

        $this->assertBudget();

        $filters = array_filter([
            'tribunal'       => $args['tribunal'] ?? null,
            'periodo_inicio' => $args['periodo_inicio'] ?? null,
            'periodo_fim'    => $args['periodo_fim'] ?? null,
        ]);

        $search = $searchService->search(
            $args['query'],
            $filters,
            (int) ($args['topk'] ?? 10),
            null
        );

        return $this->formatSearch($search->load('results'));
    }

    private function toolResultado(array $args): string
    {
        if (empty($args['search_id'])) {
            throw new \InvalidArgumentException('Parâmetro "search_id" obrigatório.');
        }

        $search = EvidentiaSearch::with('results')->find((int) $args['search_id']);

        if (!$search) {
            throw new \RuntimeException("Busca #{$args['search_id']} não encontrada.");
        }

        return $this->formatSearch($search);
    }

    private function toolCitacao(array $args, EvidentiaCitationService $citationService): string
    {
        if (empty($args['search_id'])) {
            throw new \InvalidArgumentException('Parâmetro "search_id" obrigatório.');
        }

        $this->assertBudget();

        $search = EvidentiaSearch::with(['results', 'citationBlock'])->find((int) $args['search_id']);

        if (!$search) {
            throw new \RuntimeException("Busca #{$args['search_id']} não encontrada.");
        }

        if ($search->status !== 'complete') {
            throw new \RuntimeException('Busca ainda não concluída.');
        }

        $block = $citationService->generate($search, null);

        if (!$block) {
            throw new \RuntimeException('Não foi possível gerar o bloco de citação. Verifique se há resultados disponíveis.');
        }

        return implode("\n", [
            "📝 Bloco de citação — busca #{$search->id}",
            "Tema: \"{$search->query}\"",
            '',
            '## Síntese objetiva',
            $block->sintese_objetiva,
            '',
            '## Precedentes (pronto para inserir na peça)',
            $block->bloco_precedentes,
            '',
            'Custo: $' . round($block->cost_usd, 4) . ' USD',
        ]);
    }

    // ─── Formatação de resultados ─────────────────────────────────────────

    private function formatSearch(EvidentiaSearch $search): string
    {
        $lines = [
            "🔍 Busca: \"{$search->query}\"",
            "📊 {$search->results->count()} resultados | {$search->latency_ms}ms | \$" . round((float) $search->cost_usd, 4) . " USD",
        ];

        if ($search->degraded_mode) {
            $lines[] = '⚠️  Modo degradado (busca semântica indisponível)';
        }

        $lines[] = "search_id: {$search->id}";
        $lines[] = '';

        foreach ($search->results as $r) {
            $juris = $r->getJurisprudence();

            $lines[] = "─── #{$r->final_rank} — {$r->tribunal} | Score: " . round($r->final_score, 4);
            $lines[] = 'Processo: ' . ($juris->numero_processo ?? 'N/D');
            $lines[] = 'Classe: ' . ($juris->sigla_classe ?? '') . ' ' . ($juris->descricao_classe ?? '');
            $lines[] = 'Relator: ' . ($juris->relator ?? 'N/D') . ' | Órgão: ' . ($juris->orgao_julgador ?? 'N/D');
            $lines[] = 'Data: ' . ($juris->data_decisao ?? 'N/D');

            if ($r->rerank_justification) {
                $lines[] = "💡 {$r->rerank_justification}";
            }

            $ementa = $juris->ementa ?? '';
            $lines[] = 'Ementa: ' . mb_substr($ementa, 0, 600) . (mb_strlen($ementa) > 600 ? '...' : '');
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    // ─── Budget guard ─────────────────────────────────────────────────────

    private function assertBudget(): void
    {
        $spent = (float) Cache::get('evidentia_budget_' . now()->toDateString(), 0);
        if ($spent >= config('evidentia.daily_budget_usd')) {
            throw new \RuntimeException('Limite diário de orçamento Evidentia atingido.');
        }
    }

    // ─── Definição das tools ───────────────────────────────────────────────

    private function toolDefinitions(): array
    {
        return [
            [
                'name'        => 'evidentia_buscar',
                'description' => 'Busca jurisprudência relevante no banco Evidentia usando busca híbrida (fulltext + semântica + rerank por IA). Cobre TJSC, STJ, TRF4 e TRT12. Use antes de redigir qualquer peça jurídica para encontrar precedentes. Retorna até 10 acórdãos rankeados com ementa, relator, data e justificativa do ranking.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'query' => [
                            'type'        => 'string',
                            'description' => 'Tese jurídica ou tema a pesquisar. Seja específico: ex. "dano moral atraso voo consumidor responsabilidade objetiva"',
                        ],
                        'tribunal' => [
                            'type'        => 'string',
                            'enum'        => ['TJSC', 'STJ', 'TRF4', 'TRT12'],
                            'description' => 'Filtrar por tribunal (opcional). Omitir para pesquisar todos.',
                        ],
                        'topk' => [
                            'type'        => 'integer',
                            'minimum'     => 3,
                            'maximum'     => 20,
                            'default'     => 10,
                            'description' => 'Número de resultados (padrão: 10).',
                        ],
                        'periodo_inicio' => [
                            'type'        => 'string',
                            'description' => 'Data inicial YYYY-MM-DD (opcional).',
                        ],
                        'periodo_fim' => [
                            'type'        => 'string',
                            'description' => 'Data final YYYY-MM-DD (opcional).',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name'        => 'evidentia_resultado',
                'description' => 'Recupera os resultados de uma busca Evidentia já realizada pelo search_id. Use quando quiser revisitar uma busca anterior sem gastar novo orçamento de IA.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search_id' => [
                            'type'        => 'integer',
                            'description' => 'ID da busca retornado por evidentia_buscar.',
                        ],
                    ],
                    'required' => ['search_id'],
                ],
            ],
            [
                'name'        => 'evidentia_gerar_citacao',
                'description' => 'Gera um bloco de citação jurídica pronto para inserir em petições e pareceres. Retorna: (1) síntese objetiva dos precedentes; (2) bloco formatado com referências completas. Requer search_id de uma busca já realizada.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'search_id' => [
                            'type'        => 'integer',
                            'description' => 'ID da busca retornado por evidentia_buscar.',
                        ],
                    ],
                    'required' => ['search_id'],
                ],
            ],
        ];
    }

    // ─── Helpers JSON-RPC ─────────────────────────────────────────────────

    private function okResponse(mixed $id, array $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private function errorResponse(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}
