<?php

use App\Http\Controllers\Crm\CrmDashboardController;
use App\Http\Controllers\Crm\CrmDistributionController;
use App\Http\Controllers\Crm\CrmLeadsController;
use App\Http\Controllers\Crm\CrmCarteiraController;
use App\Http\Controllers\Crm\CrmPipelineController;
use App\Http\Controllers\Crm\CrmAccountController;
use App\Http\Controllers\Crm\CrmOpportunityController;
use App\Http\Controllers\Crm\CrmReportsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CRM V2 Routes
|--------------------------------------------------------------------------
*/


Route::middleware(['auth','modulo:operacional.crm,visualizar'])->prefix('crm')->name('crm.')->group(function () {

    // ── Painel Gerencial CRM ──
    Route::get('/painel', [\App\Http\Controllers\CrmPainelController::class, 'index'])->name('painel');
    Route::post('/painel/generate-digest', [\App\Http\Controllers\CrmPainelController::class, 'generateDigest'])->name('painel.generate-digest');
    Route::post('/painel/account-action/{accountId}', [\App\Http\Controllers\CrmPainelController::class, 'generateAccountAction'])->name('painel.account-action');

    // Dashboard (home CRM)
    Route::get('/', [CrmDashboardController::class, 'index'])->name('dashboard');

    // Leads (qualificados)
    Route::get('/leads', [CrmLeadsController::class, 'index'])->name('leads');
    Route::post('/leads/{id}/status', [CrmLeadsController::class, 'updateStatus'])->name('leads.status');
    Route::post('/leads/{id}/assign', [CrmLeadsController::class, 'assignOwner'])->name('leads.assign');
    Route::post('/leads/manual', [CrmLeadsController::class, 'storeManual'])->name('leads.store-manual');

    // Carteira
    Route::get('/carteira', [CrmCarteiraController::class, 'index'])->name('carteira');
    Route::post('/carteira/bulk-assign', [CrmCarteiraController::class, 'bulkAssign'])->name('carteira.bulk-assign');
    Route::post('/carteira/bulk-action', [CrmCarteiraController::class, 'bulkAction'])->name('carteira.bulk-action');

    // Distribuição de Carteira (Admin)
    Route::get('/distribuicao', [CrmDistributionController::class, 'index'])->name('distribution');
    Route::put('/distribuicao/perfil/{id}', [CrmDistributionController::class, 'updateProfile'])->name('distribution.update-profile');
    Route::post('/distribuicao/gerar', [CrmDistributionController::class, 'generate'])->name('distribution.generate');
    Route::get('/distribuicao/{id}/revisar', [CrmDistributionController::class, 'review'])->name('distribution.review');
    Route::post('/distribuicao/{id}/aplicar', [CrmDistributionController::class, 'apply'])->name('distribution.apply');

    // Pipeline (Oportunidades)
    Route::get('/pipeline', [CrmPipelineController::class, 'index'])->name('pipeline');
    Route::post('/pipeline/{id}/move', [CrmPipelineController::class, 'moveStage'])->name('pipeline.move');
    Route::post('/pipeline/{id}/won', [CrmPipelineController::class, 'markWon'])->name('pipeline.won');
    Route::post('/pipeline/{id}/lost', [CrmPipelineController::class, 'markLost'])->name('pipeline.lost');
    Route::delete('/pipeline/{id}', [CrmPipelineController::class, 'destroy'])->name('pipeline.destroy');

    // Account — criação manual (admin)
    Route::get('/accounts/create', [CrmAccountController::class, 'create'])->name('accounts.create');
    Route::post('/accounts', [CrmAccountController::class, 'store'])->name('accounts.store');
    Route::get('/accounts/search', [CrmAccountController::class, 'search'])->name('accounts.search');

    // Account 360
    Route::get('/accounts/{id}', [CrmAccountController::class, 'show'])->name('accounts.show');
    Route::post('/accounts/{id}/gate-revisar', [CrmAccountController::class, 'marcarRevisao'])->name('accounts.gate-revisar');
    Route::delete('/accounts/{id}', [CrmAccountController::class, 'destroy'])->name('accounts.destroy');
    Route::put('/accounts/{id}', [CrmAccountController::class, 'update'])->name('accounts.update');
    Route::post('/accounts/{id}/opportunities', [CrmAccountController::class, 'createOpportunity'])->name('accounts.create-opp');
    Route::post('/accounts/{id}/activities', [CrmAccountController::class, 'storeActivity'])->name('accounts.store-activity');
    Route::post('/accounts/{id}/activities/{activityId}/complete', [CrmAccountController::class, 'completeActivity'])->name('accounts.complete-activity');
    Route::get('/accounts/{id}/activities/{activityId}/pdf', [CrmAccountController::class, 'generateVisitPdf'])->name('accounts.visit-pdf');

    // Transferência de responsável (admin/coordenador)
    Route::post('/accounts/{id}/transfer', [CrmAccountController::class, 'transferOwner'])->name('accounts.transfer');

    // Arquivamento
    Route::post('/accounts/{id}/archive', [CrmAccountController::class, 'archive'])->name('accounts.archive');
    Route::post('/accounts/{id}/unarchive', [CrmAccountController::class, 'unarchive'])->name('accounts.unarchive');

    // Opportunity 360
    Route::get('/oportunidades/{id}', [CrmOpportunityController::class, 'show'])->name('opportunities.show');
    Route::put('/oportunidades/{id}', [CrmOpportunityController::class, 'update'])->name('opportunities.update');
    Route::post('/oportunidades/{id}/activities', [CrmOpportunityController::class, 'storeActivity'])->name('opportunities.store-activity');
    Route::post('/oportunidades/{id}/activities/{activityId}/complete', [CrmOpportunityController::class, 'completeActivity'])->name('opportunities.complete-activity');
    Route::post('/oportunidades/{id}/cadence/{taskId}/complete', [CrmOpportunityController::class, 'completeCadenceTask'])->name('opportunities.complete-cadence');

    // Documentos (upload/delete)
    Route::post('/accounts/{id}/documents', [CrmAccountController::class, 'uploadDocument'])->name('accounts.upload-document');
    Route::delete('/accounts/{id}/documents/{docId}', [CrmAccountController::class, 'deleteDocument'])->name('accounts.delete-document');

    // Inadimplência — fluxo decisório e evidências de cobrança
    Route::post('/accounts/{id}/inadimplencia/decisao', [CrmAccountController::class, 'storeDecisaoInadimplencia'])->name('accounts.inadimplencia.decisao');
    Route::post('/accounts/{id}/inadimplencia/evidencia/{activityId}', [CrmAccountController::class, 'uploadEvidenciaCobranca'])->name('accounts.inadimplencia.evidencia');

    // Solicitações Internas (Service Requests)
    Route::post('/accounts/{id}/service-requests', [\App\Http\Controllers\Crm\CrmServiceRequestController::class, 'store'])->name('service-requests.store');
    Route::get('/solicitacoes/{id}', [\App\Http\Controllers\Crm\CrmServiceRequestController::class, 'show'])->name('service-requests.show');
    Route::put('/solicitacoes/{id}', [\App\Http\Controllers\Crm\CrmServiceRequestController::class, 'update'])->name('service-requests.update');
    Route::post('/solicitacoes/{id}/comments', [\App\Http\Controllers\Crm\CrmServiceRequestController::class, 'addComment'])->name('service-requests.comment');

    // Relatórios
    Route::get('/relatorios', [CrmReportsController::class, 'index'])->name('reports');

    // ── Processos Administrativos ──
    Route::prefix('processos-admin')->name('admin-processes.')->group(function () {
        Route::get('/',                      [\App\Http\Controllers\Crm\CrmAdminProcessController::class, 'index'])->name('index');
        Route::get('/criar',                 [\App\Http\Controllers\Crm\CrmAdminProcessController::class, 'create'])->name('create');
        Route::post('/',                     [\App\Http\Controllers\Crm\CrmAdminProcessController::class, 'store'])->name('store');
        Route::get('/api/template/{tipo}',   [\App\Http\Controllers\Crm\CrmAdminProcessController::class, 'getTemplate'])->name('template');
        Route::post('/api/checklist-ia',     [\App\Http\Controllers\Crm\CrmAdminProcessController::class, 'checklistIa'])->name('checklist-ia');
        Route::get('/{id}',                  [\App\Http\Controllers\Crm\CrmAdminProcessController::class, 'show'])->name('show');
        Route::get('/{id}/editar',           [\App\Http\Controllers\Crm\CrmAdminProcessController::class, 'edit'])->name('edit');
        Route::put('/{id}',                  [\App\Http\Controllers\Crm\CrmAdminProcessController::class, 'update'])->name('update');
        Route::post('/{id}/status',          [\App\Http\Controllers\Crm\CrmAdminProcessController::class, 'updateStatus'])->name('update-status');
        Route::post('/{id}/ato',             [\App\Http\Controllers\Crm\CrmAdminProcessController::class, 'storeAto'])->name('store-ato');
        Route::post('/{id}/tramitar',        [\App\Http\Controllers\Crm\CrmAdminProcessController::class, 'tramitar'])->name('tramitar');
        Route::post('/{id}/etapas/{stepId}', [\App\Http\Controllers\Crm\CrmAdminProcessController::class, 'updateStep'])->name('update-step');
        Route::post('/{id}/checklist/import-docx', [\App\Http\Controllers\Crm\CrmAdminProcessController::class, 'importChecklistDocx'])->name('import-checklist-docx');
        Route::post('/{id}/checklist',             [\App\Http\Controllers\Crm\CrmAdminProcessController::class, 'storeChecklistItem'])->name('store-checklist-item');
        Route::post('/{id}/checklist/{itemId}',    [\App\Http\Controllers\Crm\CrmAdminProcessController::class, 'updateChecklist'])->name('update-checklist');
    });

    // ── Pulso do Cliente ──
    require __DIR__ . '/_crm_pulso_routes.php';
});
