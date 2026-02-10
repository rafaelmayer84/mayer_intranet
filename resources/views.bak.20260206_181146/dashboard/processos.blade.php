@extends('layouts.app')

@section('title', 'Processos - Intranet Mayer')
@section('header', 'Análise de Processos - ' . $ano)

@section('content')
<div class="space-y-6">
    <!-- KPIs Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Processos Ativos</p>
                    <p class="text-2xl font-bold text-blue-600">{{ number_format($kpis['ativos']) }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Processos Concluídos</p>
                    <p class="text-2xl font-bold text-green-600">{{ number_format($kpis['concluidos']) }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Taxa de Resolução</p>
                    <p class="text-2xl font-bold text-purple-600">{{ number_format($kpis['taxa_resolucao'], 1) }}%</p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Processos por Área -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Processos por Tipo de Ação</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo de Ação</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantidade</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Percentual</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @php $totalProcessos = array_sum(array_column($kpis['por_area'], 'total')); @endphp
                    @forelse($kpis['por_area'] as $area)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $area['tipo_acao'] ?? 'Não informado' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">{{ number_format($area['total']) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <div class="flex items-center justify-end">
                                <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $totalProcessos > 0 ? ($area['total'] / $totalProcessos * 100) : 0 }}%"></div>
                                </div>
                                <span class="text-gray-600">{{ $totalProcessos > 0 ? number_format($area['total'] / $totalProcessos * 100, 1) : 0 }}%</span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-6 py-4 text-center text-gray-500">Nenhum processo encontrado</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Processos por Advogado -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Processos por Advogado</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Advogado</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Processos</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Percentual</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @php $totalPorAdv = array_sum(array_column($kpis['por_advogado'], 'total')); @endphp
                    @forelse($kpis['por_advogado'] as $adv)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                    {{ substr($adv['advogado_responsavel'] ?? 'N', 0, 1) }}
                                </div>
                                <span class="ml-3 text-sm text-gray-900">{{ $adv['advogado_responsavel'] ?? 'Não informado' }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">{{ number_format($adv['total']) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <div class="flex items-center justify-end">
                                <div class="w-24 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="bg-green-500 h-2 rounded-full" style="width: {{ $totalPorAdv > 0 ? ($adv['total'] / $totalPorAdv * 100) : 0 }}%"></div>
                                </div>
                                <span class="text-gray-600">{{ $totalPorAdv > 0 ? number_format($adv['total'] / $totalPorAdv * 100, 1) : 0 }}%</span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-6 py-4 text-center text-gray-500">Nenhum advogado encontrado</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
