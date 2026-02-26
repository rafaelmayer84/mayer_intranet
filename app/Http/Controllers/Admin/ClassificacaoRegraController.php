<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassificacaoRegra;
use App\Services\Sync\Customization\ClassificacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\SystemEvent;

class ClassificacaoRegraController extends Controller
{
    protected $classificacaoService;

    public function __construct(ClassificacaoService $classificacaoService)
    {
        $this->classificacaoService = $classificacaoService;
    }

    /**
     * Lista todas as regras
     */
    public function index(Request $request)
    {
        $query = ClassificacaoRegra::query();

        if ($request->filled('busca')) {
            $busca = $request->input('busca');
            $query->where(function($q) use ($busca) {
                $q->where('codigo_plano', 'like', "%{$busca}%")
                  ->orWhere('nome_plano', 'like', "%{$busca}%");
            });
        }

        if ($request->filled('classificacao')) {
            $query->where('classificacao', $request->input('classificacao'));
        }

        $regras = $query->orderBy('codigo_plano')->paginate(20);

        return response()->json([
            'data' => $regras->items(),
            'current_page' => $regras->currentPage(),
            'last_page' => $regras->lastPage(),
            'total' => $regras->total(),
        ]);
    }

    /**
     * Cria ou atualiza uma regra
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo_plano' => 'required|string|max:50',
            'nome_plano' => 'nullable|string|max:255',
            'classificacao' => 'required|in:RECEITA_PF,RECEITA_PJ,DESPESA,PENDENTE_CLASSIFICACAO',
            'ativa' => 'boolean',
        ]);

        try {
            $regra = $this->classificacaoService->criarOuAtualizarRegra($validated);

            return response()->json([
                'success' => true,
                'message' => 'Regra salva com sucesso!',
                'data' => $regra,
            ]);
        } catch (\Exception $e) {
            Log::error("Erro ao salvar regra: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar regra.',
            ], 500);
        }
    }

    /**
     * Busca uma regra específica
     */
    public function show($id)
    {
        $regra = ClassificacaoRegra::findOrFail($id);
        return response()->json($regra);
    }

    /**
     * Deleta uma regra
     */
    public function destroy($id)
    {
        try {
            $regra = ClassificacaoRegra::findOrFail($id);
            $regra->delete();

            return response()->json([
                'success' => true,
                'message' => 'Regra deletada com sucesso!',
            ]);
        } catch (\Exception $e) {
            Log::error("Erro ao deletar regra: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Erro ao deletar regra.',
            ], 500);
        }
    }

    /**
     * Reclassifica todos os movimentos
     */
    public function reclassificar()
    {
        try {
            $stats = $this->classificacaoService->reclassificarTudo();
            SystemEvent::financeiro('reclassificacao.concluida', 'info', 'Reclassificacao: ' . ($stats['processados'] ?? 0) . ' movimentos', null, $stats);
            return response()->json([
                'success' => true,
                'message' => "Reclassificação concluída! {$stats['processados']} movimentos atualizados.",
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error("Erro ao reclassificar: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Erro ao reclassificar movimentos.',
            ], 500);
        }
    }

    /**
     * Importa planos de contas do DataJuri
     */
    public function importar()
    {
        try {
            // Busca códigos únicos que ainda não têm regra
            $codigos = $this->classificacaoService->buscarCodigosSemRegra();

            $importados = 0;
            foreach ($codigos as $codigo) {
                $this->classificacaoService->criarOuAtualizarRegra([
                    'codigo_plano' => $codigo,
                    'nome_plano' => "Plano {$codigo}",
                    'classificacao' => 'PENDENTE_CLASSIFICACAO',
                    'origem' => 'IMPORTACAO',
                ]);
                $importados++;
            }

            return response()->json([
                'success' => true,
                'message' => "{$importados} novos planos de contas importados!",
                'importados' => $importados,
            ]);
        } catch (\Exception $e) {
            Log::error("Erro ao importar: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Erro ao importar planos de contas.',
            ], 500);
        }
    }

    /**
     * Estatísticas de classificação
     */
    public function estatisticas()
    {
        $stats = $this->classificacaoService->estatisticas();
        return response()->json($stats);
    }
}
