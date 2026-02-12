<?php
// ═══════════════════════════════════════════════════════════════
// NOVAS ROTAS NEXO v2.0 — ADICIONAR ao final de _nexo_routes.php
// (NÃO substituir o arquivo inteiro, apenas APPEND estas rotas
//  dentro do grupo de middleware 'auth' existente)
// ═══════════════════════════════════════════════════════════════

// Prioridade
Route::patch('/nexo/atendimento/conversas/{id}/priority', [App\Http\Controllers\NexoAtendimentoController::class, 'updatePriority'])->name('nexo.atendimento.priority');

// Notas internas
Route::get('/nexo/atendimento/conversas/{id}/notes', [App\Http\Controllers\NexoAtendimentoController::class, 'notes'])->name('nexo.atendimento.notes');
Route::post('/nexo/atendimento/conversas/{id}/notes', [App\Http\Controllers\NexoAtendimentoController::class, 'storeNote'])->name('nexo.atendimento.notes.store');
Route::delete('/nexo/atendimento/conversas/{id}/notes/{noteId}', [App\Http\Controllers\NexoAtendimentoController::class, 'deleteNote'])->name('nexo.atendimento.notes.delete');

// Flows
Route::get('/nexo/atendimento/flows', [App\Http\Controllers\NexoAtendimentoController::class, 'flows'])->name('nexo.atendimento.flows');
Route::post('/nexo/atendimento/conversas/{id}/run-flow', [App\Http\Controllers\NexoAtendimentoController::class, 'runFlow'])->name('nexo.atendimento.run-flow');
