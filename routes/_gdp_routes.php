<?php

use App\Http\Controllers\GdpController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GDP — Gestão de Desempenho de Pessoas
|--------------------------------------------------------------------------
| Incluído em routes/web.php dentro do grupo auth middleware
*/

Route::prefix('gdp')->name('gdp.')->middleware('auth')->group(function () {

    // Minha Performance (sócio: só o seu; coordenador/admin: qualquer um via ?user_id=)
    Route::get('/', [GdpController::class, 'minhaPerformance'])->name('minha-performance');

    // Equipe (coordenador + admin)
    Route::get('/equipe', [GdpController::class, 'equipe'])->name('equipe');

    // Apurar mês (admin only, via AJAX POST)
    Route::post('/apurar', [GdpController::class, 'apurar'])->name('apurar');

    // Dados JSON de um usuário (gráficos)
    Route::get('/dados/{userId}', [GdpController::class, 'dadosUsuario'])->name('dados-usuario');


    // -- Acordo de Desempenho --
    Route::get('/acordo', [GdpController::class, 'acordo'])->name('acordo');
    Route::post('/acordo', [GdpController::class, 'salvarAcordo'])->name('acordo.salvar');
    Route::get('/acordo/{userId}/visualizar', [GdpController::class, 'visualizarAcordo'])->name('acordo.visualizar');
    Route::post('/acordo/{userId}/aceitar', [GdpController::class, 'aceitarAcordo'])->name('acordo.aceitar');
    Route::get('/acordo/{userId}/print', [GdpController::class, 'acordoPrint'])->name('acordo.print');

});
