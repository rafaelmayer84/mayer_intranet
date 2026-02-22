<?php

namespace App\Http\Controllers;

use App\Models\Aviso;
use App\Models\CategoriaAviso;
use App\Models\NotificationIntranet;
use App\Models\User;
use App\Services\AvisoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AvisoController extends Controller
{
    public function __construct(private AvisoService $avisoService)
    {
    }

    /**
     * Página pública (home): quadro de avisos.
     */
    public function index(Request $request)
    {
        $categorias = CategoriaAviso::ativas()->orderBy('ordem')->orderBy('nome')->get();

        $filtros = [
            'categoria_id' => $request->query('categoria_id'),
            'ordenar' => $request->query('ordenar', 'prioridade'),
            'busca' => $request->query('busca', ''),
        ];

        $avisos = $this->avisoService->getAvisosAtivos($filtros);
        $totalUsuarios = $this->avisoService->getTotalUsuariosAtivos();

        return view('avisos.index', compact('avisos', 'categorias', 'filtros', 'totalUsuarios'));
    }

    public function show(Aviso $aviso)
    {
        abort_unless($aviso->isAtivo(), 404);

        // Registra leitura automaticamente ao abrir o aviso
        $this->avisoService->marcarComoLido($aviso->id, auth()->id());

        $aviso->load(['categoria', 'autor'])->loadCount('usuariosLidos');
        $totalUsuarios = $this->avisoService->getTotalUsuariosAtivos();
        $jaLeu = $this->avisoService->usuarioJaLeu($aviso->id, auth()->id());

        $descricaoHtml = $this->renderMarkdownSeguro($aviso->descricao);

        return view('avisos.show', compact('aviso', 'totalUsuarios', 'jaLeu', 'descricaoHtml'));
    }

    /**
     * Admin: lista/gerenciamento.
     */
    public function admin(Request $request)
    {
        $categorias = CategoriaAviso::orderBy('ordem')->orderBy('nome')->get();

        $query = Aviso::query()->with(['categoria', 'autor']);

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', (int) $request->categoria_id);
        }
        if ($request->filled('prioridade')) {
            $query->where('prioridade', $request->prioridade);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('busca')) {
            $busca = trim((string) $request->busca);
            $query->where(function ($q) use ($busca) {
                $q->where('titulo', 'like', '%' . $busca . '%')
                    ->orWhere('descricao', 'like', '%' . $busca . '%');
            });
        }

        $avisos = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('avisos.admin.index', compact('avisos', 'categorias'));
    }

    public function create()
    {
        $categorias = CategoriaAviso::ativas()->orderBy('ordem')->orderBy('nome')->get();
        $aviso = new Aviso();

        return view('avisos.admin.form', compact('aviso', 'categorias'));
    }

    public function store(Request $request)
    {
        $data = $this->validarAviso($request);
        $data['criado_por'] = auth()->id();

        try {
            $aviso = null;
            DB::transaction(function () use ($data, &$aviso) {
                $aviso = Aviso::create($data);

                // Notificar todos os usuarios ativos
                $usuarios = User::where('ativo', true)->pluck('id');
                $now = now();
                $notificacoes = [];
                foreach ($usuarios as $uid) {
                    $notificacoes[] = [
                        'user_id'    => $uid,
                        'tipo'       => 'aviso',
                        'titulo'     => 'Novo Aviso: ' . $aviso->titulo,
                        'mensagem'   => \Illuminate\Support\Str::limit(strip_tags($aviso->descricao), 120),
                        'link'       => '/avisos/' . $aviso->id,
                        'icone'      => 'megaphone',
                        'lida'       => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                NotificationIntranet::insert($notificacoes);
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AVISO STORE ERRO: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $data ?? [],
            ]);

            return back()
                ->withInput()
                ->withErrors(['store' => 'Não foi possível salvar o aviso. Erro: ' . $e->getMessage()]);
        }

        // IMPORTANTE: a listagem pública usa cache; sem isso o novo aviso pode demorar a aparecer.
        $this->avisoService->bumpCacheVersion();

        return redirect()->route('admin.avisos.index')
            ->with('success', 'Aviso criado com sucesso!');
    }

    public function edit(Aviso $aviso)
    {
        $categorias = CategoriaAviso::ativas()->orderBy('ordem')->orderBy('nome')->get();

        return view('avisos.admin.form', compact('aviso', 'categorias'));
    }

    public function update(Request $request, Aviso $aviso)
    {
        $data = $this->validarAviso($request);
        $aviso->update($data);

        $this->avisoService->bumpCacheVersion();

        return redirect()->route('admin.avisos.index')
            ->with('success', 'Aviso atualizado com sucesso!');
    }

    public function destroy(Aviso $aviso)
    {
        $aviso->delete();

        $this->avisoService->bumpCacheVersion();

        return redirect()->route('admin.avisos.index')
            ->with('success', 'Aviso removido com sucesso!');
    }

    public function marcarComoLido(Request $request, Aviso $aviso)
    {
        abort_unless($aviso->isAtivo(), 404);

        $this->avisoService->marcarComoLido($aviso->id, auth()->id());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Marcado como lido.');
    }

    private function validarAviso(Request $request): array
    {
        return $request->validate([
            'titulo' => 'required|string|max:255',
            'descricao' => 'required|string|max:10000',
            'categoria_id' => 'required|exists:categorias_avisos,id',
            'prioridade' => 'required|in:baixa,media,alta,critica',
            'status' => 'required|in:ativo,inativo,agendado',
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date|after_or_equal:data_inicio',
        ]);
    }

    private function renderMarkdownSeguro(string $texto): string
    {
        $html = Str::markdown($texto);

        // Permite um conjunto pequeno e útil de tags.
        $html = strip_tags($html, '<p><br><strong><em><ul><ol><li><a><blockquote><code><pre><h1><h2><h3><h4><h5><h6>');

        // Hardening simples para links.
        $html = preg_replace('/<a\s+([^>]*href=)/i', '<a rel="noopener noreferrer" target="_blank" $1', $html) ?? $html;

        return $html;
    }
}
