<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NexoQaController;

Route::middleware(['auth', 'user.active'])->group(function () {
    Route::prefix('nexo/qualidade')->middleware('modulo:operacional.nexo-qualidade,visualizar')->group(function () {
        Route::get('/', [NexoQaController::class, 'index'])->name('nexo.qualidade.index');
        Route::post('/', [NexoQaController::class, 'store'])->name('nexo.qualidade.store');
        Route::patch('/{campaign}/toggle-status', [NexoQaController::class, 'toggleStatus'])->name('nexo.qualidade.toggle-status');
        Route::get('/{campaign}/targets', [NexoQaController::class, 'targets'])->name('nexo.qualidade.targets');
        Route::get('/{campaign}/respostas', [NexoQaController::class, 'respostas'])->name('nexo.qualidade.respostas');
        Route::patch('/{campaign}/config', [NexoQaController::class, 'updateConfig'])->name('nexo.qualidade.update-config');
        Route::delete('/{campaign}', [NexoQaController::class, 'destroy'])->name('nexo.qualidade.destroy');
    });
});
