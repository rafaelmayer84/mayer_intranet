<?php

use App\Http\Controllers\Crm\CrmPulsoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CRM Pulso do Cliente Routes
|--------------------------------------------------------------------------
| Incluído dentro do grupo CRM em _crm_routes.php
*/

// Dashboard gerencial
Route::get('/pulso', [CrmPulsoController::class, 'index'])->name('pulso');

// Alertas
Route::get('/pulso/alertas', [CrmPulsoController::class, 'alertas'])->name('pulso.alertas');
Route::post('/pulso/alertas/{id}/resolver', [CrmPulsoController::class, 'resolverAlerta'])->name('pulso.alertas.resolver');
Route::post('/pulso/alertas/{id}/visto', [CrmPulsoController::class, 'vistoAlerta'])->name('pulso.alertas.visto');

// Upload ligações
Route::get('/pulso/upload', [CrmPulsoController::class, 'uploadForm'])->name('pulso.upload');
Route::post('/pulso/upload', [CrmPulsoController::class, 'uploadProcess'])->name('pulso.upload.process');

// Config thresholds
Route::get('/pulso/config', [CrmPulsoController::class, 'configForm'])->name('pulso.config');
Route::post('/pulso/config', [CrmPulsoController::class, 'configSave'])->name('pulso.config.save');

// Account Pulso (JSON para aba Account 360)
Route::get('/accounts/{id}/pulso', [CrmPulsoController::class, 'accountPulso'])->name('accounts.pulso');
