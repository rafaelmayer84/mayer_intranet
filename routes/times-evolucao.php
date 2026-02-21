<?php

use App\Http\Controllers\TimesEvolucaoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas: Times & Evolução (BSC)
|--------------------------------------------------------------------------
| Incluir via: require __DIR__.'/times-evolucao.php'; em routes/web.php
| dentro do grupo middleware 'auth'
|--------------------------------------------------------------------------
*/

Route::middleware(['auth','modulo:resultados.times-evolucao,visualizar'])->group(function () {
    Route::get('/times-evolucao', [TimesEvolucaoController::class, 'index'])
        ->name('times-evolucao.index');

    Route::get('/times-evolucao/api/kpis', [TimesEvolucaoController::class, 'apiKpis'])
        ->name('times-evolucao.api.kpis');
});
