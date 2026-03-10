<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SisrhAdvogadoController;

Route::get('/sisrh/advogados', [SisrhAdvogadoController::class, 'index'])->name('sisrh.advogados');
Route::post('/sisrh/advogados/ativar', [SisrhAdvogadoController::class, 'ativar'])->name('sisrh.advogado.ativar');
Route::put('/sisrh/advogados/{id}', [SisrhAdvogadoController::class, 'editar'])->name('sisrh.advogado.editar');
Route::post('/sisrh/advogados/{id}/desativar', [SisrhAdvogadoController::class, 'desativar'])->name('sisrh.advogado.desativar');
Route::post('/sisrh/advogados/{id}/reativar', [SisrhAdvogadoController::class, 'reativar'])->name('sisrh.advogado.reativar');

// ── Frequencia (SISRH v1.2) ──
Route::get('/sisrh/frequencia', [\App\Http\Controllers\SisrhFrequenciaController::class, 'index'])->name('sisrh.frequencia');
Route::post('/sisrh/frequencia/importar', [\App\Http\Controllers\SisrhFrequenciaController::class, 'importar'])->name('sisrh.frequencia.importar');

