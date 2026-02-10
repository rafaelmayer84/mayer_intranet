@extends('layouts.app')

@section('title', 'Central de Leads')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    📊 Central de Leads
                </h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Gestão de leads captados via WhatsApp Bot
                </p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('leads.stats') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    API Stats
                </a>
            </div>
        </div>
    </div>

    {{-- Métricas --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {{-- Total de Leads --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border-t-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Leads</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ number_format($totalLeads, 0, ',', '.') }}</p>
                </div>
                <div class="bg-blue-500 bg-opacity-10 rounded-full p-3">
                    <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Leads Hoje --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border-t-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Leads Hoje</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ $leadsHoje }}</p>
                </div>
                <div class="bg-green-500 bg-opacity-10 rounded-full p-3">
                    <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Leads Esta Semana --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border-t-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Esta Semana</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ $leadsSemana }}</p>
                </div>
                <div class="bg-purple-500 bg-opacity-10 rounded-full p-3">
                    <svg class="w-8 h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Taxa de Conversão --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border-t-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Taxa de Conversão</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white mt-2">{{ $taxaConversao }}%</p>
                </div>
                <div class="bg-orange-500 bg-opacity-10 rounded-full p-3">
                    <svg class="w-8 h-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Leads com intenção "Sim"</p>
        </div>
    </div>

    {{-- Gráficos --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Distribuição por Área --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Distribuição por Área Jurídica</h3>
            <div style="height: 300px;">
                <canvas id="chartArea"></canvas>
            </div>
        </div>

        {{-- Top 10 Cidades --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Top 10 Cidades</h3>
            <div style="height: 300px;">
                <canvas id="chartCidade"></canvas>
            </div>
        </div>

        {{-- Timeline (30 dias) --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Evolução (Últimos 30 Dias)</h3>
            <div style="height: 250px;">
                <canvas id="chartTimeline"></canvas>
            </div>
        </div>

        {{-- Intenção de Contratar --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Intenção de Contratar</h3>
            <div style="height: 300px;">
                <canvas id="chartIntencao"></canvas>
            </div>
        </div>

        {{-- Palavras-Chave --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Palavras-Chave Mais Frequentes</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($topPalavras as $palavra => $count)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                        {{ $palavra }}
                        <span class="ml-1 px-1.5 py-0.5 text-xs bg-blue-200 dark:bg-blue-800 rounded-full">{{ $count }}</span>
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">🔍 Filtros</h3>
        <form method="GET" action="{{ route('leads.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                {{-- Área --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Área Jurídica</label>
                    <select name="area" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" onchange="this.form.submit()">
                        <option value="todos">Todas as áreas</option>
                        @foreach($areas as $area)
                            <option value="{{ $area }}" {{ $filtroArea == $area ? 'selected' : '' }}>{{ $area }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Cidade --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cidade</label>
                    <select name="cidade" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" onchange="this.form.submit()">
                        <option value="todos">Todas as cidades</option>
                        @foreach($cidades as $cidade)
                            <option value="{{ $cidade }}" {{ $filtroCidade == $cidade ? 'selected' : '' }}>{{ $cidade }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Período --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Período</label>
                    <select name="periodo" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" onchange="this.form.submit()">
                        <option value="todos" {{ $filtroPeriodo == 'todos' ? 'selected' : '' }}>Todos</option>
                        <option value="hoje" {{ $filtroPeriodo == 'hoje' ? 'selected' : '' }}>Hoje</option>
                        <option value="semana" {{ $filtroPeriodo == 'semana' ? 'selected' : '' }}>Esta Semana</option>
                        <option value="mes" {{ $filtroPeriodo == 'mes' ? 'selected' : '' }}>Este Mês</option>
                    </select>
                </div>

                {{-- Intenção --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Intenção</label>
                    <select name="intencao" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500" onchange="this.form.submit()">
                        <option value="todos">Todas</option>
                        <option value="sim" {{ $filtroIntencao == 'sim' ? 'selected' : '' }}>Sim</option>
                        <option value="não" {{ $filtroIntencao == 'não' ? 'selected' : '' }}>Não</option>
                        <option value="talvez" {{ $filtroIntencao == 'talvez' ? 'selected' : '' }}>Talvez</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Aplicar Filtros
                </button>
                <a href="{{ route('leads.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-300 dark:bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-400 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Limpar Filtros
                </a>
            </div>
        </form>
    </div>

    {{-- Tabela de Leads --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Últimos Leads</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Telefone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Área</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cidade</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Intenção</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($leads as $lead)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 {{ $lead->temErro() ? 'bg-red-50 dark:bg-red-900/20' : '' }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $lead->nome }}</div>
                                @if($lead->resumo_demanda)
                                    <div class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs">{{ Str::limit($lead->resumo_demanda, 50) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">{{ $lead->telefone }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">{{ $lead->area_interesse ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-300">{{ $lead->cidade ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{!! $lead->getIntencaoBadge() !!}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{!! $lead->getStatusBadge() !!}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $lead->data_entrada->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('leads.show', $lead) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">Ver Detalhes</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Nenhum lead encontrado</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const colors = {
        primary: '#3B82F6',
        secondary: '#8B5CF6',
        success: '#10B981',
        warning: '#F59E0B',
        danger: '#EF4444',
        info: '#06B6D4'
    };

    const gradientColors = [
        '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B',
        '#10B981', '#06B6D4', '#6366F1', '#EF4444'
    ];

    // Gráfico de Área
    @if($dadosArea->isNotEmpty())
    new Chart(document.getElementById('chartArea'), {
        type: 'doughnut',
        data: {
            labels: @json($dadosArea->pluck('area_interesse')),
            datasets: [{
                data: @json($dadosArea->pluck('total')),
                backgroundColor: gradientColors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 15, font: { size: 12 } }
                }
            }
        }
    });
    @endif

    // Gráfico de Cidade
    @if($dadosCidade->isNotEmpty())
    new Chart(document.getElementById('chartCidade'), {
        type: 'bar',
        data: {
            labels: @json($dadosCidade->pluck('cidade')),
            datasets: [{
                label: 'Leads',
                data: @json($dadosCidade->pluck('total')),
                backgroundColor: colors.primary,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
    @endif

    // Gráfico de Timeline
    @if($dadosTimeline->isNotEmpty())
    new Chart(document.getElementById('chartTimeline'), {
        type: 'line',
        data: {
            labels: @json($dadosTimeline->map(fn($d) => \Carbon\Carbon::parse($d->data)->format('d/m'))),
            datasets: [{
                label: 'Leads por Dia',
                data: @json($dadosTimeline->pluck('total')),
                borderColor: colors.primary,
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4,
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
    @endif

    // Gráfico de Intenção
    @if($dadosIntencao->isNotEmpty())
    new Chart(document.getElementById('chartIntencao'), {
        type: 'pie',
        data: {
            labels: @json($dadosIntencao->pluck('intencao_contratar')),
            datasets: [{
                data: @json($dadosIntencao->pluck('total')),
                backgroundColor: [colors.success, colors.danger, colors.warning],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 15, font: { size: 12 } }
                }
            }
        }
    });
    @endif
});
</script>
@endpush
@endsection
