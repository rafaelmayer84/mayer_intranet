<?php

namespace App\Http\Controllers;

use App\Models\CategoriaAviso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategoriaAvisoController extends Controller
{
    public function index()
    {

        $categorias = CategoriaAviso::orderBy('ordem')->orderBy('nome')->paginate(20);

        return view('avisos.admin.categorias.index', compact('categorias'));
    }

    public function create()
    {

        $categoria = new CategoriaAviso();
        return view('avisos.admin.categorias.form', compact('categoria'));
    }

    public function store(Request $request)
    {

        $data = $this->validar($request);
        CategoriaAviso::create($data);

        return redirect()->route('admin.categorias-avisos.index')
            ->with('success', 'Categoria criada com sucesso!');
    }

    public function edit(CategoriaAviso $categoriaAviso)
    {

        $categoria = $categoriaAviso;
        return view('avisos.admin.categorias.form', compact('categoria'));
    }

    public function update(Request $request, CategoriaAviso $categoriaAviso)
    {

        $data = $this->validar($request, $categoriaAviso->id);
        $categoriaAviso->update($data);

        return redirect()->route('admin.categorias-avisos.index')
            ->with('success', 'Categoria atualizada com sucesso!');
    }

    public function destroy(CategoriaAviso $categoriaAviso)
    {

        // Se houver avisos vinculados, bloqueia exclusão para evitar erro de FK
        if ($categoriaAviso->avisos()->exists()) {
            return back()->with('error', 'Não é possível remover: existem avisos vinculados a esta categoria.');
        }

        $categoriaAviso->delete();

        return redirect()->route('admin.categorias-avisos.index')
            ->with('success', 'Categoria removida com sucesso!');
    }

    private function validar(Request $request, ?int $ignoreId = null): array
    {
        $uniqueRule = 'unique:categorias_avisos,nome';
        if ($ignoreId) {
            $uniqueRule .= ',' . $ignoreId;
        }

        return $request->validate([
            'nome' => ['required', 'string', 'max:100', $uniqueRule],
            'descricao' => 'nullable|string|max:2000',
            'cor_hexadecimal' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icone' => 'nullable|string|max:50',
            'ordem' => 'nullable|integer|min:0|max:9999',
            'ativo' => 'nullable|boolean',
        ]);
    }
}
