@extends('layouts.app')

@section('title', 'Minha Performance - Intranet Mayer')
@section('header', 'Minha Performance - ' . $ano)

@section('content')
<div class="space-y-6">
    <!-- Info do Advogado -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center">
            <div class="w-16 h-16 bg-brand rounded-full flex items-center justify-center text-white text-2xl font-bold">
                {{ substr($advogado->nome, 0, 1) }}
            </div>
            <div class="ml-4">
                <h2 class="text-xl font-bold text-gray-800">{{ $advogado->nome }}</h2>
                <p class="text-gray-500">{{ $advogado->email }}</p>
            </div>
        </div>
    </div>
    
    <!-- KPIs Principais -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Faturamento -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Faturamento</p>
                    <p class="text-2xl font-bold text-green-600">R$ {{ number_format($kpis['faturamento'] ?? 0, 2, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Processos Ativos -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Processos Ativos</p>
                    <p class="text-2xl font-bold text-blue-600">{{ number_format($kpis['processos_ativos'] ?? 0) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Horas Trabalhadas -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Horas Trabalhadas</p>
                    <p class="text-2xl font-bold text-purple-600">{{ number_format($kpis['horas_trabalhadas'] ?? 0, 1, ',', '.') }}h</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Atividades Concluídas -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Atividades Concluídas</p>
                    <p class="text-2xl font-bold text-yellow-600">{{ number_format($kpis['atividades_concluidas'] ?? 0) }}</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Score BSC -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Score BSC</h3>
            <div class="flex items-center justify-center">
                <div class="relative w-48 h-48">
                    <svg class="w-48 h-48 transform -rotate-90">
                        <circle cx="96" cy="96" r="80" stroke="#e5e7eb" stroke-width="16" fill="none"></circle>
                        <circle cx="96" cy="96" r="80" stroke="#10B981" stroke-width="16" fill="none"
                            stroke-dasharray="{{ ($kpis['score_bsc'] ?? 0) * 5.02 }} 502"
                            stroke-linecap="round"></circle>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-4xl font-bold text-gray-800">{{ number_format($kpis['score_bsc'] ?? 0, 0) }}</span>
                    </div>
                </div>
            </div>
            <p class="text-center text-gray-500 mt-4">Pontuação Total</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Detalhamento BSC</h3>
            <div class="space-y-4">
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-500">Financeiro</span>
                        <span class="text-gray-800 font-medium">{{ number_format($kpis['bsc_financeiro'] ?? 0, 0) }} pts</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-green-500 h-2 rounded-full" style="width: {{ min(($kpis['bsc_financeiro'] ?? 0), 100) }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-500">Processos</span>
                        <span class="text-gray-800 font-medium">{{ number_format($kpis['bsc_processos'] ?? 0, 0) }} pts</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full" style="width: {{ min(($kpis['bsc_processos'] ?? 0), 100) }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-500">Produtividade</span>
                        <span class="text-gray-800 font-medium">{{ number_format($kpis['bsc_produtividade'] ?? 0, 0) }} pts</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-purple-500 h-2 rounded-full" style="width: {{ min(($kpis['bsc_produtividade'] ?? 0), 100) }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-500">Qualidade</span>
                        <span class="text-gray-800 font-medium">{{ number_format($kpis['bsc_qualidade'] ?? 0, 0) }} pts</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-yellow-500 h-2 rounded-full" style="width: {{ min(($kpis['bsc_qualidade'] ?? 0), 100) }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Metas -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Progresso das Metas</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <p class="text-gray-500 text-sm mb-2">Meta de Faturamento</p>
                <div class="flex items-center">
                    <div class="flex-1 bg-gray-200 rounded-full h-3">
                        <div class="bg-green-500 h-3 rounded-full" style="width: {{ min(($kpis['progresso_faturamento'] ?? 0), 100) }}%"></div>
                    </div>
                    <span class="ml-3 text-gray-800 font-medium">{{ number_format($kpis['progresso_faturamento'] ?? 0, 0) }}%</span>
                </div>
            </div>
            <div>
                <p class="text-gray-500 text-sm mb-2">Meta de Horas</p>
                <div class="flex items-center">
                    <div class="flex-1 bg-gray-200 rounded-full h-3">
                        <div class="bg-purple-500 h-3 rounded-full" style="width: {{ min(($kpis['progresso_horas'] ?? 0), 100) }}%"></div>
                    </div>
                    <span class="ml-3 text-gray-800 font-medium">{{ number_format($kpis['progresso_horas'] ?? 0, 0) }}%</span>
                </div>
            </div>
            <div>
                <p class="text-gray-500 text-sm mb-2">Meta de Processos</p>
                <div class="flex items-center">
                    <div class="flex-1 bg-gray-200 rounded-full h-3">
                        <div class="bg-blue-500 h-3 rounded-full" style="width: {{ min(($kpis['progresso_processos'] ?? 0), 100) }}%"></div>
                    </div>
                    <span class="ml-3 text-gray-800 font-medium">{{ number_format($kpis['progresso_processos'] ?? 0, 0) }}%</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
