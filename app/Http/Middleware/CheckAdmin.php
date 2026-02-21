<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdmin
{
    /**
     * Handle an incoming request.
     *
     * Uso nas rotas:
     * Route::get('/admin/rota', [Controller::class, 'method'])->middleware('admin');
     */
    public function handle(Request $request, Closure $next): Response
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

        // Verificar se é admin
        if (!$user->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Acesso negado',
                    'message' => 'Esta área é restrita a administradores.'
                ], 403);
            }

            abort(403);
        }

        return $next($request);
    }
}
