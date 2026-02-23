<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SisrhDocumentoController;

Route::get('/sisrh/advogados/{userId}/documentos', [SisrhDocumentoController::class, 'index'])->name('sisrh.documentos');
Route::post('/sisrh/advogados/{userId}/documentos', [SisrhDocumentoController::class, 'upload'])->name('sisrh.documento.upload');
Route::get('/sisrh/documentos/{id}/download', [SisrhDocumentoController::class, 'download'])->name('sisrh.documento.download');
Route::get('/sisrh/documentos/{id}/visualizar', [SisrhDocumentoController::class, 'visualizar'])->name('sisrh.documento.visualizar');
Route::delete('/sisrh/documentos/{id}', [SisrhDocumentoController::class, 'excluir'])->name('sisrh.documento.excluir');
