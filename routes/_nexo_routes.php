<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NexoAtendimentoController;
use App\Http\Controllers\NexoGerencialController;
use App\Http\Controllers\NexoDataJuriController;
use App\Http\Controllers\NexoTicketController;
Route::middleware(['auth', 'user.active'])->group(function () {
    Route::prefix('nexo/atendimento')->middleware('modulo:operacional.nexo,visualizar')->group(function () {
        // -- Rotas existentes (INTOCADAS) ------------------------------
        Route::get('/', [NexoAtendimentoController::class, 'index'])->name('nexo.atendimento');
        Route::get('/conversas', [NexoAtendimentoController::class, 'conversas'])->name('nexo.atendimento.conversas');
        Route::get('/conversas/{id}', [NexoAtendimentoController::class, 'conversa'])->name('nexo.atendimento.conversa')->whereNumber('id');
        Route::post('/conversas/{id}/mensagens', [NexoAtendimentoController::class, 'enviarMensagem'])->name('nexo.atendimento.enviar')->whereNumber('id');
        Route::get('/conversas/{id}/poll', [NexoAtendimentoController::class, 'pollMessages'])->name('nexo.atendimento.poll')->whereNumber('id');
        Route::patch('/conversas/{id}/assign', [NexoAtendimentoController::class, 'assignUser'])->name('nexo.atendimento.assign')->whereNumber('id');
        Route::patch('/conversas/{id}/status', [NexoAtendimentoController::class, 'changeStatus'])->name('nexo.atendimento.status')->whereNumber('id');
        Route::post('/conversas/{id}/link-lead', [NexoAtendimentoController::class, 'linkLead'])->name('nexo.atendimento.link-lead')->whereNumber('id');
        Route::post('/conversas/{id}/link-cliente', [NexoAtendimentoController::class, 'linkCliente'])->name('nexo.atendimento.link-cliente')->whereNumber('id');
        Route::delete('/conversas/{id}/unlink-lead', [NexoAtendimentoController::class, 'unlinkLead'])->name('nexo.atendimento.unlink-lead')->whereNumber('id');
        Route::post('/leads/{leadId}/promover-crm', [NexoAtendimentoController::class, 'promoverLeadCrm'])->name('nexo.atendimento.promover-crm')->whereNumber('leadId');
        Route::delete('/conversas/{id}/unlink-cliente', [NexoAtendimentoController::class, 'unlinkCliente'])->name('nexo.atendimento.unlink-cliente')->whereNumber('id');
        Route::get('/conversas/{id}/contexto', [NexoAtendimentoController::class, 'contexto360'])->name('nexo.atendimento.contexto')->whereNumber('id');
        // -- Rotas DataJuri (v2026.02.07) ------------------------------
        Route::get('/conversas/{id}/contexto-datajuri', [NexoDataJuriController::class, 'contextoDataJuri'])->name('nexo.atendimento.contexto-datajuri')->whereNumber('id');
        Route::get('/conversas/{id}/buscar-clientes', [NexoDataJuriController::class, 'buscarClientes'])->name('nexo.atendimento.buscar-clientes')->whereNumber('id');
        Route::post('/conversas/{id}/auto-vincular-cliente', [NexoDataJuriController::class, 'autoVincularCliente'])->name('nexo.atendimento.auto-vincular')->whereNumber('id');
        Route::post('/conversas/{id}/link-processo', [NexoDataJuriController::class, 'linkProcesso'])->name('nexo.atendimento.link-processo')->whereNumber('id');
        Route::delete('/conversas/{id}/unlink-processo', [NexoDataJuriController::class, 'unlinkProcesso'])->name('nexo.atendimento.unlink-processo')->whereNumber('id');
        Route::get('/conversas/{id}/prazos-filtrados', [NexoDataJuriController::class, 'prazosFiltrados'])->name('nexo.atendimento.prazos-filtrados')->whereNumber('id');
        Route::get('/processo/{processoId}/detalhe', [NexoDataJuriController::class, 'processoDetalhe'])->name('nexo.atendimento.processo-detalhe')->whereNumber('processoId');
        // -- Rotas NEXO v2.0 (Prioridade, Notas, Flows) ---------------
        Route::patch('/conversas/{id}/priority', [NexoAtendimentoController::class, 'updatePriority'])->name('nexo.atendimento.priority')->whereNumber('id');
        Route::patch('/conversas/{id}/marked-unread', [NexoAtendimentoController::class, 'toggleMarkedUnread'])->name('nexo.atendimento.marked-unread')->whereNumber('id');
        Route::get('/conversas/{id}/notes', [NexoAtendimentoController::class, 'notes'])->name('nexo.atendimento.notes')->whereNumber('id');
        Route::post('/conversas/{id}/notes', [NexoAtendimentoController::class, 'storeNote'])->name('nexo.atendimento.notes.store')->whereNumber('id');
        Route::delete('/conversas/{id}/notes/{noteId}', [NexoAtendimentoController::class, 'deleteNote'])->name('nexo.atendimento.notes.delete');
        Route::get('/flows', [NexoAtendimentoController::class, 'flows'])->name('nexo.atendimento.flows');
        Route::post('/conversas/{id}/run-flow', [NexoAtendimentoController::class, 'runFlow'])->name('nexo.atendimento.run-flow')->whereNumber('id');
        Route::post('/conversas/{id}/reaction', [NexoAtendimentoController::class, 'reaction'])->name('nexo.atendimento.reaction')->whereNumber('id');
        Route::post('/conversas/{id}/assumir', [NexoAtendimentoController::class, 'assumirConversa'])->name('nexo.atendimento.assumir')->whereNumber('id');
        Route::get('/tickets/buscar-clientes', [\App\Http\Controllers\NexoTicketController::class, 'buscarClientes'])->name('nexo.tickets.buscar-clientes');
        Route::post('/conversas/{id}/devolver-bot', [NexoAtendimentoController::class, 'devolverAoBot'])->name('nexo.atendimento.devolver-bot')->whereNumber('id');

    // v2.1: Busca unificada para autocomplete (Bug 3)
    Route::get('/search-contacts', [NexoAtendimentoController::class, 'searchContacts'])->name('nexo.atendimento.search-contacts');
    });
    Route::patch('/nexo/atendimento/conversas/{id}/category', [NexoAtendimentoController::class, 'updateCategory'])->name('nexo.atendimento.category');
    Route::get('/nexo/atendimento/conversas/{id}/tags', [NexoAtendimentoController::class, 'getTags'])->name('nexo.atendimento.tags');
    Route::patch('/nexo/atendimento/conversas/{id}/tags', [NexoAtendimentoController::class, 'updateTags'])->name('nexo.atendimento.tags.update');

    // =========================================================
    // WHATSAPP TICKETS (16/02/2026)
    // =========================================================
    Route::prefix('nexo/tickets')->middleware('modulo:operacional.nexo,visualizar')->group(function () {
        Route::get('/', [NexoTicketController::class, 'index'])->name('nexo.tickets');
        Route::post('/', [NexoTicketController::class, 'store'])->name('nexo.tickets.store');
        Route::get('/{id}', [NexoTicketController::class, 'show'])->name('nexo.tickets.show')->whereNumber('id');
        Route::put('/{id}/atribuir', [NexoTicketController::class, 'atribuir'])->name('nexo.tickets.atribuir')->whereNumber('id');
        Route::put('/{id}/status', [NexoTicketController::class, 'mudarStatus'])->name('nexo.tickets.status')->whereNumber('id');
        Route::post('/{id}/nota', [NexoTicketController::class, 'adicionarNota'])->name('nexo.tickets.nota')->whereNumber('id');
        Route::put('/{id}/resolver', [NexoTicketController::class, 'resolver'])->name('nexo.tickets.resolver')->whereNumber('id');
        Route::delete('/{id}', [NexoTicketController::class, 'destroy'])->name('nexo.tickets.destroy')->whereNumber('id');
    });

    Route::prefix('nexo/gerencial')->middleware('modulo:operacional.nexo-gerencial,visualizar')->group(function () {
        Route::get('/', [NexoGerencialController::class, 'index'])->name('nexo.gerencial');
        Route::get('/data', [NexoGerencialController::class, 'data'])->name('nexo.gerencial.data');

    // === NEXO GERENCIAL: Escala + Drill-down ===
    Route::get('/drill/{tipo}', [NexoGerencialController::class, 'drillDown'])->name('nexo.gerencial.drill');
    Route::get('/escala', [NexoGerencialController::class, 'escala'])->name('nexo.gerencial.escala');
    Route::post('/escala', [NexoGerencialController::class, 'escalaStore'])->name('nexo.gerencial.escala.store');
    Route::delete('/escala/{id}', [NexoGerencialController::class, 'escalaDestroy'])->name('nexo.gerencial.escala.destroy');
    // === FIM NEXO GERENCIAL ===
    });
});