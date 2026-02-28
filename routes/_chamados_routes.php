<?php

use App\Http\Controllers\ChamadosController;
use App\Http\Controllers\Crm\CrmServiceRequestController;

Route::middleware(['auth'])->prefix('chamados')->name('chamados.')->group(function () {
    Route::get('/', [ChamadosController::class, 'index'])->name('index');
    Route::post('/', [ChamadosController::class, 'store'])->name('store');
    // Detalhes reusam o controller existente
    Route::get('/{id}', [CrmServiceRequestController::class, 'show'])->name('show');
    Route::put('/{id}', [CrmServiceRequestController::class, 'update'])->name('update');
    Route::post('/{id}/comments', [CrmServiceRequestController::class, 'addComment'])->name('comment');
});
