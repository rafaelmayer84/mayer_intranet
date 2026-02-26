<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassificacaoRegra;
use App\Services\ClassificacaoService;
use App\Services\DataJuriService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\SystemEvent;

class ClassificacaoRegrasController extends Controller
{
    protected ClassificacaoService $classificacaoService;

    public function __construct(ClassificacaoService $classificacaoService)
    {
        $this->middleware('auth');
        $this->classificacaoService = $classificacaoService;
    }

    /**
     * Lista todas as regras de classificação
     */
    public function index(Request $request)
    {
        $query = ClassificacaoRegra::query();

        // Filtro por classificação
        if ($request->filled('classificacao')) {
            $query->where('classificacao', $request->classificacao);
        }

        // Filtro por status
        if ($request->filled('ativo')) {
            $query->where('ativo', $request->ativo === '1');
        }

        // Filtro por origem
        if ($request->filled('origem')) {
            $query->where('origem', $request->origem);
        }

        // Busca por código ou nome
        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function($q) use ($busca) {
                $q->where('codigo_plano', 'LIKE', "%{$busca}%")
                  ->orWhere('nome_plano', 'LIKE', "%{$busca}%");
            });
        }

        $regras = $query->with(['criador', 'modificador'])
            ->orderBy('prioridade', 'desc')
            ->orderBy('codigo_plano')
            ->paginate(50);

        $classificacoes = ClassificacaoRegra::CLASSIFICACOES;
        $tiposMovimento = ClassificacaoRegra::TIPOS_MOVIMENTO;
        $origens = ClassificacaoRegra::ORIGENS;

        return view('admin.classificacao-regras.index', compact(
            'regras',
            'classificacoes',
            'tiposMovimento',
            'origens'
        ));
    }

    /**
     * Exibe formulário de criação
     */
    public function create()
    {
        $classificacoes = ClassificacaoRegra::CLASSIFICACOES;
        $tiposMovimento = ClassificacaoRegra::TIPOS_MOVIMENTO;

        return view('admin.classificacao-regras.form', compact(
            'classificacoes',
            'tiposMovimento'
        ));
    }

    /**
     * Armazena nova regra
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'codigo_plano' => 'required|string|max:50|unique:classificacao_regras,codigo_plano',
            'nome_plano' => 'required|string|max:255',
            'classificacao' => 'required|string|in:' . implode(',', array_keys(ClassificacaoRegra::CLASSIFICACOES)),
            'tipo_movimento' => 'required|string|in:' . implode(',', array_keys(ClassificacaoRegra::TIPOS_MOVIMENTO)),
            'prioridade' => 'integer|min:0|max:100',
            'ativo' => 'boolean',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        ClassificacaoRegra::create([
            'codigo_plano' => $request->codigo_plano,
            'nome_plano' => $request->nome_plano,
            'classificacao' => $request->classificacao,
            'tipo_movimento' => $request->tipo_movimento,
            'prioridade' => $request->prioridade ?? 0,
            'ativo' => $request->has('ativo'),
            'origem' => 'manual',
            'observacoes' => $request->observacoes,
            'criado_por' => Auth::id(),
        ]);

        return redirect()
            ->route('admin.classificacao-regras.index')
            ->with('success', 'Regra de classificação criada com sucesso!');
    }

    /**
     * Exibe formulário de edição
     */
    public function edit(ClassificacaoRegra $regra)
    {
        $classificacoes = ClassificacaoRegra::CLASSIFICACOES;
        $tiposMovimento = ClassificacaoRegra::TIPOS_MOVIMENTO;

        return view('admin.classificacao-regras.form', compact(
            'regra',
            'classificacoes',
            'tiposMovimento'
        ));
    }

    /**
     * Atualiza regra existente
     */
    public function update(Request $request, ClassificacaoRegra $regra)
    {
        $validator = Validator::make($request->all(), [
            'codigo_plano' => 'required|string|max:50|unique:classificacao_regras,codigo_plano,' . $regra->id,
            'nome_plano' => 'required|string|max:255',
            'classificacao' => 'required|string|in:' . implode(',', array_keys(ClassificacaoRegra::CLASSIFICACOES)),
            'tipo_movimento' => 'required|string|in:' . implode(',', array_keys(ClassificacaoRegra::TIPOS_MOVIMENTO)),
            'prioridade' => 'integer|min:0|max:100',
            'ativo' => 'boolean',
            'observacoes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        $regra->update([
            'codigo_plano' => $request->codigo_plano,
            'nome_plano' => $request->nome_plano,
            'classificacao' => $request->classificacao,
            'tipo_movimento' => $request->tipo_movimento,
            'prioridade' => $request->prioridade ?? 0,
            'ativo' => $request->has('ativo'),
            'observacoes' => $request->observacoes,
            'modificado_por' => Auth::id(),
        ]);

        return redirect()
            ->route('admin.classificacao-regras.index')
            ->with('success', 'Regra atualizada com sucesso!');
    }

    /**
     * Remove regra
     */
    public function destroy(ClassificacaoRegra $regra)
    {
        $regra->delete();

        return redirect()
            ->route('admin.classificacao-regras.index')
            ->with('success', 'Regra excluída com sucesso!');
    }

    /**
     * Importa planos de contas do DataJuri
     */
    public function importar(Request $request, DataJuriService $dataJuriService)
    {
        try {
            // Autenticar no DataJuri
            $token = $dataJuriService->authenticate();

            // Buscar movimentos (últimos 1000 para extrair planos)
            $movimentos = $dataJuriService->getMovimentos($token, [
                'limit' => 1000,
            ]);

            if (empty($movimentos)) {
                return back()->with('warning', 'Nenhum movimento encontrado no DataJuri.');
            }

            // Importar planos únicos
            $importados = $this->classificacaoService->importarDoDataJuri($movimentos);

            if ($importados === 0) {
                return back()->with('info', 'Todos os planos já estão cadastrados.');
            }

            return back()->with('success', "{$importados} novos planos importados do DataJuri! Revise e ative as regras.");

        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao importar do DataJuri: ' . $e->getMessage());
        }
    }

    /**
     * Reclassifica todos os movimentos pendentes
     */
    public function reclassificar()
    {
        try {
            $stats = $this->classificacaoService->reclassificarMovimentos();

            $mensagem = sprintf(
                "Reclassificação concluída! %d de %d movimentos reclassificados. %d ainda pendentes.",
                $stats['reclassificados'],
                $stats['total_analisados'],
                $stats['pendentes']
            );

            return back()->with('success', $mensagem);

        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao reclassificar: ' . $e->getMessage());
        }
    }

    /**
     * Toggle status ativo/inativo
     */
    public function toggleStatus(ClassificacaoRegra $regra)
    {
        $regra->update([
            'ativo' => !$regra->ativo,
            'modificado_por' => Auth::id(),
        ]);

        $status = $regra->ativo ? 'ativada' : 'desativada';

        return back()->with('success', "Regra {$status} com sucesso!");
    }

    /**
     * Exporta regras para CSV
     */
    public function exportar()
    {
        $regras = ClassificacaoRegra::orderBy('codigo_plano')->get();

        $filename = 'classificacao_regras_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($regras) {
            $file = fopen('php://output', 'w');
            
            // BOM para UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Cabeçalho
            fputcsv($file, [
                'Código Plano',
                'Nome Plano',
                'Classificação',
                'Tipo Movimento',
                'Ativo',
                'Prioridade',
                'Origem',
                'Observações',
            ]);

            // Dados
            foreach ($regras as $regra) {
                fputcsv($file, [
                    $regra->codigo_plano,
                    $regra->nome_plano,
                    $regra->classificacao,
                    $regra->tipo_movimento,
                    $regra->ativo ? 'Sim' : 'Não',
                    $regra->prioridade,
                    $regra->origem,
                    $regra->observacoes,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
