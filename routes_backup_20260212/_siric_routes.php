<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SiricController;

/*
|--------------------------------------------------------------------------
| SIRIC - Sistema de Análise de Crédito
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->prefix('siric')->name('siric.')->group(function () {

    Route::get('/', [SiricController::class, 'index'])->name('index');
    Route::get('/nova', [SiricController::class, 'create'])->name('create');
    Route::post('/', [SiricController::class, 'store'])->name('store');
    Route::get('/{id}', [SiricController::class, 'show'])->name('show');
    Route::delete('/{id}', [SiricController::class, 'destroy'])->name('destroy');

    // Ações
    Route::post('/{id}/coletar', [SiricController::class, 'coletarDados'])->name('coletar');
    Route::post('/{id}/analisar', [SiricController::class, 'analisarIA'])->name('analisar');
    Route::post('/{id}/decisao', [SiricController::class, 'salvarDecisao'])->name('decisao');
});
