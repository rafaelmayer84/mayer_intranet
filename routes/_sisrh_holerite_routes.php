<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SisrhHoleriteController;

Route::get('/sisrh/contracheque', [SisrhHoleriteController::class, 'meuContracheque'])->name('sisrh.contracheque');
Route::get('/sisrh/contracheque/print', [SisrhHoleriteController::class, 'contrachequePrint'])->name('sisrh.contracheque.print');
Route::get('/sisrh/folha', [SisrhHoleriteController::class, 'folha'])->name('sisrh.folha');
Route::get('/sisrh/lancamentos', [SisrhHoleriteController::class, 'lancamentos'])->name('sisrh.lancamentos');
Route::post('/sisrh/lancamentos', [SisrhHoleriteController::class, 'salvarLancamento'])->name('sisrh.lancamento.salvar');
Route::delete('/sisrh/lancamentos/{id}', [SisrhHoleriteController::class, 'excluirLancamento'])->name('sisrh.lancamento.excluir');
Route::get('/sisrh/rubricas', [SisrhHoleriteController::class, 'rubricas'])->name('sisrh.rubricas');
Route::post('/sisrh/rubricas', [SisrhHoleriteController::class, 'salvarRubrica'])->name('sisrh.rubrica.salvar');
Route::put('/sisrh/rubricas/{id}', [SisrhHoleriteController::class, 'atualizarRubrica'])->name('sisrh.rubrica.atualizar');
