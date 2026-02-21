<?php

/**
 * Rotas do módulo de Gestão de Usuários
 * 
 * Adicionar ao final do arquivo routes/web.php:
 * 
 * require __DIR__ . '/_usuarios_routes.php';
 */

use App\Http\Controllers\Admin\UsuariosController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth','admin'])->prefix('admin')->name('admin.')->group(function () {
    
    // Listagem e CRUD de usuários
    Route::get('/usuarios', [UsuariosController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/create', [UsuariosController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios', [UsuariosController::class, 'store'])->name('usuarios.store');
    Route::get('/usuarios/{usuario}', [UsuariosController::class, 'show'])->name('usuarios.show');
    Route::get('/usuarios/{usuario}/edit', [UsuariosController::class, 'edit'])->name('usuarios.edit');
    Route::put('/usuarios/{usuario}', [UsuariosController::class, 'update'])->name('usuarios.update');
    
    // Ações especiais
    Route::patch('/usuarios/{usuario}/toggle-status', [UsuariosController::class, 'toggleStatus'])->name('usuarios.toggle-status');
    Route::delete('/usuarios/{usuario}', [UsuariosController::class, 'destroy'])->name('usuarios.destroy');
    Route::post('/usuarios/{usuario}/resetar-senha', [UsuariosController::class, 'resetarSenha'])->name('usuarios.resetar-senha');
    Route::post('/usuarios/{usuario}/permissoes', [UsuariosController::class, 'salvarPermissoes'])->name('usuarios.salvar-permissoes');
    Route::post('/usuarios/{usuario}/permissoes-padrao', [UsuariosController::class, 'aplicarPermissoesPadrao'])->name('usuarios.aplicar-permissoes-padrao');
    
    // Sincronização com DataJuri
    Route::get('/usuarios/sync/datajuri', [UsuariosController::class, 'sincronizacao'])->name('usuarios.sincronizacao');
    Route::post('/usuarios/sync/datajuri/ativar', [UsuariosController::class, 'ativarDataJuri'])->name('usuarios.ativar-datajuri');
    

    // Audit Log (admin only)
    Route::get('/audit-log', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-log');
    Route::get('/audit-log/data', [\App\Http\Controllers\Admin\AuditLogController::class, 'data'])->name('audit-log.data');

});
