@extends('layouts.app')

@section('title', 'Dashboard - Intranet Mayer')
@section('header', 'Dashboard Principal - ' . $ano)

@section('content')
<div class="space-y-6">
    <!-- KPIs Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Faturamento Total</p>
                    <p class="text-2xl font-bold text-green-600">R$ {{ number_format($kpis['financeiro']['faturamento_total'], 2, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="mt-2 flex gap-4 text-xs">
                <span class="text-blue-600">PF: R$ {{ number_format($kpis['financeiro']['faturamento_pf'], 2, ',', '.') }}</span>
                <span class="text-purple-600">PJ: R$ {{ number_format($kpis['financeiro']['faturamento_pj'], 2, ',', '.') }}</span>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Processos Ativos</p>
                    <p class="text-2xl font-bold text-blue-600">{{ number_format($kpis['processos']['ativos']) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-500">Taxa de Resolução: {{ $kpis['processos']['taxa_resolucao'] }}%</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Horas Trabalhadas</p>
                    <p class="text-2xl font-bold text-purple-600">{{ number_format($kpis['horas']['total_horas'], 0, ',', '.') }}h</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-500">Valor: R$ {{ number_format($kpis['horas']['valor_total'], 2, ',', '.') }}</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total de Clientes</p>
                    <p class="text-2xl font-bold text-orange-600">{{ number_format($kpis['clientes']['total_clientes']) }}</p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-500">Média: {{ $kpis['clientes']['processos_por_cliente'] }} processos/cliente</p>
        </div>
    </div>
    
    <!-- Segunda linha -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Processos por Área -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-4">Processos por Área</h3>
            <div class="space-y-3">
                @forelse($kpis['processos']['por_area'] as $area)
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">{{ $area['tipo_acao'] ?? 'Não definido' }}</span>
                    <span class="font-semibold">{{ $area['total'] }}</span>
                </div>
                @empty
                <p class="text-gray-500 text-sm">Nenhum dado disponível</p>
                @endforelse
            </div>
        </div>
        
        <!-- Ranking de Advogados -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-4">Ranking de Performance</h3>
            <div class="space-y-3">
                @forelse(array_slice($ranking, 0, 5) as $index => $adv)
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold {{ $index === 0 ? 'bg-yellow-400 text-yellow-900' : ($index === 1 ? 'bg-gray-300 text-gray-700' : ($index === 2 ? 'bg-orange-300 text-orange-900' : 'bg-gray-100 text-gray-600')) }}">
                        {{ $index + 1 }}
                    </span>
                    <div class="flex-1">
                        <p class="text-sm font-medium">{{ $adv['nome'] }}</p>
                        <p class="text-xs text-gray-500">R$ {{ number_format($adv['faturamento'], 2, ',', '.') }}</p>
                    </div>
                    <div class="text-right">
                        <span class="text-lg font-bold {{ $adv['score'] >= 70 ? 'text-green-600' : ($adv['score'] >= 40 ? 'text-yellow-600' : 'text-red-600') }}">{{ $adv['score'] }}</span>
                        <p class="text-xs text-gray-400">pts</p>
                    </div>
                </div>
                @empty
                <p class="text-gray-500 text-sm">Nenhum advogado encontrado</p>
                @endforelse
            </div>
        </div>
    </div>
    
    <!-- Atividades -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-4">Atividades</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Pendentes</span>
                    <span class="text-xl font-bold text-yellow-600">{{ number_format($kpis['atividades']['pendentes']) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Concluídas</span>
                    <span class="text-xl font-bold text-green-600">{{ number_format($kpis['atividades']['concluidas']) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-600">Cumprimento de Prazos</span>
                    <span class="text-xl font-bold text-blue-600">{{ $kpis['atividades']['taxa_cumprimento_prazos'] }}%</span>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-4">Inadimplência</h3>
            <p class="text-3xl font-bold text-red-600">R$ {{ number_format($kpis['financeiro']['inadimplencia'], 2, ',', '.') }}</p>
            <p class="text-sm text-gray-500 mt-2">Contas vencidas não recebidas</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-lg font-semibold mb-4">Ticket Médio</h3>
            <p class="text-3xl font-bold text-blue-600">R$ {{ number_format($kpis['financeiro']['ticket_medio'], 2, ',', '.') }}</p>
            <p class="text-sm text-gray-500 mt-2">Por cliente</p>
        </div>
    </div>
</div>
@endsection
