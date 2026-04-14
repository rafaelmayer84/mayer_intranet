<?php

/**
 * ============================================================================
 * SIRIC v2 — Rotas
 * ============================================================================
 *
 * Define os endpoints HTTP do sistema de análise de crédito.
 * Todas as rotas são protegidas por auth + user.active + permissão de módulo.
 *
 * Endpoints:
 *   GET  /siric/           → index (listar consultas com filtros)
 *   GET  /siric/nova       → create (formulário de nova consulta)
 *   POST /siric/           → store (salvar nova consulta)
 *   GET  /siric/{id}       → show (detalhe de uma consulta)
 *   DEL  /siric/{id}       → destroy (excluir consulta)
 *   POST /siric/{id}/coletar  → coletarDados (coleta interna do BD)
 *   POST /siric/{id}/analisar → analisarIA (análise completa: gate + serasa + IA)
 *   POST /siric/{id}/decisao  → salvarDecisao (decisão humana final)
 * ============================================================================
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SiricController;

Route::middleware(['auth', 'user.active', 'modulo:operacional.siric,visualizar'])->prefix('siric')->name('siric.')->group(function () {

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
