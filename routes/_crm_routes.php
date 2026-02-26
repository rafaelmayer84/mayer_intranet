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

    // Dashboard (home CRM)
    Route::get('/', [CrmDashboardController::class, 'index'])->name('dashboard');

    // Leads (qualificados)
    Route::get('/leads', [CrmLeadsController::class, 'index'])->name('leads');
    Route::post('/leads/{id}/status', [CrmLeadsController::class, 'updateStatus'])->name('leads.status');
    Route::post('/leads/{id}/assign', [CrmLeadsController::class, 'assignOwner'])->name('leads.assign');

    // Carteira
    Route::get('/carteira', [CrmCarteiraController::class, 'index'])->name('carteira');

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

    // Account 360
    Route::get('/accounts/{id}', [CrmAccountController::class, 'show'])->name('accounts.show');
    Route::put('/accounts/{id}', [CrmAccountController::class, 'update'])->name('accounts.update');
    Route::post('/accounts/{id}/opportunities', [CrmAccountController::class, 'createOpportunity'])->name('accounts.create-opp');
    Route::post('/accounts/{id}/activities', [CrmAccountController::class, 'storeActivity'])->name('accounts.store-activity');
    Route::post('/accounts/{id}/activities/{activityId}/complete', [CrmAccountController::class, 'completeActivity'])->name('accounts.complete-activity');

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

    // Relatórios
    Route::get('/relatorios', [CrmReportsController::class, 'index'])->name('reports');
});
