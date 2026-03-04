<?php

/*
|--------------------------------------------------------------------------
| EVIDENTIA Routes
|--------------------------------------------------------------------------
| Adicionar no arquivo routes/web.php dentro do grupo auth middleware:
|
| require __DIR__ . '/evidentia.php';
|
*/

use App\Http\Controllers\EvidentiaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('evidentia')->name('evidentia.')->group(function () {

    // Busca
    Route::get('/', [EvidentiaController::class, 'index'])->name('index');
    Route::post('/search', [EvidentiaController::class, 'search'])->name('search');

    // Resultados
    Route::get('/resultados/{id}', [EvidentiaController::class, 'resultados'])->name('resultados');

    // Gerar bloco de citação
    Route::post('/resultados/{id}/gerar-bloco', [EvidentiaController::class, 'gerarBloco'])->name('gerar-bloco');

    // Visualizar jurisprudência individual
    Route::get('/juris/{tribunal}/{id}', [EvidentiaController::class, 'show'])->name('juris.show');

    // Admin: custos
    Route::get('/admin/custos', [EvidentiaController::class, 'custos'])
        ->middleware('can:admin')
        ->name('admin.custos');
});
