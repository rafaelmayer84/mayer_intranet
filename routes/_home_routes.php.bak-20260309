<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Home Dashboard Routes
|--------------------------------------------------------------------------
| Cockpit operacional - pagina principal apos login
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/home/buscar', [HomeController::class, 'buscar'])->name('home.buscar');
});
