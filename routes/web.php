<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\ConfiguracaoController;
use App\Http\Controllers\ClassificacaoController;
// Rota raiz - redireciona para dashboard se logado, senão para login
Route::get('/', function () {
    return auth()->check() ? redirect()->route('avisos.index') : redirect()->route('login');
});
// Autenticação
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
// Rotas protegidas
Route::get('/', function () {
    return redirect()->route('visao-gerencial');
})->name('home');

Route::middleware(['auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/kpis/financeiros', [DashboardController::class, 'getKpisFinanceiros']);
    Route::get('/api/kpis/processos', [DashboardController::class, 'getKpisProcessos']);
    Route::get('/api/kpis/atividades', [DashboardController::class, 'getKpisAtividades']);
    Route::get('/api/kpis/horas', [DashboardController::class, 'getKpisHoras']);
    Route::get('/api/ranking', [DashboardController::class, 'getRankingAdvogados']);
    
    // Visão Gerencial (Resultados)
    Route::get('/visao-gerencial', [DashboardController::class, 'visaoGerencial'])->name('visao-gerencial');
    Route::get('/api/visao-gerencial', [DashboardController::class, 'visaoGerencialData'])->name('api.visao-gerencial');
    Route::get('/visao-gerencial/export', [DashboardController::class, 'visaoGerencialExport'])->name('visao-gerencial.export');
    
    // Configurar Metas
    Route::get('/configurar-metas', [DashboardController::class, 'configurarMetas'])->name('configurar-metas');
    Route::put('/configurar-metas', [DashboardController::class, 'updateMetas'])->name('configurar-metas.update');
    Route::post('/api/metas/salvar', [DashboardController::class, 'salvarMetas'])->name('metas.salvar');
    
    // Minha Performance
    Route::get('/minha-performance', [DashboardController::class, 'minhaPerformance'])->name('minha-performance');
    Route::post('/minha-performance', [DashboardController::class, 'minhaPerformance'])->name('minha-performance.update');
    
    // Equipe
    Route::get('/equipe', [DashboardController::class, 'equipe'])->name('equipe');
    Route::post('/equipe', [DashboardController::class, 'equipe'])->name('equipe.update');
    
    // Financeiro
    Route::get('/financeiro', [DashboardController::class, 'financeiro'])->name('financeiro');
    Route::post('/financeiro', [DashboardController::class, 'financeiro'])->name('financeiro.update');
    
    // Processos
    Route::get('/processos', [DashboardController::class, 'processos'])->name('processos');
    Route::post('/processos', [DashboardController::class, 'processos'])->name('processos.update');
    
    // Sincronização - AMBAS as rotas necessárias
    Route::get('/sync', [SyncController::class, 'index'])->name('sync');
    Route::get('/sync', [SyncController::class, 'index'])->name('sync.index');
    Route::get('/api/sync/status', [SyncController::class, 'status']);
    Route::post('/api/sync/advogados', [SyncController::class, 'syncAdvogados']);
    Route::post('/api/sync/processos', [SyncController::class, 'syncProcessos']);
    Route::post('/api/sync/atividades', [SyncController::class, 'syncAtividades']);
    Route::post('/api/sync/contas-receber', [SyncController::class, 'syncContasReceber']);
    Route::post('/api/sync/horas-trabalhadas', [SyncController::class, 'syncHorasTrabalhadas']);
    Route::post('/api/sync/movimentos', [SyncController::class, 'syncMovimentos']);
    Route::post('/api/sync/all', [SyncController::class, 'syncAll']);
    
    // ROTAS NOVAS DO CHATGPT - Prévia API e DB
    Route::get('/api/sync/movimentos/api-preview', [SyncController::class, 'apiPreviewMovimentos']);
    Route::post('/api/sync/movimentos/batch', [SyncController::class, 'syncMovimentosBatch']);
    Route::get('/api/sync/diagnostico', [SyncController::class, 'diagnosticoApi']);
    Route::get('/api/sync/movimentos/db', [SyncController::class, 'dbMovimentos']);
    Route::get('/sync/debug-log', [SyncController::class, 'debugLog']);
    
    // Configurações - AMBAS as rotas necessárias
    Route::get('/configuracoes', [ConfiguracaoController::class, 'index'])->name('configuracoes');
    Route::get('/configuracoes', [ConfiguracaoController::class, 'index'])->name('configuracoes.index');
    Route::post('/configuracoes', [ConfiguracaoController::class, 'update'])->name('configuracoes.update');
    Route::post('/configuracoes/metas', [ConfiguracaoController::class, 'saveMetas'])->name('configuracoes.metas');
    Route::post('/configuracoes/vincular', [ConfiguracaoController::class, 'vincularAdvogado'])->name('configuracoes.vincular');
    
    // Classificação Manual
    Route::get('/classificacao', [ClassificacaoController::class, 'index'])->name('classificacao');
    Route::post('/classificacao/aplicar', [ClassificacaoController::class, 'aplicar'])->name('classificacao.aplicar');
    Route::post('/classificacao/classificar', [ClassificacaoController::class, 'classificar']);
    Route::post('/classificacao/lote', [ClassificacaoController::class, 'classificarLote']);
    Route::post('/api/configuracoes/ano', [ConfiguracaoController::class, 'setAnoFiltro']);
    Route::post('/api/configuracoes/datajuri', [ConfiguracaoController::class, 'saveDataJuriCredentials']);
    Route::get('/api/configuracoes/datajuri/test', [ConfiguracaoController::class, 'testDataJuriConnection']);
});


// Quadro de Avisos + Diagnóstico
require __DIR__ . '/_avisos_routes.php';


// Metas KPI Mensais
Route::middleware(['auth'])->group(function () {
    Route::get('/administracao/metas-kpi-mensais', [App\Http\Controllers\Admin\KpiMonthlyTargetController::class, 'index'])->name('admin.metas-kpi-mensais');
    Route::post('/administracao/metas-kpi-mensais', [App\Http\Controllers\Admin\KpiMonthlyTargetController::class, 'store'])->name('admin.metas-kpi-mensais.store');
});

// Metas KPI Mensais - Configurações
Route::middleware(['auth'])->prefix('configuracoes')->name('config.')->group(function () {
    Route::get('metas-kpi-mensais', [App\Http\Controllers\Admin\KpiMonthlyTargetController::class, 'index'])->name('metas-kpi-mensais');
    Route::post('metas-kpi-mensais', [App\Http\Controllers\Admin\KpiMonthlyTargetController::class, 'store'])->name('metas-kpi-mensais.store');
});
