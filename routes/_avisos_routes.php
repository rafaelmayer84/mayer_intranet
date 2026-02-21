<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AvisoController;
use App\Http\Controllers\CategoriaAvisoController;

/*
|--------------------------------------------------------------------------
| Quadro de Avisos - Rotas
|--------------------------------------------------------------------------
| - Rotas públicas (autenticadas): /avisos, /avisos/{aviso}
| - Endpoint AJAX: marcar como lido: POST /avisos/{aviso}/lido (avisos.lido)
| - Admin: /admin/avisos (CRUD) e /admin/categorias-avisos (CRUD)
|
| Obs: Não mexe em permissões aqui; manter middleware/auth do projeto.
*/

Route::middleware('auth')->group(function () {

    // Quadro de Avisos (usuário)
    Route::get('/avisos', [AvisoController::class, 'index'])->name('avisos.index');

    Route::get('/avisos/{aviso}', [AvisoController::class, 'show'])
        ->whereNumber('aviso')
        ->name('avisos.show');

    // Marcar como lido (AJAX) — usado em avisos.show
    Route::post('/avisos/{aviso}/lido', [AvisoController::class, 'marcarComoLido'])
        ->whereNumber('aviso')
        ->name('avisos.lido');

    // Admin: Avisos
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/avisos', [AvisoController::class, 'admin'])->name('avisos.index');
        Route::get('/avisos/create', [AvisoController::class, 'create'])->name('avisos.create');
        Route::post('/avisos', [AvisoController::class, 'store'])->name('avisos.store');

        Route::get('/avisos/{aviso}/edit', [AvisoController::class, 'edit'])
            ->whereNumber('aviso')
            ->name('avisos.edit');

        Route::put('/avisos/{aviso}', [AvisoController::class, 'update'])
            ->whereNumber('aviso')
            ->name('avisos.update');

        Route::delete('/avisos/{aviso}', [AvisoController::class, 'destroy'])
            ->whereNumber('aviso')
            ->name('avisos.destroy');

        // Admin: Categorias de Avisos
        Route::get('/categorias-avisos', [CategoriaAvisoController::class, 'index'])->name('categorias-avisos.index');
        Route::get('/categorias-avisos/create', [CategoriaAvisoController::class, 'create'])->name('categorias-avisos.create');
        Route::post('/categorias-avisos', [CategoriaAvisoController::class, 'store'])->name('categorias-avisos.store');

        Route::get('/categorias-avisos/{categoriaAviso}/edit', [CategoriaAvisoController::class, 'edit'])
            ->whereNumber('categoriaAviso')
            ->name('categorias-avisos.edit');

        Route::put('/categorias-avisos/{categoriaAviso}', [CategoriaAvisoController::class, 'update'])
            ->whereNumber('categoriaAviso')
            ->name('categorias-avisos.update');

        Route::delete('/categorias-avisos/{categoriaAviso}', [CategoriaAvisoController::class, 'destroy'])
            ->whereNumber('categoriaAviso')
            ->name('categorias-avisos.destroy');
    });

    // Compatibilidade: telas antigas apontando para route('avisos.admin')
    Route::get('/admin/avisos-alias', fn () => redirect()->route('admin.avisos.index'))
        ->name('avisos.admin');
});
