<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| BSC Insights — encerrado em 16/04/2026
| Substituído por Relatórios CEO (quinzenal, Claude Opus 4.7)
| /bsc-insights agora redireciona para /admin/relatorios-ceo
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/bsc-insights', function () {
        return redirect()->route('admin.relatorios-ceo.index');
    })->name('bsc-insights.index');

    // Rotas antigas — redireciona para a listagem
    Route::post('/bsc-insights/generate', function () {
        return redirect()->route('admin.relatorios-ceo.index');
    })->name('bsc-insights.generate');

    Route::get('/bsc-insights/status/{runId}', function () {
        return redirect()->route('admin.relatorios-ceo.index');
    })->name('bsc-insights.status');
});
