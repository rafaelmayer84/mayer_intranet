<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('api/notifications')->group(function () {
    Route::get('/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
    Route::post('/mark-read', [NotificationController::class, 'markRead'])->name('notifications.markRead');
});
