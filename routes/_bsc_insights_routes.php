<?php

use App\Http\Controllers\BscInsightsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| BSC Insights Routes
|--------------------------------------------------------------------------
| Prefix: nenhum (mesma raiz que /visao-gerencial, /clientes-mercado, etc.)
| Middleware: auth (herdado do grupo)
*/

Route::middleware(['auth'])->group(function () {
    Route::get('/bsc-insights', [BscInsightsController::class, 'index'])
        ->name('bsc-insights.index');

    Route::post('/bsc-insights/generate', [BscInsightsController::class, 'generate'])
        ->name('bsc-insights.generate');
});
