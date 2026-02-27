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

    // ── Abrir avaliação batch (todos profissionais do mês) ──
    Route::post('/gdp/batch-eval180', [Eval180Controller::class, 'batchOpen'])
        ->name('gdp.eval180.batch-open');

    // ── Criar avaliação avulsa + notificar ──
    Route::post('/gdp/cycles/{id}/eval180/create', [Eval180Controller::class, 'createEval'])
        ->name('gdp.eval180.create')->middleware('admin');

    // ── Liberar feedback para avaliado ver ──
    Route::post('/gdp/cycles/{id}/eval180/{user}/{period}/release-feedback', [Eval180Controller::class, 'releaseFeedback'])
        ->name('gdp.eval180.release-feedback');

    // ── Excluir avaliação (admin only, hard delete) ──
    Route::delete('/gdp/cycles/{id}/eval180/{user}/{period}', [Eval180Controller::class, 'deleteEval'])
        ->name('gdp.eval180.delete')->middleware('admin');
});
