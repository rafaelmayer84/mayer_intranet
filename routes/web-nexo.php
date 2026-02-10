<?php

/**
 * ADICIONAR ESTE CONTEÚDO AO FINAL DO ARQUIVO routes/web.php
 */

use App\Http\Controllers\NexoMonitorController;

Route::middleware(['auth'])->prefix('nexo')->group(function () {
    Route::get('/automacoes/monitor', [NexoMonitorController::class, 'index'])->name('nexo.monitor');
    Route::get('/automacoes/dados', [NexoMonitorController::class, 'dados'])->name('nexo.monitor.dados');
});
