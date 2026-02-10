<?php

/**
 * Rotas do módulo RESULTADOS! > BSC > Processos Internos
 *
 * Arquivo: routes/_processos_internos_routes.php
 *
 * Incluir no routes/web.php com:
 *   require __DIR__ . '/_processos_internos_routes.php';
 *
 * Todas as rotas exigem autenticação (middleware 'auth' aplicado no controller).
 */

use App\Http\Controllers\Dashboard\ProcessosInternosController;
use Illuminate\Support\Facades\Route;

Route::prefix('resultados/bsc/processos-internos')
    ->name('resultados.bsc.processos-internos.')
    ->group(function () {

        // Dashboard principal
        Route::get('/', [ProcessosInternosController::class, 'index'])
            ->name('index');

        // Drilldown (JSON paginado) - tipos: backlog, wip, sem_andamento, throughput
        Route::get('/drilldown/{tipo}', [ProcessosInternosController::class, 'drilldown'])
            ->name('drilldown')
            ->where('tipo', 'backlog|wip|sem_andamento|throughput|sla_ok|sla_nok');

        // Exportação CSV
        Route::get('/export', [ProcessosInternosController::class, 'export'])
            ->name('export');
    });
