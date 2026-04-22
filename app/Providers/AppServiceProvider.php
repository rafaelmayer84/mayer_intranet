<?php

namespace App\Providers;

use App\Models\Aviso;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('avisos.manage', fn (User $user) => true);
        Gate::define('avisos.update', fn (User $user, Aviso $aviso) => true);
        Gate::define('avisos.delete', fn (User $user, Aviso $aviso) => true);

        // Rate limiter por telefone para rotas NEXO Consulta (bot SendPulse)
        // Usa telefone como chave porque todos os requests vêm do IP do SendPulse
        RateLimiter::for('nexo-consulta', function (Request $request) {
            $chave = $request->input('telefone') ?: $request->ip();
            return Limit::perMinute(30)->by($chave)->response(function () {
                return response()->json(['erro' => 'muitas_tentativas', 'aguarde' => '60'], 429);
            });
        });
    }
}
