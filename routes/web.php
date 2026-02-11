<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\ConfiguracaoController;
use App\Http\Controllers\ClassificacaoController;
// Rota raiz - redireciona para dashboard se logado, senão para login
Route::get("/", function () {
    return auth()->check() ? redirect()->route("avisos.index") : redirect()->route("login");
});
// Autenticação
Route::get("/login", [LoginController::class, "showLoginForm"])->name("login");
Route::post("/login", [LoginController::class, "login"]);
Route::post("/logout", [LoginController::class, "logout"])->name("logout");
// Rotas protegidas
Route::get("/", function () {    return redirect()->route("visao-gerencial");
})->name("home");

Route::middleware(["auth"])->group(function () {
    // Dashboard
    Route::get("/dashboard", [DashboardController::class, "index"])->name("dashboard");
    Route::get("/api/kpis/financeiros", [DashboardController::class, "getKpisFinanceiros"]);
    Route::get("/api/kpis/processos", [DashboardController::class, "getKpisProcessos"]);
    Route::get("/api/kpis/atividades", [DashboardController::class, "getKpisAtividades"]);
    Route::get("/api/kpis/horas", [DashboardController::class, "getKpisHoras"]);
    Route::get("/api/ranking", [DashboardController::class, "getRankingAdvogados"]);
    
    // Visão Gerencial (Resultados)
    Route::get("/visao-gerencial", [DashboardController::class, "visaoGerencial"])->name("visao-gerencial");
    Route::get("/api/visao-gerencial", [DashboardController::class, "visaoGerencialData"])->name("api.visao-gerencial");
    Route::get("/visao-gerencial/export", [DashboardController::class, "visaoGerencialExport"])->name("visao-gerencial.export");
    
    // Configurar Metas
    Route::get("/configurar-metas", [DashboardController::class, "configurarMetas"])->name("configurar-metas");
    Route::put("/configurar-metas", [DashboardController::class, "updateMetas"])->name("configurar-metas.update");
    Route::post("/api/metas/salvar", [DashboardController::class, "salvarMetas"])->name("metas.salvar");
    
    // Minha Performance
    Route::get("/minha-performance", [DashboardController::class, "minhaPerformance"])->name("minha-performance");
    Route::post("/minha-performance", [DashboardController::class, "minhaPerformance"])->name("minha-performance.update");
    
    // Equipe
    Route::get("/equipe", [DashboardController::class, "equipe"])->name("equipe");
    Route::post("/equipe", [DashboardController::class, "equipe"])->name("equipe.update");
    
    // Financeiro
    Route::get("/financeiro", [DashboardController::class, "financeiro"])->name("financeiro");
    Route::post("/financeiro", [DashboardController::class, "financeiro"])->name("financeiro.update");
    
    // Processos
    Route::get("/processos", [DashboardController::class, "processos"])->name("processos");
    Route::post("/processos", [DashboardController::class, "processos"])->name("processos.update");
    
    // Sincronização - AMBAS as rotas necessárias
    Route::get("/sync", [SyncController::class, "index"])->name("sync");
    Route::get("/sync", [SyncController::class, "index"])->name("sync.index");
    Route::get("/api/sync/status", [SyncController::class, "status"]);
    Route::post("/api/sync/advogados", [SyncController::class, "syncAdvogados"]);
    Route::post("/api/sync/processos", [SyncController::class, "syncProcessos"]);
    Route::post("/api/sync/atividades", [SyncController::class, "syncAtividades"]);
    Route::post("/api/sync/contas-receber", [SyncController::class, "syncContasReceber"]);
    Route::post("/api/sync/horas-trabalhadas", [SyncController::class, "syncHorasTrabalhadas"]);
    Route::post("/api/sync/movimentos", [SyncController::class, "syncMovimentos"]);
    Route::post("/api/sync/all", [SyncController::class, "syncAll"]);
    
    // ROTAS NOVAS DO CHATGPT - Prévia API e DB
    Route::get("/api/sync/movimentos/api-preview", [SyncController::class, "apiPreviewMovimentos"]);
    Route::post("/api/sync/movimentos/batch", [SyncController::class, "syncMovimentosBatch"]);
    Route::get("/api/sync/diagnostico", [SyncController::class, "diagnosticoApi"]);
    Route::get("/api/sync/movimentos/db", [SyncController::class, "dbMovimentos"]);
    Route::get("/sync/debug-log", [SyncController::class, "debugLog"]);
    
    // Configurações - AMBAS as rotas necessárias
    Route::get("/configuracoes", [ConfiguracaoController::class, "index"])->name("configuracoes");
    Route::get("/configuracoes", [ConfiguracaoController::class, "index"])->name("configuracoes.index");
    Route::post("/configuracoes", [ConfiguracaoController::class, "update"])->name("configuracoes.update");
    Route::post("/configuracoes/metas", [ConfiguracaoController::class, "saveMetas"])->name("configuracoes.metas");
    Route::post("/configuracoes/vincular", [ConfiguracaoController::class, "vincularAdvogado"])->name("configuracoes.vincular");
    Route::get("/configuracoes/resetar-classificacoes", [ConfiguracaoController::class, "resetarClassificacoes"])->name("configuracoes.resetar-classificacoes");
    
    // Classificação Manual
    Route::get("/classificacao", [ClassificacaoController::class, "index"])->name("classificacao");
    Route::post("/classificacao/aplicar", [ClassificacaoController::class, "aplicar"])->name("classificacao.aplicar");
    Route::post("/classificacao/classificar", [ClassificacaoController::class, "classificar"]);
    Route::post("/classificacao/lote", [ClassificacaoController::class, "classificarLote"]);
    Route::post("/api/configuracoes/ano", [ConfiguracaoController::class, "setAnoFiltro"]);
    Route::post("/api/configuracoes/datajuri", [ConfiguracaoController::class, "saveDataJuriCredentials"]);
    Route::get("/api/configuracoes/datajuri/test", [ConfiguracaoController::class, "testDataJuriConnection"]);
    
    // Clientes & Mercado
    Route::get("/clientes-mercado", [App\Http\Controllers\ClientesMercadoController::class, "index"])->name("clientes-mercado");
    Route::get("/api/clientes-mercado/top", [App\Http\Controllers\ClientesMercadoController::class, "topClientes"])->name("api.clientes-mercado.top");
    Route::get("/api/clientes-mercado/leads", [App\Http\Controllers\ClientesMercadoController::class, "leadsRecentes"])->name("api.clientes-mercado.leads");
    Route::get("/clientes-mercado/export", [App\Http\Controllers\ClientesMercadoController::class, "exportCsv"])->name("clientes-mercado.export");
    Route::get("/clientes-mercado/pdf", [App\Http\Controllers\ClientesMercadoController::class, "exportPdf"])->name("clientes-mercado.pdf");
});


// Quadro de Avisos + Diagnóstico
require __DIR__ . "/_avisos_routes.php";


// Metas KPI Mensais
Route::middleware(["auth"])->group(function () {
    Route::get("/administracao/metas-kpi-mensais", [App\Http\Controllers\Admin\KpiMonthlyTargetController::class, "index"])->name("admin.metas-kpi-mensais");
    Route::post("/administracao/metas-kpi-mensais", [App\Http\Controllers\Admin\KpiMonthlyTargetController::class, "store"])->name("admin.metas-kpi-mensais.store");
});

// Metas KPI Mensais - Configurações
Route::middleware(["auth"])->prefix("configuracoes")->name("config.")->group(function () {
    Route::get("metas-kpi-mensais", [App\Http\Controllers\Admin\KpiMonthlyTargetController::class, "index"])->name("metas-kpi-mensais");
    Route::post("metas-kpi-mensais", [App\Http\Controllers\Admin\KpiMonthlyTargetController::class, "store"])->name("metas-kpi-mensais.store");
});

// Integrações (Admin only)
Route::middleware(["auth"])->group(function () {
    Route::get("/integracao", [App\Http\Controllers\IntegracaoController::class, "index"])->name("integration.index");
    Route::get("/integracao/sync", [App\Http\Controllers\IntegracaoController::class, "sync"])->name("integration.sync");
    Route::get("/integracao/{log}", [App\Http\Controllers\IntegracaoController::class, "show"])->name("integration.show");
    Route::post("/integracao/sincronizar-datajuri", [App\Http\Controllers\IntegracaoController::class, "sincronizarDataJuri"])->name("integration.sync.datajuri");
    Route::post("/integracao/sincronizar-espocrm", [App\Http\Controllers\IntegracaoController::class, "sincronizarEspoCrm"])->name("integration.sync.espocrm");
});

// ============================================================================
// MÓDULO CLIENTES & MERCADO
// ============================================================================
use App\Http\Controllers\ClientesMercadoController;

// ============================================================================
// MÓDULO CLASSIFICAÇÃO DE REGRAS
// ============================================================================
Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    
    // Rotas de CRUD
    Route::resource('classificacao-regras', \App\Http\Controllers\Admin\ClassificacaoRegrasController::class)
        ->parameters(['classificacao-regras' => 'regra']);
    
    // Ações especiais
    Route::post('classificacao-regras/importar', 
        [\App\Http\Controllers\Admin\ClassificacaoRegrasController::class, 'importar'])
        ->name('classificacao-regras.importar');
    
    Route::post('classificacao-regras/reclassificar', 
        [\App\Http\Controllers\Admin\ClassificacaoRegrasController::class, 'reclassificar'])
        ->name('classificacao-regras.reclassificar');
    
    Route::patch('classificacao-regras/{regra}/toggle', 
        [\App\Http\Controllers\Admin\ClassificacaoRegrasController::class, 'toggleStatus'])
        ->name('classificacao-regras.toggle');
    
    Route::get('classificacao-regras/exportar/csv', 
        [\App\Http\Controllers\Admin\ClassificacaoRegrasController::class, 'exportar'])
        ->name('classificacao-regras.exportar');
});

// Rotas de Sincronização
require __DIR__.'/sincronizacao.php';

// Classificação de Planos de Contas - CORRIGIDO COM MIDDLEWARE E NOMES DE ROTA
Route::middleware(['auth'])->prefix('admin/classificacao')->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\ClassificacaoRegraController::class, 'index'])
        ->name('admin.classificacao.index');
    
    Route::post('/', [App\Http\Controllers\Admin\ClassificacaoRegraController::class, 'store'])
        ->name('admin.classificacao.store');
    
    Route::get('/{id}', [App\Http\Controllers\Admin\ClassificacaoRegraController::class, 'show'])
        ->name('admin.classificacao.show');
    
    Route::delete('/{id}', [App\Http\Controllers\Admin\ClassificacaoRegraController::class, 'destroy'])
        ->name('admin.classificacao.destroy');
    
    Route::post('/reclassificar', [App\Http\Controllers\Admin\ClassificacaoRegraController::class, 'reclassificar'])
        ->name('admin.classificacao.reclassificar');
    
    Route::post('/importar', [App\Http\Controllers\Admin\ClassificacaoRegraController::class, 'importar'])
        ->name('admin.classificacao.importar');
    
    Route::get('/estatisticas', [App\Http\Controllers\Admin\ClassificacaoRegraController::class, 'estatisticas'])
        ->name('admin.classificacao.estatisticas');
});

// Sincronização Unificada (substitui sincronizacao.php)
require __DIR__."/sincronizacao-unificada.php";

// Rotas de Sincronização DataJuri (Admin)
Route::post('/admin/integracoes/sync-datajuri', [App\Http\Controllers\Admin\IntegracoesController::class, 'syncDataJuri'])->middleware('auth')->name('admin.integracoes.sync-datajuri');

// Gestão de Usuários
require __DIR__ . '/_usuarios_routes.php';

Route::middleware(['auth'])->get('admin/sincronizacao-unificada/testar-conexao', [\App\Http\Controllers\Admin\SincronizacaoUnificadaController::class, 'smokeTest'])->name('admin.sincronizacao-unificada.testar-conexao');

// Central de Leads
require __DIR__."/_leads_routes.php";
require __DIR__.'/_leads_routes.php';
require __DIR__ . "/_nexo_routes.php";


use App\Http\Controllers\NexoMonitorController;
Route::middleware(['auth'])->prefix('nexo')->group(function () {
    Route::get('/automacoes/monitor', [NexoMonitorController::class, 'index'])->name('nexo.monitor');
    Route::get('/automacoes/dados', [NexoMonitorController::class, 'dados'])->name('nexo.monitor.dados');
});

// Manuais Normativos
require __DIR__ . '/_manuais_routes.php';

// Processos Internos (BSC)
require __DIR__ . '/_processos_internos_routes.php';
require __DIR__.'/_siric_routes.php';
