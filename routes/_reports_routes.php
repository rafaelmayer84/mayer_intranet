<?php

use App\Http\Controllers\Reports\ReportsHubController;
use App\Http\Controllers\Reports\ReportFinanceiroController;
use App\Http\Controllers\Reports\ReportProcessosController;
use App\Http\Controllers\Reports\ReportCrmController;
use App\Http\Controllers\Reports\ReportProdutividadeController;
use App\Http\Controllers\Reports\ReportJustusController;
use App\Http\Controllers\Reports\ReportNexoController;
use App\Http\Controllers\Reports\ReportGdpController;
use App\Http\Controllers\Reports\ReportSistemaController;
use App\Http\Controllers\Reports\ReportSisrhController;
use App\Http\Controllers\Reports\ReportLeadsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('relatorios')->name('relatorios.')->group(function () {

    // Hub
    Route::get('/', [ReportsHubController::class, 'index'])->name('index');
    Route::get('/export/{domain}/{report}', [ReportsHubController::class, 'export'])->name('export');

    // Financeiro (6)
    Route::prefix('financeiro')->name('financeiro.')->group(function () {
        Route::get('/dre', [ReportFinanceiroController::class, 'dre'])->name('dre');
        Route::get('/receitas', [ReportFinanceiroController::class, 'receitas'])->name('receitas');
        Route::get('/despesas', [ReportFinanceiroController::class, 'despesas'])->name('despesas');
        Route::get('/contas-receber', [ReportFinanceiroController::class, 'contasReceber'])->name('contas-receber');
        Route::get('/fluxo-caixa', [ReportFinanceiroController::class, 'fluxoCaixa'])->name('fluxo-caixa');
        Route::get('/receita-advogado', [ReportFinanceiroController::class, 'receitaAdvogado'])->name('receita-advogado');
    });

    // Processos (5)
    Route::prefix('processos')->name('processos.')->group(function () {
        Route::get('/carteira', [ReportProcessosController::class, 'carteira'])->name('carteira');
        Route::get('/movimentacoes', [ReportProcessosController::class, 'movimentacoes'])->name('movimentacoes');
        Route::get('/parados', [ReportProcessosController::class, 'parados'])->name('parados');
        Route::get('/prazos-sla', [ReportProcessosController::class, 'prazosSla'])->name('prazos-sla');
        Route::get('/contratos', [ReportProcessosController::class, 'contratos'])->name('contratos');
    });

    // CRM (4)
    Route::prefix('crm')->name('crm.')->group(function () {
        Route::get('/base-clientes', [ReportCrmController::class, 'baseClientes'])->name('base-clientes');
        Route::get('/pipeline', [ReportCrmController::class, 'pipeline'])->name('pipeline');
        Route::get('/health-segmentacao', [ReportCrmController::class, 'healthSegmentacao'])->name('health-segmentacao');
        Route::get('/atividades', [ReportCrmController::class, 'atividades'])->name('atividades');
    });

    // Produtividade (3)
    Route::prefix('produtividade')->name('produtividade.')->group(function () {
        Route::get('/horas', [ReportProdutividadeController::class, 'horas'])->name('horas');
        Route::get('/atividades', [ReportProdutividadeController::class, 'atividades'])->name('atividades');
        Route::get('/receita-hora', [ReportProdutividadeController::class, 'receitaHora'])->name('receita-hora');
    });

    // Justus (3)
    Route::prefix('justus')->name('justus.')->group(function () {
        Route::get('/acervo', [ReportJustusController::class, 'acervo'])->name('acervo');
        Route::get('/captura', [ReportJustusController::class, 'captura'])->name('captura');
        Route::get('/distribuicao', [ReportJustusController::class, 'distribuicao'])->name('distribuicao');
    });

    // NEXO (4)
    Route::prefix('nexo')->name('nexo.')->group(function () {
        Route::get('/conversas', [ReportNexoController::class, 'conversas'])->name('conversas');
        Route::get('/tickets', [ReportNexoController::class, 'tickets'])->name('tickets');
        Route::get('/qa', [ReportNexoController::class, 'qa'])->name('qa');
        Route::get('/performance-atendentes', [ReportNexoController::class, 'performanceAtendentes'])->name('performance-atendentes');
    });

    // GDP (3)
    Route::prefix('gdp')->name('gdp.')->group(function () {
        Route::get('/performance', [ReportGdpController::class, 'performance'])->name('performance');
        Route::get('/penalizacoes', [ReportGdpController::class, 'penalizacoes'])->name('penalizacoes');
        Route::get('/avaliacoes-180', [ReportGdpController::class, 'avaliacoes180'])->name('avaliacoes-180');
    });

    // Sistema (3)
    Route::prefix('sistema')->name('sistema.')->group(function () {
        Route::get('/sync', [ReportSistemaController::class, 'sync'])->name('sync');
        Route::get('/eventos', [ReportSistemaController::class, 'eventos'])->name('eventos');
        Route::get('/auditoria', [ReportSistemaController::class, 'auditoria'])->name('auditoria');
    });

    // SISRH (2)
    Route::prefix('sisrh')->name('sisrh.')->group(function () {
        Route::get('/folha', [ReportSisrhController::class, 'folha'])->name('folha');
        Route::get('/custos', [ReportSisrhController::class, 'custos'])->name('custos');
    });

    // Leads + BSC (3)
    Route::prefix('leads')->name('leads.')->group(function () {
        Route::get('/funil', [ReportLeadsController::class, 'funil'])->name('funil');
        Route::get('/marketing', [ReportLeadsController::class, 'marketing'])->name('marketing');
        Route::get('/bsc-insights', [ReportLeadsController::class, 'bscInsights'])->name('bsc-insights');
    });
});
