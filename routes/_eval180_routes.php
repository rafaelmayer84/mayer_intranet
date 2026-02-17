<?php

use App\Http\Controllers\Eval180Controller;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Eval 180 Routes — Avaliação Humana 180°
|--------------------------------------------------------------------------
| Prefixo: /gdp (integrado ao módulo GDP existente)
*/

Route::middleware('auth')->group(function () {

    // ── Advogado: Minhas avaliações ──
    Route::get('/gdp/me/eval180', [Eval180Controller::class, 'meIndex'])
        ->name('gdp.eval180.me');

    Route::get('/gdp/me/eval180/{cycle}/{period}', [Eval180Controller::class, 'meForm'])
        ->name('gdp.eval180.me.form');

    Route::post('/gdp/me/eval180/{cycle}/{period}', [Eval180Controller::class, 'meSave'])
        ->name('gdp.eval180.me.save');

    // ── Gestor/Admin: Avaliações da equipe ──
    Route::get('/gdp/cycles/{id}/eval180', [Eval180Controller::class, 'cycleIndex'])
        ->name('gdp.eval180.cycle');

    Route::get('/gdp/cycles/{id}/eval180/{user}/{period}', [Eval180Controller::class, 'managerForm'])
        ->name('gdp.eval180.manager.form');

    Route::post('/gdp/cycles/{id}/eval180/{user}/{period}', [Eval180Controller::class, 'managerSave'])
        ->name('gdp.eval180.manager.save');

    Route::post('/gdp/cycles/{id}/eval180/{user}/{period}/lock', [Eval180Controller::class, 'lock'])
        ->name('gdp.eval180.lock');

    // ── Relatório consolidado ──
    Route::get('/gdp/cycles/{id}/eval180/report', [Eval180Controller::class, 'report'])
        ->name('gdp.eval180.report');
});
