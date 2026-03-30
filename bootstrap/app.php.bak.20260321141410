<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
return Application::configure(basePath: dirname(__DIR__))
    ->withMiddleware(function ($middleware) {
        $middleware->validateCsrfTokens(except: ['webhook/leads']);
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', \App\Http\Middleware\ForcePasswordChange::class);
        $middleware->alias([
            'force.json' => \App\Http\Middleware\ForceJsonResponse::class,
            'admin' => \App\Http\Middleware\CheckAdmin::class,
            'modulo' => \App\Http\Middleware\CheckModulePermission::class,
            'user.active' => \App\Http\Middleware\CheckUserActive::class,
        ]);
        
        // Excluir rotas de API do CSRF
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
