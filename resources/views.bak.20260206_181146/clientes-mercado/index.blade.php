@extends('layouts.app')

@section('title', 'Clientes & Mercado')

@section('content')
<div class="space-y-6">
    {{-- Cabeçalho com filtros --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Clientes & Mercado</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Dashboard BSC - Perspectiva Clientes | Competência: {{ $dashboardData['competencia']['label'] }}
            </p>
        </div>
        
        {{-- Filtros de Competência --}}
        <form method="GET" action="{{ route('clientes-mercado') }}" class="flex items-center gap-2">
            <select name="mes" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500">
                @foreach($meses as $num => $nome)
                    <option value="{{ $num }}" {{ $mesSelecionado == $num ? 'selected' : '' }}>{{ $nome }}</option>
                @endforeach
            </select>
            <select name="ano" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500">
                @foreach($anosDisponiveis as $a)
                    <option value="{{ $a }}" {{ $anoSelecionado == $a ? 'selected' : '' }}>{{ $a }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium">
                Filtrar
            </button>
        </form>
    </div>

    {{-- KPIs Principais (4 cards) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($dashboardData['kpis_principais'] as $kpi)
            @include('dashboard.partials._kpi-card', [
                'id' => Str::slug($kpi['label']),
                'title' => $kpi['label'],
                'value' => $kpi['formato'] === 'moeda' 
                    ? 'R$ ' . number_format($kpi['valor'], 2, ',', '.') 
                    : number_format($kpi['valor'], 0, ',', '.'),
                'meta' => '',
                'percent' => 0,
                'icon' => $kpi['icon'],
                'accent' => $kpi['cor']
            ])
        @endforeach
    </div>

    {{-- KPIs Secundários (4 cards) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($dashboardData['kpis_secundarios'] as $kpi)
            <div class="rounded-2xl border-t-4 {{ $kpi['cor'] === 'orange' ? 'border-orange-500' : ($kpi['cor'] === 'blue' ? 'border-blue-500' : ($kpi['cor'] === 'purple' ? 'border-purple-500' : 'border-emerald-500')) }} bg-gradient-to-b from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 p-4 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ $kpi['label'] }}</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                            @if($kpi['formato'] === 'moeda')
                                R$ {{ number_format($kpi['valor'], 2, ',', '.') }}
                            @elseif($kpi['formato'] === 'percentual')
                                {{ number_format($kpi['valor'], 1, ',', '.') }}%
                            @else
                                {{ number_format($kpi['valor'], 0, ',', '.') }}
                            @endif
                        </p>
                        @if(isset($kpi['detalhe']))
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ $kpi['detalhe'] }}</p>
                        @endif
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-700">
                        <span>{{ $kpi['icon'] }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Gráficos --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Gráfico de Linha: Leads novos vs Convertidos (12 meses) --}}
        <div class="rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                📈 Evolução de Leads (12 meses)
            </h3>
            <div class="h-64">
                <canvas id="chartLeads12Meses"></canvas>
            </div>
        </div>

        {{-- Gráfico de Barras: Oportunidades por Estágio --}}
        <div class="rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                📊 Oportunidades - {{ $dashboardData['competencia']['label'] }}
            </h3>
            <div class="h-64">
                <canvas id="chartOportunidades"></canvas>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                <div>
                    <span class="inline-block w-3 h-3 rounded-full bg-emerald-500 mr-1"></span>
                    Ganhas: {{ $dashboardData['oportunidades_por_estagio']['ganhas']['qtd'] }}
                    <br><span class="text-gray-500">R$ {{ number_format($dashboardData['oportunidades_por_estagio']['ganhas']['valor'], 0, ',', '.') }}</span>
                </div>
                <div>
                    <span class="inline-block w-3 h-3 rounded-full bg-red-500 mr-1"></span>
                    Perdidas: {{ $dashboardData['oportunidades_por_estagio']['perdidas']['qtd'] }}
                    <br><span class="text-gray-500">R$ {{ number_format($dashboardData['oportunidades_por_estagio']['perdidas']['valor'], 0, ',', '.') }}</span>
                </div>
                <div>
                    <span class="inline-block w-3 h-3 rounded-full bg-purple-500 mr-1"></span>
                    Pipeline: {{ $dashboardData['oportunidades_por_estagio']['pipeline']['qtd'] }}
                    <br><span class="text-gray-500">R$ {{ number_format($dashboardData['oportunidades_por_estagio']['pipeline']['valor'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        {{-- Gráfico Donut: Origem dos Leads --}}
        <div class="rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                🎯 Origem dos Leads
            </h3>
            <div class="h-64">
                <canvas id="chartOrigemLeads"></canvas>
            </div>
        </div>

        {{-- Gráfico de Linha: Valor Ganho (12 meses) --}}
        <div class="rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                💰 Valor Ganho (12 meses)
            </h3>
            <div class="h-64">
                <canvas id="chartValorGanho"></canvas>
            </div>
        </div>
    </div>

    {{-- Top 10 Clientes --}}
    <div class="rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
            🏆 Top 10 Clientes por Processos Ativos
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">#</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Cliente</th>
                        <th class="text-left py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Tipo</th>
                        <th class="text-right py-3 px-4 font-semibold text-gray-600 dark:text-gray-400">Processos Ativos</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dashboardData['top_10_clientes'] as $i => $cliente)
                        <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="py-3 px-4 text-gray-500">{{ $i + 1 }}</td>
                            <td class="py-3 px-4 font-medium text-gray-900 dark:text-white">
                                {{ $cliente->nome }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ ($cliente->tipo ?? 'PF') === 'PJ' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' }}">
                                    {{ $cliente->tipo ?? 'PF' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-semibold text-gray-900 dark:text-white">
                                {{ $cliente->qtd_processos_ativos }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                Nenhum cliente com processos ativos encontrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Rodapé com totais e timestamp --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50 rounded-xl p-4">
        <div class="flex flex-wrap gap-4">
            <span>📊 Total Clientes: <strong>{{ number_format($totaisAcumulados['total_clientes'], 0, ',', '.') }}</strong></span>
            <span>👥 Total Leads: <strong>{{ number_format($totaisAcumulados['total_leads'], 0, ',', '.') }}</strong></span>
            <span>🎯 Total Oportunidades: <strong>{{ number_format($totaisAcumulados['total_oportunidades'], 0, ',', '.') }}</strong></span>
            <span>⚖️ Processos Ativos: <strong>{{ number_format($totaisAcumulados['processos_ativos'], 0, ',', '.') }}</strong></span>
        </div>
        <div class="mt-2 sm:mt-0">
            Atualizado em: {{ $dashboardData['gerado_em'] }}
        </div>
    </div>
</div>

{{-- Dados para os gráficos --}}
<script id="serie12MesesData" type="application/json">
    @json($dashboardData['serie_12_meses'])
</script>
<script id="oportunidadesEstagioData" type="application/json">
    @json($dashboardData['oportunidades_por_estagio'])
</script>
<script id="origemLeadsData" type="application/json">
    @json($dashboardData['origem_leads'])
</script>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.documentElement.classList.contains('dark');
    const textColor = isDark ? '#9ca3af' : '#6b7280';
    const gridColor = isDark ? '#374151' : '#e5e7eb';

    // Dados
    const serie12Meses = JSON.parse(document.getElementById('serie12MesesData').textContent);
    const opsEstagio = JSON.parse(document.getElementById('oportunidadesEstagioData').textContent);
    const origemLeads = JSON.parse(document.getElementById('origemLeadsData').textContent);

    // Gráfico 1: Leads 12 meses (linha)
    new Chart(document.getElementById('chartLeads12Meses'), {
        type: 'line',
        data: {
            labels: serie12Meses.map(d => d.label),
            datasets: [
                {
                    label: 'Leads Novos',
                    data: serie12Meses.map(d => d.leads_novos),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Convertidos',
                    data: serie12Meses.map(d => d.leads_convertidos),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { color: textColor }
                }
            },
            scales: {
                x: { ticks: { color: textColor }, grid: { color: gridColor } },
                y: { ticks: { color: textColor }, grid: { color: gridColor }, beginAtZero: true }
            }
        }
    });

    // Gráfico 2: Oportunidades por estágio (barras)
    new Chart(document.getElementById('chartOportunidades'), {
        type: 'bar',
        data: {
            labels: ['Ganhas', 'Perdidas', 'Pipeline'],
            datasets: [
                {
                    label: 'Quantidade',
                    data: [opsEstagio.ganhas.qtd, opsEstagio.perdidas.qtd, opsEstagio.pipeline.qtd],
                    backgroundColor: ['#10b981', '#ef4444', '#8b5cf6']
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { ticks: { color: textColor }, grid: { display: false } },
                y: { ticks: { color: textColor }, grid: { color: gridColor }, beginAtZero: true }
            }
        }
    });

    // Gráfico 3: Origem leads (donut)
    new Chart(document.getElementById('chartOrigemLeads'), {
        type: 'doughnut',
        data: {
            labels: origemLeads.map(o => o.origem),
            datasets: [{
                data: origemLeads.map(o => o.total),
                backgroundColor: origemLeads.map(o => o.cor)
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: { color: textColor, boxWidth: 12 }
                }
            }
        }
    });

    // Gráfico 4: Valor Ganho 12 meses (barras)
    new Chart(document.getElementById('chartValorGanho'), {
        type: 'bar',
        data: {
            labels: serie12Meses.map(d => d.label),
            datasets: [{
                label: 'Valor Ganho (R$)',
                data: serie12Meses.map(d => d.valor_ganho),
                backgroundColor: '#10b981'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { ticks: { color: textColor }, grid: { display: false } },
                y: { 
                    ticks: { 
                        color: textColor,
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR');
                        }
                    }, 
                    grid: { color: gridColor }, 
                    beginAtZero: true 
                }
            }
        }
    });
});
</script>
@endpush
