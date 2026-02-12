<?php

/**
 * =====================================================================
 * NEXO CONSULTA — Rotas de API para automação SendPulse
 * =====================================================================
 * 
 * ATENÇÃO: Estas rotas NÃO usam middleware 'auth'.
 * São chamadas pelo bot SendPulse via token no header X-Sendpulse-Token.
 * 
 * COMO INTEGRAR:
 * Adicionar no routes/api.php (fora de qualquer middleware auth):
 *   require __DIR__ . '/_nexo_consulta_routes.php';
 * 
 * URLs resultantes (prefixo /api/ automático do Laravel):
 *   POST /api/nexo/identificar-cliente
 *   POST /api/nexo/perguntas-auth
 *   POST /api/nexo/validar-auth
 *   POST /api/nexo/consulta-status
 *   POST /api/nexo/consulta-status-processo
 */

use App\Http\Controllers\NexoConsultaController;

Route::prefix('nexo')->group(function () {
    Route::post('/identificar-cliente', [NexoConsultaController::class, 'identificarCliente']);
    Route::post('/perguntas-auth', [NexoConsultaController::class, 'perguntasAuth']);
    Route::post('/validar-auth', [NexoConsultaController::class, 'validarAuth']);
    Route::post('/consulta-status', [NexoConsultaController::class, 'consultaStatus']);
    Route::post('/consulta-status-processo', [NexoConsultaController::class, 'consultaStatusProcesso']);
});
