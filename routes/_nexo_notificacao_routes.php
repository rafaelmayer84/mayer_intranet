<?php
use App\Http\Controllers\NexoNotificacaoController;
use Illuminate\Support\Facades\Route;
Route::middleware(['auth','modulo:operacional.nexo-notificacoes,visualizar'])->prefix('nexo/notificacoes')->name('nexo.notificacoes.')->group(function () {
    Route::get('/',                    [NexoNotificacaoController::class, 'index'])->name('index');
    Route::get('/buscar-clientes',     [NexoNotificacaoController::class, 'buscarClientes'])->name('buscar-clientes');
    Route::post('/aprovar-massa',      [NexoNotificacaoController::class, 'aprovarMassa'])->name('aprovar-massa');
    Route::post('/{id}/aprovar',       [NexoNotificacaoController::class, 'aprovar'])->name('aprovar');
    Route::post('/{id}/aprovar-os',    [NexoNotificacaoController::class, 'aprovarOS'])->name('aprovar-os');
    Route::post('/{id}/descartar',     [NexoNotificacaoController::class, 'descartar'])->name('descartar');
});
