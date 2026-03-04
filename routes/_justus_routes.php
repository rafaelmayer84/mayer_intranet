<?php

use App\Http\Controllers\JustusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','modulo:operacional.justus'])->prefix('justus')->name('justus.')->group(function () {

    Route::get('/', [JustusController::class, 'index'])->name('index');
    Route::get('/app', [JustusController::class, 'app'])->name('app');
    Route::post('/conversations', [JustusController::class, 'createConversation'])->name('conversations.create');
    Route::post('/{conversation}/message', [JustusController::class, 'sendMessage'])->name('message.send');
    Route::post('/{conversation}/upload', [JustusController::class, 'upload'])->name('upload');
    Route::get('/{conversation}/download/{attachment}', [JustusController::class, 'download'])->name('download');
    Route::post('/{conversation}/profile', [JustusController::class, 'updateProfile'])->name('profile.update');
    Route::post('/{conversation}/approve', [JustusController::class, 'approve'])->name('approve');
    Route::get('/{conversation}/attachment-status/{attachment}', [JustusController::class, 'checkAttachmentStatus'])->name('attachment.status');
    Route::get('/{conversation}/jurisprudencia-insights', [JustusController::class, 'jurisprudenciaInsights'])->name('jurisprudencia.insights');
    Route::delete('/{conversation}', [JustusController::class, 'destroyConversation'])->name('conversations.destroy');

    Route::get('/{conversation}/messages/{message}/document', [JustusController::class, 'downloadDocument'])->name('message.document');
    Route::post('/{conversation}/messages/{message}/feedback', [JustusController::class, 'messageFeedback'])->name('message.feedback');

    // Admin routes
    Route::get('/admin/config', [JustusController::class, 'adminConfig'])->name('admin.config');
    Route::put('/admin/guides/{guide}', [JustusController::class, 'adminUpdateGuide'])->name('admin.guides.update');
    Route::get('/prompt-templates', [JustusController::class, 'promptTemplates'])->name('prompt.templates');
    Route::get('/admin/audit', [JustusController::class, 'adminAudit'])->name('admin.audit');
    Route::get('/admin/audit/{conversation}', [JustusController::class, 'adminAuditConversation'])->name('admin.audit.conversation');
    Route::put('/admin/templates/{template}', [JustusController::class, 'adminUpdateTemplate'])->name('admin.template.update');
    Route::get('/admin/feedback', [JustusController::class, 'adminFeedbackReport'])->name('admin.feedback');

});
