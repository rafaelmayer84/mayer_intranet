@extends('layouts.app')

@section('title', 'Visão Gerencial')

@section('content')
<link rel="stylesheet" href="{{ asset('css/dashboard-custom.css') }}">

@php
    $d = $dashboardData ?? [];
    $resumo = $d['resumoExecutivo'] ?? [];
    $saude = $d['saudeFinanceira'] ?? [];

    $fmtMoeda = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $fmtPct = fn($v) => number_format((float) $v, 1, ',', '.') . '%';
@endphp

<div id="dashboard-root" class="space-y-6 dashboard-fadein" data-api-url="{{ route('api.visao-gerencial') }}" data-export-url="{{ route('visao-gerencial.export') }}">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Dashboard Financeira Executiva</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">Visão gerencial por competência com metas, tendências e aging.</p>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <div class="flex items-center gap-2">
                <label for="filter-ano" class="text-xs text-gray-600 dark:text-gray-400">Ano</label>
                <select id="filter-ano" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                    @foreach(($anosDisponiveis ?? []) as $a)
                        <option value="{{ $a }}" @selected((int)$a === (int)$ano)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <label for="filter-mes" class="text-xs text-gray-600 dark:text-gray-400">Mês</label>
                <select id="filter-mes" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                    @foreach(($mesesDisponiveis ?? []) as $mNum => $mLabel)
                        <option value="{{ $mNum }}" @selected((int)$mNum === (int)$mes)>{{ $mLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div class="relative">
                <button id="btn-export" type="button" class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:opacity-90 dark:bg-gray-100 dark:text-gray-900">
                    <span>Exportar</span>
                    <span aria-hidden="true">📥</span>
                </button>
                <div id="export-menu" class="absolute right-0 z-10 mt-2 hidden w-44 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
                    <a id="export-csv" class="block px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800" href="#">Excel (CSV)</a>
                    <button id="export-pdf" type="button" class="block w-full px-4 py-3 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800">PDF (Imprimir)</button>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        @include('dashboard.partials._kpi-card', [
            'id' => 'receita',
            'title' => 'Receita Total',
            'value' => $fmtMoeda($resumo['receitaTotal'] ?? 0),
            'meta' => $fmtMoeda($resumo['receitaMeta'] ?? 0),
            'percent' => ($resumo['receitaMeta'] ?? 0) > 0 ? (($resumo['receitaTotal'] ?? 0) / ($resumo['receitaMeta'] ?? 1) * 100) : 0,
            'trend' => $resumo['receitaTrend'] ?? 0,
            'accent' => 'green',
            'icon' => '💰'
        ])

        @include('dashboard.partials._kpi-card', [
            'id' => 'despesas',
            'title' => 'Despesas Totais',
            'value' => $fmtMoeda($resumo['despesasTotal'] ?? 0),
            'meta' => $fmtMoeda($resumo['despesasMeta'] ?? 0),
            'percent' => ($resumo['despesasMeta'] ?? 0) > 0 ? (($resumo['despesasTotal'] ?? 0) / ($resumo['despesasMeta'] ?? 1) * 100) : 0,
            'trend' => $resumo['despesasTrend'] ?? 0,
            'accent' => 'blue',
            'icon' => '📊'
        ])

        @include('dashboard.partials._kpi-card', [
            'id' => 'resultado',
            'title' => 'Resultado Líquido',
            'value' => $fmtMoeda($resumo['resultadoLiquido'] ?? 0),
            'meta' => $fmtMoeda($resumo['resultadoMeta'] ?? 0),
            'percent' => ($resumo['resultadoMeta'] ?? 0) > 0 ? (($resumo['resultadoLiquido'] ?? 0) / ($resumo['resultadoMeta'] ?? 1) * 100) : 0,
            'trend' => $resumo['resultadoTrend'] ?? 0,
            'accent' => 'orange',
            'icon' => '📈'
        ])

        @include('dashboard.partials._kpi-card', [
            'id' => 'margem',
            'title' => 'Margem Líquida',
            'value' => $fmtPct($resumo['margemLiquida'] ?? 0),
            'meta' => $fmtPct($resumo['margemMeta'] ?? 0),
            'percent' => ($resumo['margemMeta'] ?? 0) > 0 ? (($resumo['margemLiquida'] ?? 0) / ($resumo['margemMeta'] ?? 1) * 100) : 0,
            'trend' => $resumo['margemTrend'] ?? 0,
            'accent' => 'purple',
            'icon' => '📉'
        ])
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        @include('dashboard.partials._health-card', [
            'id' => 'atraso',
            'title' => 'Contas em Atraso',
            'value' => $fmtMoeda($saude['contasAtraso'] ?? 0),
            'sub' => ($saude['contasAtrasoPercent'] ?? 0) . '% da receita',
            'statusRule' => 'atrasoPercent',
            'statusValue' => $saude['contasAtrasoPercent'] ?? 0,
            'trend' => $saude['contasAtrasoTrend'] ?? 0,
            'icon' => '⏰'
        ])

        @include('dashboard.partials._health-card', [
            'id' => 'dias',
            'title' => 'Dias Médio Atraso',
            'value' => (int)($saude['diasMedioAtraso'] ?? 0) . ' dias',
            'sub' => 'Meta: ' . (int)($saude['diasMedioAtrasoMeta'] ?? 30) . ' dias',
            'statusRule' => 'diasAtraso',
            'statusValue' => $saude['diasMedioAtraso'] ?? 0,
            'trend' => $saude['diasMedioAtrasoTrend'] ?? 0,
            'icon' => '📅'
        ])

        @include('dashboard.partials._health-card', [
            'id' => 'cobranca',
            'title' => 'Taxa de Cobrança',
            'value' => $fmtPct($saude['taxaCobranca'] ?? 0),
            'sub' => 'Meta: ' . $fmtPct($saude['taxaCobrancaMeta'] ?? 95),
            'statusRule' => 'taxaCobranca',
            'statusValue' => $saude['taxaCobranca'] ?? 0,
            'trend' => $saude['taxaCobrancaTrend'] ?? 0,
            'icon' => '✅'
        ])
    </div>


<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Receita PF - Meta x Resultado (12 Meses)</h2>
        </div>
        <div class="mt-4">
            <canvas id="chart-receita-pf" height="120"></canvas>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Receita PJ - Meta x Resultado (12 Meses)</h2>
        </div>
        <div class="mt-4">
            <canvas id="chart-receita-pj" height="120"></canvas>
        </div>
    </div>
</div>

<div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Rentabilidade Mensal (%) - 12 Meses</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Rentabilidade = (Receita - Despesas) / Receita × 100</p>
        </div>
    </div>
    <div class="mt-4">
        <canvas id="chart-rentabilidade" height="150"></canvas>
    </div>
</div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Despesas por Rubrica</h2>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-100 text-left text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <th class="px-3 py-2">Rubrica</th>
                            <th class="px-3 py-2">Atual</th>
                            <th class="px-3 py-2">Meta</th>
                            <th class="px-3 py-2">% Meta</th>
                            <th class="px-3 py-2">Trend</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-despesas" class="divide-y divide-gray-100 dark:divide-gray-800"></tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Gráfico de Despesas por Rubrica</h2>
            <div class="mt-4">
                <canvas id="chart-despesas" height="180"></canvas>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Clique em uma rubrica para sinalizar detalhamento (fase 2).</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Contas em Atraso</h2>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-100 text-left text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <th class="px-3 py-2">Cliente</th>
                            <th class="px-3 py-2">Valor</th>
                            <th class="px-3 py-2">Dias</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-atrasos" class="divide-y divide-gray-100 dark:divide-gray-800"></tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Aging de Contas (Empilhado)</h2>
            <div class="mt-4">
                <canvas id="chart-aging" height="160"></canvas>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Clique na faixa para listar clientes (fase 2).</p>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Comparativo Mensal (Últimos 3 Meses)</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-100 text-left text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <th class="px-3 py-2">Métrica</th>
                        <th class="px-3 py-2">-2</th>
                        <th class="px-3 py-2">-1</th>
                        <th class="px-3 py-2">Atual</th>
                        <th class="px-3 py-2">Tendência</th>
                    </tr>
                </thead>
                <tbody id="tbl-comparativo" class="divide-y divide-gray-100 dark:divide-gray-800"></tbody>
            </table>
        </div>
    </div>

</div>

<script type="application/json" id="dashboard-exec-data-json">@json($dashboardData ?? [])</script>
<script>
    // Mantém compatibilidade: o JS lê essas variáveis quando disponível.
    window.__DASHBOARD_EXEC_DATA__ = window.__DASHBOARD_EXEC_DATA__ ?? @json($dashboardData ?? []);
    window.__DASHBOARD_API_URL__ = window.__DASHBOARD_API_URL__ ?? "{{ route('api.visao-gerencial') }}";
    window.__DASHBOARD_EXPORT_URL__ = window.__DASHBOARD_EXPORT_URL__ ?? "{{ route('visao-gerencial.export') }}";
</script>
<script src="{{ asset('js/dashboard-charts.js') }}"></script>
@endsection
