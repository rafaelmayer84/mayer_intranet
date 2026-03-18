<?php

use App\Http\Controllers\VigiliaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| VIGÍLIA Routes
|--------------------------------------------------------------------------
| Incluir no routes/web.php:
|   require __DIR__ . '/_vigilia_routes.php';
|
| Todas as rotas protegidas por auth middleware.
| Acesso: admin, socio, coordenador
*/

Route::middleware(['auth'])->prefix('vigilia')->name('vigilia.')->group(function () {

    // View principal (SPA-like com abas)
    Route::get('/', [VigiliaController::class, 'index'])->name('index');

    // API endpoints para AJAX
    Route::prefix('api')->group(function () {
        Route::get('/resumo', [VigiliaController::class, 'apiResumo'])->name('api.resumo');
        Route::get('/alertas', [VigiliaController::class, 'apiAlertas'])->name('api.alertas');
        Route::get('/compromissos', [VigiliaController::class, 'apiCompromissos'])->name('api.compromissos');
        Route::post('/cruzar', [VigiliaController::class, 'apiCruzar'])->name('api.cruzar');
        Route::get('/triggers', [VigiliaController::class, 'apiTriggers'])->name('api.triggers');
    });

    // Relatórios (views renderizadas no servidor)
    Route::get('/relatorio/individual', [VigiliaController::class, 'relatorioIndividual'])->name('relatorio.individual');
    Route::get('/relatorio/prazos', [VigiliaController::class, 'relatorioPrazos'])->name('relatorio.prazos');
    Route::get('/relatorio/consolidado', [VigiliaController::class, 'relatorioConsolidado'])->name('relatorio.consolidado');
    Route::get('/relatorio/cruzamento', [VigiliaController::class, 'relatorioCruzamento'])->name('relatorio.cruzamento');

    // Exportação
    Route::get('/export/excel', [VigiliaController::class, 'exportExcel'])->name('export.excel');
    Route::get('/export/pdf', [VigiliaController::class, 'exportPdf'])->name('export.pdf');
});
