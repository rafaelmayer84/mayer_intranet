<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ManualNormativoController;
use App\Http\Controllers\Admin\ManuaisNormativosController;

/*
|--------------------------------------------------------------------------
| Manuais Normativos Routes
|--------------------------------------------------------------------------
| Incluir no web.php: require __DIR__ . '/_manuais_routes.php';
*/

// ── Tela pública (usuário logado) ──
Route::middleware(['auth'])->group(function () {
    Route::get('/manuais-normativos', [ManualNormativoController::class, 'index'])
        ->name('manuais-normativos.index');
});

// ── Admin CRUD ──
Route::middleware(['auth'])->prefix('admin/manuais-normativos')->group(function () {

    // Grupos
    Route::get('/grupos', [ManuaisNormativosController::class, 'gruposIndex'])
        ->name('admin.manuais.grupos.index');
    Route::get('/grupos/criar', [ManuaisNormativosController::class, 'gruposCreate'])
        ->name('admin.manuais.grupos.create');
    Route::post('/grupos', [ManuaisNormativosController::class, 'gruposStore'])
        ->name('admin.manuais.grupos.store');
    Route::get('/grupos/{grupo}/editar', [ManuaisNormativosController::class, 'gruposEdit'])
        ->name('admin.manuais.grupos.edit');
    Route::put('/grupos/{grupo}', [ManuaisNormativosController::class, 'gruposUpdate'])
        ->name('admin.manuais.grupos.update');
    Route::delete('/grupos/{grupo}', [ManuaisNormativosController::class, 'gruposDestroy'])
        ->name('admin.manuais.grupos.destroy');

    // Documentos
    Route::get('/documentos', [ManuaisNormativosController::class, 'documentosIndex'])
        ->name('admin.manuais.documentos.index');
    Route::get('/documentos/criar', [ManuaisNormativosController::class, 'documentosCreate'])
        ->name('admin.manuais.documentos.create');
    Route::post('/documentos', [ManuaisNormativosController::class, 'documentosStore'])
        ->name('admin.manuais.documentos.store');
    Route::get('/documentos/{documento}/editar', [ManuaisNormativosController::class, 'documentosEdit'])
        ->name('admin.manuais.documentos.edit');
    Route::put('/documentos/{documento}', [ManuaisNormativosController::class, 'documentosUpdate'])
        ->name('admin.manuais.documentos.update');
    Route::delete('/documentos/{documento}', [ManuaisNormativosController::class, 'documentosDestroy'])
        ->name('admin.manuais.documentos.destroy');

    // Permissões
    Route::get('/permissoes', [ManuaisNormativosController::class, 'permissoesIndex'])
        ->name('admin.manuais.permissoes.index');
    Route::put('/permissoes', [ManuaisNormativosController::class, 'permissoesUpdate'])
        ->name('admin.manuais.permissoes.update');
});
