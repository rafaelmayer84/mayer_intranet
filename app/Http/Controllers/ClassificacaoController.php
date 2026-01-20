<?php

namespace App\Http\Controllers;

use App\Models\Movimento;
use App\Services\SyncService;
use Illuminate\Http\Request;

class ClassificacaoController extends Controller
{
    protected $syncService;

    public function __construct(SyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Exibir página de classificação manual
     */
    public function index(Request $request)
    {
        $mes = (int)($request->get('mes', date('n')));
        $ano = (int)($request->get('ano', date('Y')));

        // Obter movimentos pendentes de classificação
        $pendentes = Movimento::where('ano', $ano)
            ->where('mes', $mes)
            ->where('classificacao', Movimento::PENDENTE_CLASSIFICACAO)
            ->orderBy('data', 'asc')
            ->get();

        // Obter resumo do período
        $resumo = $this->syncService->getResumoMovimentos($mes, $ano);

        // Lista de meses disponíveis
        $mesesDisponiveis = Movimento::selectRaw('DISTINCT mes, ano')
            ->orderBy('ano', 'desc')
            ->orderBy('mes', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'mes' => $item->mes,
                    'ano' => $item->ano,
                    'label' => $this->getNomeMes($item->mes) . '/' . $item->ano
                ];
            });

        return view('classificacao.index', compact('pendentes', 'resumo', 'mes', 'ano', 'mesesDisponiveis'));
    }

    /**
     * Aplicar classificação a múltiplos movimentos
     */
    public function aplicar(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'classificacao' => 'required|string|in:' . implode(',', [
                Movimento::RECEITA_PF,
                Movimento::RECEITA_PJ,
                Movimento::RECEITA_FINANCEIRA,
            ]),
        ]);

        Movimento::whereIn('id', $request->ids)->update([
            'classificacao' => $request->classificacao,
            'classificacao_manual' => true,
        ]);

        return redirect()->route('classificacao', [
            'mes' => $request->get('mes'),
            'ano' => $request->get('ano'),
        ])->with('success', 'Classificação aplicada com sucesso!');
    }

    /**
     * Classificar um movimento individual (API)
     */
    public function classificar(Request $request)
    {
        $request->validate([
            'movimento_id' => 'required|integer',
            'classificacao' => 'required|in:RECEITA_PF,RECEITA_PJ,RECEITA_FINANCEIRA'
        ]);

        $sucesso = $this->syncService->classificarManualmente(
            $request->movimento_id,
            $request->classificacao
        );

        if ($sucesso) {
            return response()->json([
                'success' => true,
                'message' => 'Movimento classificado com sucesso!'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Erro ao classificar movimento'
        ], 400);
    }

    /**
     * Classificar múltiplos movimentos de uma vez (API)
     */
    public function classificarLote(Request $request)
    {
        $request->validate([
            'movimentos' => 'required|array',
            'movimentos.*.id' => 'required|integer',
            'movimentos.*.classificacao' => 'required|in:RECEITA_PF,RECEITA_PJ,RECEITA_FINANCEIRA'
        ]);

        $sucessos = 0;
        $erros = 0;

        foreach ($request->movimentos as $mov) {
            $resultado = $this->syncService->classificarManualmente(
                $mov['id'],
                $mov['classificacao']
            );
            if ($resultado) {
                $sucessos++;
            } else {
                $erros++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$sucessos} movimentos classificados com sucesso. {$erros} erros.",
            'sucessos' => $sucessos,
            'erros' => $erros
        ]);
    }

    /**
     * Obter nome do mês
     */
    protected function getNomeMes(int $mes): string
    {
        $meses = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
            4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
            7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
            10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
        ];
        return $meses[$mes] ?? '';
    }
}
