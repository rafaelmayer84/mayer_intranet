<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmComandoService;
use Illuminate\Http\Request;

/**
 * Painel do Dono: visão única para o gestor tomar decisões.
 * Acesso restrito a admin.
 */
class CrmComandoController extends Controller
{
    public function index(Request $request, CrmComandoService $svc)
    {
        $user = $request->user();
        if (!$user || !method_exists($user, 'isAdmin') || !$user->isAdmin()) {
            abort(403, 'Painel do Dono é restrito a administradores.');
        }

        $data = $svc->todosOsBlocos();

        return view('crm.comando.index', $data);
    }
}
