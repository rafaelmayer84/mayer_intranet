<?php

/**
 * Rotas do módulo Meu Perfil
 */

use App\Http\Controllers\PerfilController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/meu-perfil', [PerfilController::class, 'index'])->name('perfil.index');
    Route::post('/meu-perfil/alterar-senha', [PerfilController::class, 'alterarSenha'])->name('perfil.alterar-senha');
});
