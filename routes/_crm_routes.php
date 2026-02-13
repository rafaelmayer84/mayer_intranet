<?php

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
| Include this file in routes/web.php:
|   require __DIR__ . '/_crm_routes.php';
*/

Route::middleware(['auth'])->prefix('crm')->name('crm.')->group(function () {

    // Carteira
    Route::get('/carteira', [CrmCarteiraController::class, 'index'])->name('carteira');

    // Pipeline
    Route::get('/pipeline', [CrmPipelineController::class, 'index'])->name('pipeline');
    Route::post('/pipeline/{id}/move', [CrmPipelineController::class, 'moveStage'])->name('pipeline.move');
    Route::post('/pipeline/{id}/won', [CrmPipelineController::class, 'markWon'])->name('pipeline.won');
    Route::post('/pipeline/{id}/lost', [CrmPipelineController::class, 'markLost'])->name('pipeline.lost');

    // Account 360
    Route::get('/accounts/{id}', [CrmAccountController::class, 'show'])->name('accounts.show');
    Route::put('/accounts/{id}', [CrmAccountController::class, 'update'])->name('accounts.update');
    Route::post('/accounts/{id}/opportunities', [CrmAccountController::class, 'createOpportunity'])->name('accounts.create-opp');
    Route::post('/accounts/{id}/activities', [CrmAccountController::class, 'storeActivity'])->name('accounts.store-activity');

    // Opportunity 360
    Route::get('/oportunidades/{id}', [CrmOpportunityController::class, 'show'])->name('opportunities.show');
    Route::put('/oportunidades/{id}', [CrmOpportunityController::class, 'update'])->name('opportunities.update');
    Route::post('/oportunidades/{id}/activities', [CrmOpportunityController::class, 'storeActivity'])->name('opportunities.store-activity');
    Route::post('/oportunidades/{id}/activities/{activityId}/complete', [CrmOpportunityController::class, 'completeActivity'])->name('opportunities.complete-activity');

    // Relatórios
    Route::get('/relatorios', [CrmReportsController::class, 'index'])->name('reports');
});
