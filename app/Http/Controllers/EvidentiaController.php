<?php

namespace App\Http\Controllers;

use App\Models\EvidentiaCitationBlock;
use App\Models\EvidentiaSearch;
use App\Services\Evidentia\EvidentiaCitationService;
use App\Services\Evidentia\EvidentiaSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EvidentiaController extends Controller
{
    /**
     * Página principal — busca.
     */
    public function index()
    {
        $recentSearches = EvidentiaSearch::where('user_id', auth()->id())
            ->where('status', 'complete')
            ->latest()
            ->limit(10)
            ->get();

        $tribunais = array_keys(config('evidentia.tribunal_databases'));

        return view('evidentia.index', compact('recentSearches', 'tribunais'));
    }

    /**
     * Executa busca.
     */
    public function search(Request $request, EvidentiaSearchService $searchService)
    {
        $request->validate([
            'query'          => 'required|string|min:5|max:1000',
            'tribunal'       => 'nullable|string|max:20',
            'classe'         => 'nullable|string|max:80',
            'area_direito'   => 'nullable|string|max:30',
            'orgao_julgador' => 'nullable|string|max:120',
            'relator'        => 'nullable|string|max:120',
            'periodo_inicio' => 'nullable|date',
            'periodo_fim'    => 'nullable|date',
            'topk'           => 'nullable|integer|min:5|max:30',
        ]);

        // Budget guard no controller
        $todayBudget = (float) Cache::get('evidentia_budget_' . now()->toDateString(), 0);
        if ($todayBudget >= config('evidentia.daily_budget_usd')) {
            return back()->with('error', 'Limite diário de uso da IA atingido. Tente novamente amanhã.');
        }

        $filters = array_filter([
            'tribunal'       => $request->input('tribunal'),
            'classe'         => $request->input('classe'),
            'area_direito'   => $request->input('area_direito'),
            'orgao_julgador' => $request->input('orgao_julgador'),
            'relator'        => $request->input('relator'),
            'periodo_inicio' => $request->input('periodo_inicio'),
            'periodo_fim'    => $request->input('periodo_fim'),
        ]);

        $topk = (int) ($request->input('topk', config('evidentia.default_topk')));

        $search = $searchService->search(
            $request->input('query'),
            $filters,
            $topk,
            auth()->id()
        );

        return redirect()->route('evidentia.resultados', $search->id);
    }

    /**
     * Exibe resultados da busca.
     */
    public function resultados(int $searchId)
    {
        $search = EvidentiaSearch::with(['results', 'citationBlock'])
            ->findOrFail($searchId);

        // Carrega jurisprudências completas para cada resultado
        $resultsWithJuris = $search->results->map(function ($result) {
            $juris = $result->getJurisprudence();
            $result->juris = $juris;
            return $result;
        });

        return view('evidentia.resultados', compact('search', 'resultsWithJuris'));
    }

    /**
     * Gera bloco de citação para petição.
     */
    public function gerarBloco(int $searchId, EvidentiaCitationService $citationService)
    {
        $search = EvidentiaSearch::findOrFail($searchId);

        // Budget guard
        $todayBudget = (float) Cache::get('evidentia_budget_' . now()->toDateString(), 0);
        if ($todayBudget >= config('evidentia.daily_budget_usd')) {
            return back()->with('error', 'Limite diário de uso da IA atingido.');
        }

        $block = $citationService->generate($search, auth()->id());

        if (!$block) {
            return back()->with('error', 'Não foi possível gerar o bloco de citação. Verifique se há resultados disponíveis.');
        }

        return redirect()->route('evidentia.resultados', $searchId)
            ->with('success', 'Bloco de citação gerado com sucesso.');
    }

    /**
     * Exibe jurisprudência individual.
     */
    public function show(string $tribunal, int $id)
    {
        $config = config("evidentia.tribunal_databases." . strtoupper($tribunal));
        if (!$config) {
            abort(404, 'Tribunal não encontrado');
        }

        $juris = \DB::connection($config['connection'])
            ->table($config['table'])
            ->where('id', $id)
            ->first();

        if (!$juris) {
            abort(404, 'Jurisprudência não encontrada');
        }

        return view('evidentia.show', compact('juris', 'tribunal'));
    }

    /**
     * Painel de custos (admin).
     */
    public function custos()
    {
        $dailyCosts = EvidentiaSearch::selectRaw('DATE(created_at) as dia, SUM(cost_usd) as total_cost, COUNT(*) as total_searches, SUM(tokens_in) as total_tokens_in, SUM(tokens_out) as total_tokens_out')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupByRaw('DATE(created_at)')
            ->orderByDesc('dia')
            ->get();

        $todayBudget = (float) Cache::get('evidentia_budget_' . now()->toDateString(), 0);
        $dailyLimit  = config('evidentia.daily_budget_usd');

        return view('evidentia.custos', compact('dailyCosts', 'todayBudget', 'dailyLimit'));
    }
}
