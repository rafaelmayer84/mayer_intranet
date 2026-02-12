<?php

/**
 * Rotas do Dashboard Clientes & Mercado
 * 
 * Adicionar ao final de routes/web.php ou incluir via require
 * 
 * NOTA: Essas rotas devem estar dentro do grupo middleware 'auth'
 */

use App\Http\Controllers\ClientesMercadoController;

// Dashboard Clientes & Mercado
Route::get('/clientes-mercado', [ClientesMercadoController::class, 'index'])
    ->name('clientes-mercado');

// API endpoints (para atualização AJAX)
Route::prefix('api/clientes-mercado')->group(function () {
    Route::get('/dashboard', [ClientesMercadoController::class, 'apiDashboard'])
        ->name('api.clientes-mercado.dashboard');
    
    Route::post('/limpar-cache', [ClientesMercadoController::class, 'limparCache'])
        ->name('api.clientes-mercado.limpar-cache');
});
