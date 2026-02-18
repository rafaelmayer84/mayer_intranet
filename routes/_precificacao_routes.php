<?php

use App\Http\Controllers\PrecificacaoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas do Módulo de Precificação Inteligente
|--------------------------------------------------------------------------
| Incluir em routes/web.php dentro do grupo auth:
| require __DIR__ . '/_precificacao_routes.php';
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'user.active', 'modulo:operacional.precificacao,visualizar'])->prefix('precificacao')->group(function () {
    // Tela principal
    Route::get('/', [PrecificacaoController::class, 'index'])->name('precificacao.index');

    // APIs de busca/carregamento
    Route::get('/buscar', [PrecificacaoController::class, 'buscar'])->name('precificacao.buscar');
    Route::get('/lead/{id}', [PrecificacaoController::class, 'carregarLead'])->name('precificacao.lead')->whereNumber('id');
    Route::get('/cliente/{id}', [PrecificacaoController::class, 'carregarCliente'])->name('precificacao.cliente')->whereNumber('id');

    // Gerar propostas via IA
    Route::post('/gerar', [PrecificacaoController::class, 'gerar'])->name('precificacao.gerar');

    // Escolher proposta
    Route::post('/{id}/escolher', [PrecificacaoController::class, 'escolher'])->name('precificacao.escolher')->whereNumber('id');

    // Gerar proposta persuasiva para cliente (IA)
    Route::post('/{id}/gerar-proposta-cliente', [PrecificacaoController::class, 'gerarPropostaCliente'])->name('precificacao.gerar-proposta-cliente')->whereNumber('id');

    // Imprimir proposta para cliente (HTML → PDF via browser)
    Route::get('/{id}/proposta-print', [PrecificacaoController::class, 'imprimirProposta'])->name('precificacao.proposta.print')->whereNumber('id');

    // Excluir proposta (admin only)
    Route::delete('/{id}/excluir', [PrecificacaoController::class, 'excluir'])->name('precificacao.excluir')->whereNumber('id');

    // Ver proposta individual
    Route::get('/{id}', [PrecificacaoController::class, 'show'])->name('precificacao.show')->whereNumber('id');

    // Histórico
    Route::get('/historico/lista', [PrecificacaoController::class, 'historico'])->name('precificacao.historico');

    // Calibração (admin only)
    Route::get('/calibracao/painel', [PrecificacaoController::class, 'calibracao'])->name('precificacao.calibracao');
    Route::post('/calibracao/salvar', [PrecificacaoController::class, 'salvarCalibracao'])->name('precificacao.calibracao.salvar');
});
