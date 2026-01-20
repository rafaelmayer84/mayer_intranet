<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SyncController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Rotas de API para sincronização (sem autenticação para facilitar)
Route::prefix('sync')->group(function () {
    Route::get('/test-connection', [SyncController::class, 'testConnection']);
    Route::get('/status', [SyncController::class, 'status']);
    Route::get('/auth', [SyncController::class, 'auth']);
    
    // Rotas GET e POST para sincronização
    Route::match(['get', 'post'], '/advogados', [SyncController::class, 'syncAdvogados']);
    Route::match(['get', 'post'], '/processos', [SyncController::class, 'syncProcessos']);
    Route::match(['get', 'post'], '/atividades', [SyncController::class, 'syncAtividades']);
    Route::match(['get', 'post'], '/contas', [SyncController::class, 'syncContas']);
    Route::match(['get', 'post'], '/contas-receber', [SyncController::class, 'syncContasReceber']);
    Route::match(['get', 'post'], '/horas', [SyncController::class, 'syncHoras']);
    Route::match(['get', 'post'], '/movimentos', [SyncController::class, 'syncMovimentos']);
    Route::match(['get', 'post'], '/all', [SyncController::class, 'syncAll']);
    
    Route::get('/dados/{tipo}', [SyncController::class, 'getDados']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
