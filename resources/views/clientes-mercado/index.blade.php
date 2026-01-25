@extends('layouts.app')

@section('title', 'Clientes & Mercado')

@section('content')
<div class="p-6">
    <!-- Cabeçalho -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white flex items-center gap-3">
                <span class="text-4xl">👥</span>
                Clientes & Mercado
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Análise completa de clientes, leads e 
oportunidades</p>
        </div>
        <button onclick="location.reload()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 
transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 
2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Atualizar
        </button>
    </div>

    <!-- KPIs Linha 1: Clientes e Leads -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        @include('dashboard.partials._kpi-card', [
            'id' => 'kpi-total-clientes',
            'title' => 'Total Clientes',
            'value' => number_format($kpis['total_clientes'], 0, ',', '.'),
            'meta' => 'Cadastrados no sistema',
            'percent' => 100,
            'accent' => 'green',
            'icon' => '👥'
        ])

        @include('dashboard.partials._kpi-card', [
            'id' => 'kpi-clientes-ativos',
            'title' => 'Clientes Ativos',
            'value' => number_format($kpis['clientes_ativos'], 0, ',', '.'),
            'meta' => number_format(($kpis['clientes_ativos'] / max($kpis['total_clientes'], 1)) * 100, 1) . '% 
do total',
            'percent' => ($kpis['clientes_ativos'] / max($kpis['total_clientes'], 1)) * 100,
            'accent' => 'green',
            'icon' => '✅'
        ])

        @include('dashboard.partials._kpi-card', [
            'id' => 'kpi-total-leads',
            'title' => 'Total Leads',
            'value' => number_format($kpis['total_leads'], 0, ',', '.'),
            'meta' => 'Em prospecção',
            'percent' => 100,
            'accent' => 'orange',
            'icon' => '🎯'
        ])

        @include('dashboard.partials._kpi-card', [
            'id' => 'kpi-oportunidades',
            'title' => 'Oportunidades',
            'value' => number_format($kpis['total_oportunidades'], 0, ',', '.'),
            'meta' => $kpis['oportunidades_abertas'] . ' abertas',
            'percent' => ($kpis['oportunidades_abertas'] / max($kpis['total_oportunidades'], 1)) * 100,
            'accent' => 'purple',
            'icon' => '💼'
        ])
    </div>

    <!-- KPIs Linha 2: Valores Financeiros -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        @include('dashboard.partials._kpi-card', [
            'id' => 'kpi-valor-carteira',
            'title' => 'Valor Carteira',
            'value' => 'R$ ' . number_format($kpis['valor_carteira'], 2, ',', '.'),
            'meta' => 'Clientes ativos',
            'percent' => 100,
            'accent' => 'green',
            'icon' => '💰'
        ])

        @include('dashboard.partials._kpi-card', [
            'id' => 'kpi-pipeline',
            'title' => 'Pipeline',
            'value' => 'R$ ' . number_format($kpis['valor_oportunidades'], 2, ',', '.'),
            'meta' => 'Oportunidades abertas',
            'percent' => 100,
            'accent' => 'blue',
            'icon' => '📊'
        ])

        @include('dashboard.partials._kpi-card', [
            'id' => 'kpi-ticket-medio',
            'title' => 'Ticket Médio',
            'value' => 'R$ ' . number_format($kpis['ticket_medio'], 2, ',', '.'),
            'meta' => 'Por cliente ativo',
            'percent' => 100,
            'accent' => 'blue',
            'icon' => '💵'
        ])

        @include('dashboard.partials._kpi-card', [
            'id' => 'kpi-taxa-conversao',
            'title' => 'Taxa Conversão',
            'value' => number_format($kpis['taxa_conversao'], 1) . '%',
            'meta' => 'Lead → Oportunidade',
            'percent' => $kpis['taxa_conversao'],
            'accent' => 'orange',
            'icon' => '🎯'
        ])
    </div>

    <!-- Gráficos e Tabelas -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Gráfico Mix PF/PJ -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border-t-4 border-purple-500 
overflow-hidden">
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                    <span>📈</span>
                    Mix Pessoa Física / Jurídica
                </h3>
            </div>
            <div class="p-6">
                <canvas id="graficoMix" height="250"></canvas>
            </div>
        </div>

        <!-- Top 10 Clientes -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border-t-4 border-green-500 overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                    <span>🏆</span>
                    Top 10 Clientes por Valor
                </h3>
            </div>
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400 
font-semibold">#</th>
                                <th class="text-left py-2 px-3 text-gray-600 dark:text-gray-400 
font-semibold">Cliente</th>
                                <th class="text-right py-2 px-3 text-gray-600 dark:text-gray-400 
font-semibold">Valor</th>
                            </tr>
                        </thead>
                        <tbody id="topClientes" class="text-gray-800 dark:text-gray-200">
                            <tr>
                                <td colspan="3" class="text-center py-8 text-gray-500">
                                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 
border-blue-600"></div>
                                    <p class="mt-2">Carregando...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs Linha 3: Métricas Avançadas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        @include('dashboard.partials._kpi-card', [
            'id' => 'kpi-taxa-retencao',
            'title' => 'Taxa Retenção',
            'value' => number_format($kpis['taxa_retencao'], 1) . '%',
            'meta' => 'Últimos 12 meses',
            'percent' => $kpis['taxa_retencao'],
            'accent' => 'green',
            'icon' => '🔒'
        ])

        @include('dashboard.partials._kpi-card', [
            'id' => 'kpi-concentracao',
            'title' => 'Concentração Top 10',
            'value' => number_format($kpis['concentracao_top10'], 1) . '%',
            'meta' => 'Do valor total',
            'percent' => $kpis['concentracao_top10'],
            'accent' => 'orange',
            'icon' => '📌'
        ])

        @include('dashboard.partials._kpi-card', [
            'id' => 'kpi-crescimento-mom',
            'title' => 'Crescimento MoM',
            'value' => ($kpis['crescimento_mom'] >= 0 ? '+' : '') . number_format($kpis['crescimento_mom'], 1) . 
'%',
            'meta' => 'Mês a mês',
            'percent' => abs($kpis['crescimento_mom']),
            'accent' => $kpis['crescimento_mom'] >= 0 ? 'green' : 'orange',
            'icon' => $kpis['crescimento_mom'] >= 0 ? '📈' : '📉'
        ])

        @include('dashboard.partials._kpi-card', [
            'id' => 'kpi-win-rate',
            'title' => 'Win Rate',
            'value' => number_format($kpis['win_rate'], 1) . '%',
            'meta' => 'Oportunidades ganhas',
            'percent' => $kpis['win_rate'],
            'accent' => 'purple',
            'icon' => '🏅'
        ])
    </div>

    <!-- KPIs Linha 4: Resumo -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @include('dashboard.partials._kpi-card', [
            'id' => 'kpi-novos-mes',
            'title' => 'Novos Clientes (Mês)',
            'value' => number_format($kpis['novos_mes'], 0, ',', '.'),
            'meta' => date('F Y'),
            'percent' => 100,
            'accent' => 'blue',
            'icon' => '🆕'
        ])

        @include('dashboard.partials._kpi-card', [
            'id' => 'kpi-valor-medio-opo',
            'title' => 'Valor Médio Oportunidade',
            'value' => 'R$ ' . number_format($kpis['valor_medio_oportunidade'], 2, ',', '.'),
            'meta' => 'Pipeline ativo',
            'percent' => 100,
            'accent' => 'green',
            'icon' => '💎'
        ])
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico Mix PF/PJ
    const ctx = document.getElementById('graficoMix');
    if (ctx) {
        new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Pessoa Física', 'Pessoa Jurídica'],
                datasets: [{
                    data: [{{ $kpis['clientes_pf'] ?? 0 }}, {{ $kpis['clientes_pj'] ?? 0 }}],
                    backgroundColor: [
                        'rgb(16, 185, 129)', // emerald-500
                        'rgb(59, 130, 246)'   // blue-500
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                size: 14
                            }
                        }
                    }
                }
            }
        });
    }

    // Carregar Top 10 Clientes via AJAX
    fetch('/api/clientes-mercado/top-clientes')
        .then(response => {
            if (!response.ok) throw new Error('Erro na API');
            return response.json();
        })
        .then(data => {
            const tbody = document.getElementById('topClientes');
            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center py-8 text-gray-500">Nenhum cliente 
                return;
            }
            
            let html = '';
            data.forEach((cliente, index) => {
                html += `
                    <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 

                        <td class="py-3 px-3 font-semibold text-gray-600 dark:text-gray-400">${index + 1}</td>
                        <td class="py-3 px-3">${cliente.nome || 'Nome não disponível'}</td>
                        <td class="py-3 px-3 text-right font-semibold text-green-600 dark:text-green-400">
                            R$ ${(cliente.valor || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, 
maximumFractionDigits: 2})}
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        })
        .catch(error => {
            console.error('Erro ao carregar top clientes:', error);
            document.getElementById('topClientes').innerHTML = `
                <tr>
                    <td colspan="3" class="text-center py-8 text-red-500">
                        <span class="text-2xl">⚠️</span>
                        <p class="mt-2">Erro ao carregar dados</p>
                    </td>
                </tr>
            `;
        });
});
</script>
@endsection

