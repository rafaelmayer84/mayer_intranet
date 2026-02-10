@extends('layouts.app')

@section('title', 'Análise por Área - Intranet Mayer')
@section('header', 'Análise por Área - ' . $ano)

@section('content')
<div class="space-y-6">
    <!-- Resumo -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <p class="text-sm text-gray-500">Total de Áreas</p>
            <p class="text-2xl font-bold text-blue-600">{{ count($kpis['por_area']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6">
            <p class="text-sm text-gray-500">Processos Ativos</p>
            <p class="text-2xl font-bold text-green-600">{{ number_format($kpis['ativos']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6">
            <p class="text-sm text-gray-500">Taxa de Resolução</p>
            <p class="text-2xl font-bold text-purple-600">{{ number_format($kpis['taxa_resolucao'], 1) }}%</p>
        </div>
    </div>
    
    <!-- Gráfico de Barras Visual -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6">Distribuição por Tipo de Ação</h3>
        
        @php 
            $maxTotal = count($kpis['por_area']) > 0 ? max(array_column($kpis['por_area'], 'total')) : 1;
            $colors = ['bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-yellow-500', 'bg-red-500', 'bg-indigo-500', 'bg-pink-500', 'bg-teal-500', 'bg-orange-500', 'bg-cyan-500'];
        @endphp
        
        <div class="space-y-4">
            @forelse($kpis['por_area'] as $index => $area)
            <div class="flex items-center">
                <div class="w-48 text-sm text-gray-600 truncate" title="{{ $area['tipo_acao'] ?? 'Não informado' }}">
                    {{ $area['tipo_acao'] ?? 'Não informado' }}
                </div>
                <div class="flex-1 mx-4">
                    <div class="w-full bg-gray-200 rounded-full h-6">
                        <div class="{{ $colors[$index % count($colors)] }} h-6 rounded-full flex items-center justify-end pr-2" 
                             style="width: {{ ($area['total'] / $maxTotal) * 100 }}%">
                            <span class="text-white text-xs font-medium">{{ number_format($area['total']) }}</span>
                        </div>
                    </div>
                </div>
                <div class="w-16 text-right text-sm text-gray-500">
                    @php $totalProcessos = array_sum(array_column($kpis['por_area'], 'total')); @endphp
                    {{ $totalProcessos > 0 ? number_format($area['total'] / $totalProcessos * 100, 1) : 0 }}%
                </div>
            </div>
            @empty
            <p class="text-center text-gray-500">Nenhuma área encontrada</p>
            @endforelse
        </div>
    </div>
    
    <!-- Tabela Detalhada -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Detalhamento por Área</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo de Ação</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantidade</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Participação</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($kpis['por_area'] as $index => $area)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $index + 1 }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $area['tipo_acao'] ?? 'Não informado' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900">{{ number_format($area['total']) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-600">
                            @php $totalProcessos = array_sum(array_column($kpis['por_area'], 'total')); @endphp
                            {{ $totalProcessos > 0 ? number_format($area['total'] / $totalProcessos * 100, 1) : 0 }}%
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">Nenhuma área encontrada</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
