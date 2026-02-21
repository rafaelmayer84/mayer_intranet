<?php

use App\Http\Controllers\GdpController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GDP — Gestão de Desempenho de Pessoas
|--------------------------------------------------------------------------
| Incluído em routes/web.php dentro do grupo auth middleware
*/

Route::prefix('gdp')->name('gdp.')->middleware(['auth','modulo:gdp.minha-performance,visualizar'])->group(function () {

    // Minha Performance (sócio: só o seu; coordenador/admin: qualquer um via ?user_id=)
    Route::get('/', [GdpController::class, 'minhaPerformance'])->name('minha-performance');

    // Equipe (coordenador + admin)
    Route::get('/equipe', [GdpController::class, 'equipe'])->name('equipe')->middleware('modulo:gdp.equipe,visualizar');

    // Apurar mês (admin only, via AJAX POST)
    Route::post('/apurar', [GdpController::class, 'apurar'])->name('apurar');

    // Dados JSON de um usuário (gráficos)
    Route::get('/dados/{userId}', [GdpController::class, 'dadosUsuario'])->name('dados-usuario');


    // -- Acordo de Desempenho --
    Route::get('/acordo', [GdpController::class, 'acordo'])->name('acordo')->middleware('modulo:gdp.metas,visualizar');
    Route::post('/acordo', [GdpController::class, 'salvarAcordo'])->name('acordo.salvar')->middleware('modulo:gdp.metas,editar');
    Route::get('/acordo/{userId}/visualizar', [GdpController::class, 'visualizarAcordo'])->name('acordo.visualizar');
    Route::post('/acordo/{userId}/aceitar', [GdpController::class, 'aceitarAcordo'])->name('acordo.aceitar');
    Route::get('/acordo/{userId}/print', [GdpController::class, 'acordoPrint'])->name('acordo.print');


    // -- Penalizacoes --
    Route::get('/penalizacoes', [GdpController::class, 'penalizacoes'])->name('penalizacoes')->middleware('modulo:gdp.penalizacoes,visualizar');
    Route::post('/penalizacoes/manual', [GdpController::class, 'criarManual'])->name('penalizacoes.manual')->middleware('admin');
    Route::post('/penalizacoes/scanner', [GdpController::class, 'executarScanner'])->name('penalizacoes.scanner')->middleware('admin');
    Route::get('/penalizacoes/{id}/detalhes', [GdpController::class, 'detalhePenalizacao'])->name('penalizacoes.detalhes');
    Route::post('/penalizacoes/{id}/avaliar', [GdpController::class, 'avaliarContestacao'])->name('penalizacoes.avaliar')->middleware('admin');
    Route::post('/penalizacoes/{id}/contestar', [GdpController::class, 'contestarPenalizacao'])->name('penalizacoes.contestar');

});
