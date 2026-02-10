@extends('layouts.app')

@section('title', 'Financeiro - Intranet Mayer')
@section('header', 'Análise Financeira - ' . $ano)

@section('content')
<div class="space-y-6">
    <!-- KPIs Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Faturamento Total</p>
                    <p class="text-2xl font-bold text-green-600">R$ {{ number_format($kpis['faturamento_total'], 2, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Inadimplência</p>
                    <p class="text-2xl font-bold text-red-600">R$ {{ number_format($kpis['inadimplencia'], 2, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Ticket Médio</p>
                    <p class="text-2xl font-bold text-blue-600">R$ {{ number_format($kpis['ticket_medio'], 2, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total de Clientes</p>
                    <p class="text-2xl font-bold text-purple-600">{{ number_format($kpis['total_clientes']) }}</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Resumo Financeiro -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Resumo Financeiro</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="text-sm font-medium text-gray-500 mb-3">Receitas</h4>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Honorários Recebidos</span>
                        <span class="font-medium text-green-600">R$ {{ number_format($kpis['faturamento_total'], 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Pessoa Física</span>
                        <span class="font-medium">R$ {{ number_format($kpis['faturamento_pf'], 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Pessoa Jurídica</span>
                        <span class="font-medium">R$ {{ number_format($kpis['faturamento_pj'], 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
            <div>
                <h4 class="text-sm font-medium text-gray-500 mb-3">Indicadores</h4>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Taxa de Inadimplência</span>
                        <span class="font-medium {{ ($kpis['faturamento_total'] > 0 ? ($kpis['inadimplencia'] / $kpis['faturamento_total'] * 100) : 0) > 10 ? 'text-red-600' : 'text-green-600' }}">
                            {{ $kpis['faturamento_total'] > 0 ? number_format($kpis['inadimplencia'] / $kpis['faturamento_total'] * 100, 1) : 0 }}%
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Receita por Cliente</span>
                        <span class="font-medium">R$ {{ number_format($kpis['ticket_medio'], 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
