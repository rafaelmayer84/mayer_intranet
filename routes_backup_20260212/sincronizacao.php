<?php

use App\Http\Controllers\Admin\SincronizacaoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas de Sincronização
|--------------------------------------------------------------------------
|
| Este arquivo contém todas as rotas relacionadas à sincronização de dados
| com APIs externas (DataJuri e ESPO CRM)
|
*/

Route::middleware(['auth'])->prefix('admin/sincronizacao')->name('admin.sincronizacao.')->group(function () {
    // Página principal
    Route::get('/', [SincronizacaoController::class, 'index'])->name('index');
    
    // Sincronização manual
    Route::post('/sync', [SincronizacaoController::class, 'sync'])->name('sync');
    
    // Verificar status da API
    Route::post('/check-status', [SincronizacaoController::class, 'checkStatus'])->name('check-status');
    
    // Limpar logs antigos
    Route::post('/clear-logs', [SincronizacaoController::class, 'clearLogs'])->name('clear-logs');
});
