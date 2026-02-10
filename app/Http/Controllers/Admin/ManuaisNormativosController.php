<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ManualGrupo;
use App\Models\ManualDocumento;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ManuaisNormativosController extends Controller
{
    // ─────────────────────────────────────────────
    // ABA GRUPOS
    // ─────────────────────────────────────────────

    public function gruposIndex()
    {
        $grupos = ManualGrupo::ordenados()->withCount('documentos')->get();
        return view('admin.manuais.grupos.index', compact('grupos'));
    }

    public function gruposCreate()
    {
        return view('admin.manuais.grupos.form', ['grupo' => null]);
    }

    public function gruposStore(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:manuais_grupos,slug',
            'ordem' => 'nullable|integer|min:0',
            'ativo' => 'nullable|boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['nome']);
        }
        $validated['ativo'] = $request->has('ativo');
        $validated['ordem'] = $validated['ordem'] ?? 0;

        ManualGrupo::create($validated);

        return redirect()->route('admin.manuais.grupos.index')
            ->with('success', 'Grupo criado com sucesso.');
    }

    public function gruposEdit(ManualGrupo $grupo)
    {
        return view('admin.manuais.grupos.form', compact('grupo'));
    }

    public function gruposUpdate(Request $request, ManualGrupo $grupo)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:manuais_grupos,slug,' . $grupo->id,
            'ordem' => 'nullable|integer|min:0',
            'ativo' => 'nullable|boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['nome']);
        }
        $validated['ativo'] = $request->has('ativo');
        $validated['ordem'] = $validated['ordem'] ?? 0;

        $grupo->update($validated);

        return redirect()->route('admin.manuais.grupos.index')
            ->with('success', 'Grupo atualizado com sucesso.');
    }

    public function gruposDestroy(ManualGrupo $grupo)
    {
        $grupo->delete();

        return redirect()->route('admin.manuais.grupos.index')
            ->with('success', 'Grupo excluído com sucesso.');
    }

    // ─────────────────────────────────────────────
    // ABA DOCUMENTOS
    // ─────────────────────────────────────────────

    public function documentosIndex(Request $request)
    {
        $grupoFiltro = $request->get('grupo_id');

        $query = ManualDocumento::with('grupo')->orderBy('grupo_id')->orderBy('ordem')->orderBy('titulo');

        if ($grupoFiltro) {
            $query->where('grupo_id', $grupoFiltro);
        }

        $documentos = $query->get();
        $grupos = ManualGrupo::ordenados()->get();

        return view('admin.manuais.documentos.index', compact('documentos', 'grupos', 'grupoFiltro'));
    }

    public function documentosCreate()
    {
        $grupos = ManualGrupo::ordenados()->get();
        return view('admin.manuais.documentos.form', ['documento' => null, 'grupos' => $grupos]);
    }

    public function documentosStore(Request $request)
    {
        $validated = $request->validate([
            'grupo_id' => 'required|exists:manuais_grupos,id',
            'titulo' => 'required|string|max:255',
            'descricao' => 'nullable|string|max:1000',
            'url_onedrive' => 'required|url:http,https',
            'data_publicacao' => 'nullable|date',
            'ordem' => 'nullable|integer|min:0',
            'ativo' => 'nullable|boolean',
        ]);

        $validated['ativo'] = $request->has('ativo');
        $validated['ordem'] = $validated['ordem'] ?? 0;

        ManualDocumento::create($validated);

        return redirect()->route('admin.manuais.documentos.index')
            ->with('success', 'Documento criado com sucesso.');
    }

    public function documentosEdit(ManualDocumento $documento)
    {
        $grupos = ManualGrupo::ordenados()->get();
        return view('admin.manuais.documentos.form', compact('documento', 'grupos'));
    }

    public function documentosUpdate(Request $request, ManualDocumento $documento)
    {
        $validated = $request->validate([
            'grupo_id' => 'required|exists:manuais_grupos,id',
            'titulo' => 'required|string|max:255',
            'descricao' => 'nullable|string|max:1000',
            'url_onedrive' => 'required|url:http,https',
            'data_publicacao' => 'nullable|date',
            'ordem' => 'nullable|integer|min:0',
            'ativo' => 'nullable|boolean',
        ]);

        $validated['ativo'] = $request->has('ativo');
        $validated['ordem'] = $validated['ordem'] ?? 0;

        $documento->update($validated);

        return redirect()->route('admin.manuais.documentos.index')
            ->with('success', 'Documento atualizado com sucesso.');
    }

    public function documentosDestroy(ManualDocumento $documento)
    {
        $documento->delete();

        return redirect()->route('admin.manuais.documentos.index')
            ->with('success', 'Documento excluído com sucesso.');
    }

    // ─────────────────────────────────────────────
    // ABA PERMISSÕES
    // ─────────────────────────────────────────────

    public function permissoesIndex()
    {
        $users = User::where('role', '!=', 'admin')->orderBy('name')->get();
        $grupos = ManualGrupo::ordenados()->get();

        // Carrega associações existentes
        $userGrupos = [];
        foreach ($users as $user) {
            $userGrupos[$user->id] = $user->manuaisGrupos()->pluck('manuais_grupos.id')->toArray();
        }

        return view('admin.manuais.permissoes', compact('users', 'grupos', 'userGrupos'));
    }

    public function permissoesUpdate(Request $request)
    {
        $validated = $request->validate([
            'permissoes' => 'nullable|array',
            'permissoes.*' => 'nullable|array',
            'permissoes.*.*' => 'exists:manuais_grupos,id',
        ]);

        $users = User::where('role', '!=', 'admin')->get();

        foreach ($users as $user) {
            $grupoIds = $validated['permissoes'][$user->id] ?? [];
            $user->manuaisGrupos()->sync($grupoIds);
        }

        return redirect()->route('admin.manuais.permissoes.index')
            ->with('success', 'Permissões atualizadas com sucesso.');
    }
}
