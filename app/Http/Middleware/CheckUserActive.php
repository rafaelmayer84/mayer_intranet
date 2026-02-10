<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserActive
{
    /**
     * Handle an incoming request.
     *
     * Verifica se o usuário está ativo no sistema.
     * Deve ser aplicado a todas as rotas protegidas.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Se não há usuário, deixa o middleware auth lidar
        if (!$user) {
            return $next($request);
        }

        // Verifica se o usuário está ativo
        if (!$user->ativo) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Conta inativa',
                    'message' => 'Sua conta foi desativada. Contate o administrador.'
                ], 403);
            }

            return redirect()
                ->route('login')
                ->with('error', 'Sua conta foi desativada. Contate o administrador.');
        }

        // Atualiza último acesso
        if ($user->ultimo_acesso === null || $user->ultimo_acesso->diffInMinutes(now()) > 5) {
            $user->update(['ultimo_acesso' => now()]);
        }

        return $next($request);
    }
}
