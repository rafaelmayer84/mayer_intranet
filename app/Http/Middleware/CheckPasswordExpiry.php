<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPasswordExpiry
{
    /**
     * Redireciona para troca de senha se expirada (30 dias).
     * Exceções: rota de perfil, logout, e admins.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Rotas permitidas mesmo com senha expirada
            $rotasLiberadas = [
                'perfil.index',
                'perfil.alterar-senha',
                'logout',
            ];

            $rotaAtual = $request->route()?->getName();

            if (!in_array($rotaAtual, $rotasLiberadas) && $user->senhaExpirada()) {
                return redirect()->route('perfil.index')
                    ->with('warning', 'Sua senha expirou. Por favor, altere-a para continuar.');
            }
        }

        return $next($request);
    }
}
