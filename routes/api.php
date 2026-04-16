<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\ClientesMercadoController;
use App\Http\Controllers\IntegracaoController;
use App\Http\Controllers\Api\EvidentiaMcpController;
use App\Http\Controllers\Api\EvidentiaMcpHttpController;
use App\Http\Controllers\Api\NexoAutoatendimentoController;
use App\Http\Controllers\Api\NexoInactivityController;
use App\Http\Controllers\Nexo\NexoTrackingController;

Route::prefix('sync')->middleware(['throttle:30,1', 'internal.api'])->group(function () {
    Route::get('/test-connection', [SyncController::class, 'testConnection']);
    Route::get('/status', [SyncController::class, 'status']);
    Route::get('/auth', [SyncController::class, 'auth']);
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

Route::prefix('clientes-mercado')->middleware('internal.api')->group(function () {
    Route::get('/top-clientes', [ClientesMercadoController::class, 'topClientes']);
    Route::get('/leads-recentes', [ClientesMercadoController::class, 'leadsRecentes']);
    Route::get('/resumo-executivo', [ClientesMercadoController::class, 'resumoExecutivo']);
    Route::get('/lancamentos', [ClientesMercadoController::class, 'lancamentos']);
    Route::get('/mix-pf-pj', [ClientesMercadoController::class, 'mixPfPj']);
});

Route::prefix('integracao')->middleware('internal.api')->group(function () {
    Route::get('/dados', [IntegracaoController::class, 'dados']);
    Route::post('/sincronizar-datajuri', [IntegracaoController::class, 'sincronizarDataJuri']);
    Route::get('/detalhes/{id}', [IntegracaoController::class, 'detalhes']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// NEXO Consulta — Automacao SendPulse
require __DIR__ . '/_nexo_consulta_routes.php';

// Tracking WhatsApp Lead (publico com rate limit)
Route::post('nexo/api/pre-track-whatsapp-lead', [
    NexoTrackingController::class,
    'preTrackWhatsAppLead'
])->middleware('throttle:60,1')->name('nexo.pre-track-whatsapp');

// NEXO Autoatendimento — Endpoints chamados pelo SendPulse
Route::prefix('nexo/autoatendimento')->middleware(['throttle:60,1'])->group(function () {
    Route::post('/financeiro/titulos-abertos', [NexoAutoatendimentoController::class, 'titulosAbertos']);
    Route::post('/financeiro/segunda-via', [NexoAutoatendimentoController::class, 'segundaVia']);
    Route::post('/compromissos/proximos', [NexoAutoatendimentoController::class, 'proximosCompromissos']);
    Route::post('/tickets/abrir', [NexoAutoatendimentoController::class, 'abrirTicket']);
    Route::post('/tickets/resumir-contexto', [NexoAutoatendimentoController::class, 'resumirContexto']);
    Route::post('/tickets/listar', [NexoAutoatendimentoController::class, 'listarTickets']);
    Route::post('/resumo', [NexoAutoatendimentoController::class, 'resumoLeigo']);
    Route::post('/chat-ia', [NexoAutoatendimentoController::class, 'chatIA']);
    Route::post('/documentos/solicitar', [NexoAutoatendimentoController::class, 'solicitarDocumento']);
    Route::post('/documentos/enviar', [NexoAutoatendimentoController::class, 'enviarDocumento']);
    Route::post('/agendamento/solicitar', [NexoAutoatendimentoController::class, 'solicitarAgendamento']);
    Route::post('/verificar-inatividade', [NexoInactivityController::class, 'verificarInatividade']);
    Route::post('/desativar-bot', [NexoAutoatendimentoController::class, 'desativarBot']);
    Route::post('/admin-processos/listar', [NexoAutoatendimentoController::class, 'processosAdministrativos']);
});
// --- NEXO QA: Webhook de Respostas de Pesquisa ---
Route::post('/webhooks/sendpulse/nexo-qa', [\App\Http\Controllers\Api\NexoQaWebhookController::class, 'handle'])->name('webhooks.sendpulse.nexo-qa');

// Evidentia MCP REST — endpoints para uso programático interno
Route::prefix('evidentia-mcp')->middleware(['evidentia.mcp', 'throttle:30,1'])->group(function () {
    Route::post('/search',          [EvidentiaMcpController::class, 'search']);
    Route::get('/results/{id}',     [EvidentiaMcpController::class, 'results']);
    Route::post('/citation/{id}',   [EvidentiaMcpController::class, 'citation']);
});

// Evidentia MCP HTTP — servidor MCP protocolo JSON-RPC 2.0 para Claude Desktop
Route::post('/mcp/evidentia', [EvidentiaMcpHttpController::class, 'handle'])
    ->middleware(['evidentia.mcp', 'throttle:30,1']);

// NEXO: servir media para SendPulse (sem auth - URL publica)
Route::get('/nexo/media/{filename}', function (string $filename) {
    $path = storage_path('app/public/nexo/media/' . $filename);
    if (!file_exists($path)) abort(404);
    return response()->file($path);
})->where('filename', '[a-zA-Z0-9._-]+');
