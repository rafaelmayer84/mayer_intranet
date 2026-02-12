<?php

/**
 * ADICIONAR ESTE CONTEÚDO AO FINAL DO ARQUIVO routes/api.php
 */

use App\Http\Controllers\Api\NexoWebhookController;

Route::prefix('nexo')->group(function () {
    Route::get('/identificar-cliente', [NexoWebhookController::class, 'identificarCliente']);
    Route::post('/perguntas-auth', [NexoWebhookController::class, 'perguntasAuth']);
    Route::post('/validar-auth', [NexoWebhookController::class, 'validarAuth']);
    Route::post('/consulta-status', [NexoWebhookController::class, 'consultaStatus']);
});
