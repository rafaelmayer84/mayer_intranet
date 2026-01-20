<?php

namespace App\Http\Controllers;

use App\Models\CategoriaAviso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CategoriaAvisoController extends Controller
{
    public function index(): View
    {
        Gate::authorize('avisos.manage');

        $categorias = CategoriaAviso::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->paginate(20);

        return view('avisos.admin.categorias.index', compact('categorias'));
    }

    public function create(): View
    {
        Gate::authorize('avisos.manage');

        $categoria = new CategoriaAviso([
            'ativo' => true,
            'cor_hexadecimal' => '#3B82F6',
            'ordem' => 0,
        ]);

        return view('avisos.admin.categorias.form', compact('categoria'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('avisos.manage');

        $data = $this->validateCategoria($request);

        CategoriaAviso::create($data);

        return redirect()
            ->route('admin.categorias-avisos.index')
            ->with('success', 'Categoria criada com sucesso.');
    }

    public function edit(CategoriaAviso $categoriaAviso): View
    {
        Gate::authorize('avisos.manage');

        $categoria = $categoriaAviso;

        return view('avisos.admin.categorias.form', compact('categoria'));
    }

    public function update(Request $request, CategoriaAviso $categoriaAviso): RedirectResponse
    {
        Gate::authorize('avisos.manage');

        $data = $this->validateCategoria($request, $categoriaAviso->id);

        $categoriaAviso->update($data);

        return redirect()
            ->route('admin.categorias-avisos.index')
            ->with('success', 'Categoria atualizada com sucesso.');
    }

    public function destroy(CategoriaAviso $categoriaAviso): RedirectResponse
    {
        Gate::authorize('avisos.manage');

        // Evita apagar categoria em uso (integridade)
        if ($categoriaAviso->avisos()->exists()) {
            return back()->withErrors(['categoria' => 'Não é possível excluir uma categoria que possui avisos vinculados.']);
        }

        $categoriaAviso->delete();

        return redirect()
            ->route('admin.categorias-avisos.index')
            ->with('success', 'Categoria excluída com sucesso.');
    }

    /**
     * @return array<string,mixed>
     */
    private function validateCategoria(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:100'],
            'descricao' => ['nullable', 'string'],
            'cor_hexadecimal' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icone' => ['nullable', 'string', 'max:50'],
            'ordem' => ['required', 'integer', 'min:0', 'max:9999'],
            'ativo' => ['nullable', 'boolean'],
        ], [
            'nome.required' => 'Informe o nome da categoria.',
            'cor_hexadecimal.regex' => 'Cor inválida (use formato #RRGGBB).',
        ]);
    }
}
