<?php

use App\Http\Controllers\Admin\SystemEventController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin/ocorrencias')->group(function () {
    Route::get('/', [SystemEventController::class, 'index'])->name('admin.ocorrencias');
    Route::get('/{systemEvent}', [SystemEventController::class, 'show'])->name('admin.ocorrencias.show');
});
