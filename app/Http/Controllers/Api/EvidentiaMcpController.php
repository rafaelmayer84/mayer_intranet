<?php

// ESTÁVEL desde 16/04/2026
//
// ┌─────────────────────────────────────────────────────────────────────────┐
// │  EvidentiaMcpController — API para Claude Desktop (MCP)  v1.0          │
// ├─────────────────────────────────────────────────────────────────────────┤
// │  Expõe o Evidentia como ferramentas MCP para o Claude Desktop.          │
// │  Autenticado via Bearer token (EVIDENTIA_MCP_TOKEN no .env).            │
// ├─────────────────────────────────────────────────────────────────────────┤
// │  Endpoints                                                              │
// │  POST /api/evidentia-mcp/search         → executa busca híbrida        │
// │  GET  /api/evidentia-mcp/results/{id}   → recupera busca existente     │
// │  POST /api/evidentia-mcp/citation/{id}  → gera bloco de citação         │
// ├─────────────────────────────────────────────────────────────────────────┤
// │  Middleware: evidentia.mcp + throttle:30,1                              │
// │  User_id: null em todas as chamadas (contexto MCP, sem sessão web)      │
// │  Budget guard: mesmo cache que o controller web (evidentia_budget_*)    │
// └─────────────────────────────────────────────────────────────────────────┘

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvidentiaSearch;
use App\Services\Evidentia\EvidentiaCitationService;
use App\Services\Evidentia\EvidentiaSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EvidentiaMcpController extends Controller
{
    /**
     * POST /api/evidentia-mcp/search
     *
     * Executa busca híbrida e retorna resultados rankeados.
     *
     * Body: { query, tribunal?, topk?, periodo_inicio?, periodo_fim? }
     */
    public function search(Request $request, EvidentiaSearchService $searchService): JsonResponse
    {
        $request->validate([
            'query'          => 'required|string|min:5|max:1000',
            'tribunal'       => 'nullable|string|max:20',
            'topk'           => 'nullable|integer|min:3|max:20',
            'periodo_inicio' => 'nullable|date',
            'periodo_fim'    => 'nullable|date',
        ]);

        $todayBudget = (float) Cache::get('evidentia_budget_' . now()->toDateString(), 0);
        if ($todayBudget >= config('evidentia.daily_budget_usd')) {
            return response()->json(['error' => 'Limite diário de orçamento atingido.'], 429);
        }

        $filters = array_filter([
            'tribunal'       => $request->input('tribunal'),
            'periodo_inicio' => $request->input('periodo_inicio'),
            'periodo_fim'    => $request->input('periodo_fim'),
        ]);

        $topk = (int) $request->input('topk', 10);

        $search = $searchService->search(
            $request->input('query'),
            $filters,
            $topk,
            null // sem user_id em chamadas MCP
        );

        return response()->json($this->formatSearch($search));
    }

    /**
     * GET /api/evidentia-mcp/results/{id}
     *
     * Recupera resultados de uma busca existente.
     */
    public function results(int $id): JsonResponse
    {
        $search = EvidentiaSearch::with('results')->find($id);

        if (!$search) {
            return response()->json(['error' => 'Busca não encontrada.'], 404);
        }

        return response()->json($this->formatSearch($search));
    }

    /**
     * POST /api/evidentia-mcp/citation/{id}
     *
     * Gera bloco de citação (síntese + precedentes formatados) para uso em peças.
     */
    public function citation(int $id, EvidentiaCitationService $citationService): JsonResponse
    {
        $search = EvidentiaSearch::with(['results', 'citationBlock'])->find($id);

        if (!$search) {
            return response()->json(['error' => 'Busca não encontrada.'], 404);
        }

        if ($search->status !== 'complete') {
            return response()->json(['error' => 'Busca ainda não concluída.'], 422);
        }

        $todayBudget = (float) Cache::get('evidentia_budget_' . now()->toDateString(), 0);
        if ($todayBudget >= config('evidentia.daily_budget_usd')) {
            return response()->json(['error' => 'Limite diário de orçamento atingido.'], 429);
        }

        $block = $citationService->generate($search, null);

        if (!$block) {
            return response()->json(['error' => 'Não foi possível gerar o bloco de citação.'], 500);
        }

        return response()->json([
            'search_id'         => $search->id,
            'query'             => $search->query,
            'sintese_objetiva'  => $block->sintese_objetiva,
            'bloco_precedentes' => $block->bloco_precedentes,
            'precedentes_usados'=> $block->jurisprudence_ids_used,
            'custo_usd'         => round($block->cost_usd, 4),
        ]);
    }

    /**
     * Formata EvidentiaSearch + results para resposta JSON estruturada.
     */
    private function formatSearch(EvidentiaSearch $search): array
    {
        $resultados = $search->results->map(function ($r) {
            // Carrega metadados da jurisprudência no DB de origem
            $juris = $r->getJurisprudence();

            return [
                'rank'                  => $r->final_rank,
                'jurisprudencia_id'     => $r->jurisprudence_id,
                'tribunal'              => $r->tribunal,
                'numero_processo'       => $juris->numero_processo ?? null,
                'sigla_classe'          => $juris->sigla_classe ?? null,
                'descricao_classe'      => $juris->descricao_classe ?? null,
                'relator'               => $juris->relator ?? null,
                'orgao_julgador'        => $juris->orgao_julgador ?? null,
                'data_decisao'          => $juris->data_decisao ?? null,
                'ementa'                => $juris->ementa
                    ? mb_substr($juris->ementa, 0, 1500)
                    : null,
                'score_final'           => round($r->final_score, 4),
                'score_semantico'       => round($r->score_semantic, 4),
                'rerank_justificativa'  => $r->rerank_justification,
            ];
        })->values()->all();

        return [
            'search_id'      => $search->id,
            'query'          => $search->query,
            'status'         => $search->status,
            'degraded_mode'  => (bool) $search->degraded_mode,
            'latencia_ms'    => $search->latency_ms,
            'custo_usd'      => round((float) $search->cost_usd, 4),
            'total_resultados'=> count($resultados),
            'resultados'     => $resultados,
        ];
    }
}
