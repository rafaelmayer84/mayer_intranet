<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NexoTemplateController;

Route::prefix('nexo/templates')->middleware('auth')->name('nexo.templates.')->group(function () {
    Route::get('/', [NexoTemplateController::class, 'listar'])->name('listar');
    Route::post('/enviar', [NexoTemplateController::class, 'enviar'])->name('enviar');
    Route::post('/nova-conversa', [NexoTemplateController::class, 'iniciarConversa'])->name('nova-conversa');
});
