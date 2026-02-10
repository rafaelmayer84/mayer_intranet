<?php

namespace App\Http\Controllers;

use App\Models\ManualGrupo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManualNormativoController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $isAdmin = ($user->role === 'admin');

        if ($isAdmin) {
            // Admin vê todos os grupos ativos com documentos ativos
            $grupos = ManualGrupo::ativos()
                ->ordenados()
                ->with('documentosAtivos')
                ->get();
        } else {
            // Usuário comum vê apenas grupos atribuídos
            $grupoIds = $user->manuaisGrupos()->where('ativo', true)->pluck('manuais_grupos.id');

            $grupos = ManualGrupo::ativos()
                ->whereIn('id', $grupoIds)
                ->ordenados()
                ->with('documentosAtivos')
                ->get();
        }

        return view('manuais.index', compact('grupos', 'isAdmin'));
    }
}
