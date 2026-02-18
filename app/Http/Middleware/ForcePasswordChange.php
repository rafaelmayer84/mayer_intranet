<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Redireciona para alteração de senha se nunca foi alterada (primeiro acesso).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && is_null($user->password_changed_at)) {
            // Permitir acesso às rotas de perfil e logout
            $allowed = ['perfil.index', 'perfil.alterar-senha', 'logout'];

            if (!in_array($request->route()?->getName(), $allowed)) {
                return redirect()->route('perfil.index')
                    ->with('warning', 'Por segurança, altere sua senha temporária antes de continuar.');
            }
        }

        return $next($request);
    }
}
