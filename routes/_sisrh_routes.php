<?php

use App\Http\Controllers\SisrhController;
use App\Http\Controllers\GdpAcompanhamentoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SISRH Routes — Gestão de Advogados + Apuração de Remuneração
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->prefix('sisrh')->group(function () {
    // Visão geral (todos autenticados, controller filtra por role)
    Route::get('/', [SisrhController::class, 'index'])->name('sisrh.index');

    // Regras RB (admin/sócio)
    Route::get('/regras-rb', [SisrhController::class, 'regrasRb'])->name('sisrh.regras-rb');
        Route::post('/regras-rb/senioridade', [SisrhController::class, 'salvarSenioridade'])->name('sisrh.senioridade.salvar');
    Route::post('/regras-rb/nivel', [SisrhController::class, 'salvarRbNivel'])->name('sisrh.rb-nivel.salvar');
    Route::post('/regras-rb/override', [SisrhController::class, 'salvarRbOverride'])->name('sisrh.rb-override.salvar');
    Route::post('/regras-rb/faixa', [SisrhController::class, 'salvarFaixa'])->name('sisrh.faixa.salvar');
    Route::delete('/regras-rb/faixa/{id}', [SisrhController::class, 'excluirFaixa'])->name('sisrh.faixa.excluir');

    // Apuração (admin/sócio)
    Route::get('/apuracao', [SisrhController::class, 'apuracao'])->name('sisrh.apuracao');
    Route::post('/apuracao/simular', [SisrhController::class, 'simular'])->name('sisrh.apuracao.simular');
    Route::post('/apuracao/fechar', [SisrhController::class, 'fecharCompetencia'])->name('sisrh.apuracao.fechar');

    // Espelho individual
    Route::get('/espelho/{ano}/{mes}/{user}', [SisrhController::class, 'espelho'])->name('sisrh.espelho');

    // Banco de créditos
    Route::get('/banco-creditos', [SisrhController::class, 'bancoCreditos'])->name('sisrh.banco-creditos');

    // Ajustes (admin/sócio)
    Route::post('/ajuste', [SisrhController::class, 'lancarAjuste'])->name('sisrh.ajuste.lancar');
});

/*
|--------------------------------------------------------------------------
| GDP Acompanhamento Bimestral Routes (extensão do GDP)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->prefix('gdp/acompanhamento')->group(function () {
    Route::get('/', [GdpAcompanhamentoController::class, 'index'])->name('gdp.acompanhamento');
    Route::post('/submeter', [GdpAcompanhamentoController::class, 'submeter'])->name('gdp.acompanhamento.submeter');
    Route::get('/admin', [GdpAcompanhamentoController::class, 'admin'])->name('gdp.acompanhamento.admin');
    Route::post('/validar/{id}', [GdpAcompanhamentoController::class, 'validar'])->name('gdp.acompanhamento.validar');
});
