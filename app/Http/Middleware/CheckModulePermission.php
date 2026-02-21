<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModulePermission
{
    /**
     * Handle an incoming request.
     *
     * Uso nas rotas:
     * Route::get('/rota', [Controller::class, 'method'])
     *     ->middleware('modulo:resultados.visao-gerencial,visualizar');
     * 
     * Parâmetros:
     * - modulo: slug do módulo (ex: resultados.visao-gerencial)
     * - permissao: tipo de permissão (visualizar, editar, criar, excluir, executar)
     */
    public function handle(Request $request, Closure $next, string $moduloSlug, string $permissao = 'visualizar'): Response
    {
        $user = $request->user();

        // Usuário não autenticado
        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }
            return redirect()->route('login');
        }

        // Usuário inativo
        if (!$user->ativo) {
            auth()->logout();
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Usuário inativo'], 403);
            }
            return redirect()->route('login')->with('error', 'Sua conta está inativa. Contate o administrador.');
        }

        // Admin tem acesso total
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Verificar permissão específica
        $temPermissao = match ($permissao) {
            'visualizar' => $user->podeAcessar($moduloSlug),
            'editar' => $user->podeEditar($moduloSlug),
            'executar' => $user->podeExecutar($moduloSlug),
            default => false,
        };

        if (!$temPermissao) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Acesso negado',
                    'message' => 'Você não tem permissão para acessar este recurso.'
                ], 403);
            }

            abort(403, 'Você não tem permissão para acessar este recurso.');
        }

        return $next($request);
    }
}
