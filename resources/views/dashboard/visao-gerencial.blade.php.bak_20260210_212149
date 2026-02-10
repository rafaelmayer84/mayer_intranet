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

@php
    $mix = $d['mixReceita'] ?? [];
    $yoy = $d['receitaYoY'] ?? [];
    $expense = $d['expenseRatio'] ?? [];
    $inad = $d['inadimplencia'] ?? [];
    $qual = $d['qualidadeDados'] ?? [];
    $topAtraso = $d['topAtrasoClientes'] ?? [];
    $rubMoM = $d['rubricasMoM'] ?? [];

    $statusMeta = function($real, $meta, $direction) {
        $real = (float) $real;
        $meta = (float) $meta;
        if ($meta <= 0) return ['label' => '—', 'class' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200'];
        if ($direction === 'LOWER') {
            if ($real <= $meta) return ['label' => 'OK', 'class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'];
            if ($real <= $meta * 1.10) return ['label' => 'Atenção', 'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'];
            return ['label' => 'Crítico', 'class' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200'];
        }
        // HIGHER
        if ($real >= $meta) return ['label' => 'OK', 'class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200'];
        if ($real >= $meta * 0.90) return ['label' => 'Atenção', 'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'];
        return ['label' => 'Crítico', 'class' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200'];
    };
@endphp

<!-- Metas do mês (Gap vs Meta + Semáforo) -->
<div class="mt-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
    <div class="flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Metas do Mês (Gap vs Meta)</h2>
    </div>
    <div class="mt-3 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-left text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    <th class="px-3 py-2">Indicador</th>
                    <th class="px-3 py-2">Realizado</th>
                    <th class="px-3 py-2">Meta</th>
                    <th class="px-3 py-2">% Atingimento</th>
                    <th class="px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-gray-800 dark:text-gray-200">
                @php
                    $itemsMetas = [
                        ['label' => 'Receita', 'real' => $resumo['receitaTotal'] ?? 0, 'meta' => $resumo['receitaMeta'] ?? 0, 'dir' => 'HIGH', 'fmt' => $fmtMoeda],
                        ['label' => 'Despesas', 'real' => $resumo['despesasTotal'] ?? 0, 'meta' => $resumo['despesasMeta'] ?? 0, 'dir' => 'LOWER', 'fmt' => $fmtMoeda],
                        ['label' => 'Resultado', 'real' => $resumo['resultadoLiquido'] ?? 0, 'meta' => $resumo['resultadoMeta'] ?? 0, 'dir' => 'HIGH', 'fmt' => $fmtMoeda],
                        ['label' => 'Margem', 'real' => $resumo['margemLiquida'] ?? 0, 'meta' => $resumo['margemMeta'] ?? 0, 'dir' => 'HIGH', 'fmt' => $fmtPct],
                    ];
                @endphp
                @foreach($itemsMetas as $it)
                    @php
                        $real = (float) $it['real'];
                        $meta = (float) $it['meta'];
                        $pct = $meta > 0 ? ($real / $meta) * 100 : 0;
                        $st = $statusMeta($real, $meta, $it['dir']);
                    @endphp
                    <tr>
                        <td class="px-3 py-2 font-medium">{{ $it['label'] }}</td>
                        <td class="px-3 py-2">{{ ($it['fmt'])($real) }}</td>
                        <td class="px-3 py-2">{{ ($it['fmt'])($meta) }}</td>
                        <td class="px-3 py-2">{{ number_format($pct, 1, ',', '.') }}%</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $st['class'] }}">{{ $st['label'] }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- KPIs adicionais -->
<div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="text-xs text-gray-600 dark:text-gray-400">Mix Receita PF/PJ</p>
        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
            PF {{ $fmtPct($mix['pfPct'] ?? 0) }} • PJ {{ $fmtPct($mix['pjPct'] ?? 0) }}
        </p>
        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
            PF {{ $fmtMoeda($mix['pfValor'] ?? 0) }} • PJ {{ $fmtMoeda($mix['pjValor'] ?? 0) }}
        </p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="text-xs text-gray-600 dark:text-gray-400">Receita YoY</p>
        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $fmtPct($yoy['yoyPct'] ?? 0) }}</p>
        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
            Atual {{ $fmtMoeda($yoy['atual'] ?? 0) }} • Ano ant. {{ $fmtMoeda($yoy['anoAnterior'] ?? 0) }}
        </p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="text-xs text-gray-600 dark:text-gray-400">Expense Ratio</p>
        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $fmtPct($expense['pct'] ?? 0) }}</p>
        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
            Desp {{ $fmtMoeda($expense['despesas'] ?? 0) }} / Rec {{ $fmtMoeda($expense['receita'] ?? 0) }}
        </p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="text-xs text-gray-600 dark:text-gray-400">Inadimplência (% receita)</p>
        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $fmtPct($inad['inadimplencia'] ?? 0) }}</p>
        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
            Vencido {{ $fmtMoeda($inad['totalVencido'] ?? 0) }} • Receita {{ $fmtMoeda($d['resumo']['receitaTotal'] ?? 0) }}
        </p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <p class="text-xs text-gray-600 dark:text-gray-400">Qualidade do dado</p>
        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
            Concil. {{ $fmtPct($qual['pctConciliadoCount'] ?? 0) }}
        </p>
        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
            Receita qualif. {{ $fmtMoeda($qual['receitaQualificada'] ?? 0) }} / {{ $fmtMoeda($qual['receitaTotal'] ?? 0) }}
        </p>
    </div>
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


<div class="grid grid-cols-1 gap-4">
    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Receita PF - Meta x Resultado (12 Meses)</h2>
        </div>
        <div class="mt-4 h-[450px]">
            <canvas id="chart-receita-pf" class="h-full w-full"></canvas>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Receita PJ - Meta x Resultado (12 Meses)</h2>
        </div>
        <div class="mt-4 h-[450px]">
            <canvas id="chart-receita-pj" class="h-full w-full"></canvas>
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
    <div class="mt-4 h-[450px]">
        <canvas id="chart-rentabilidade" class="h-full w-full"></canvas>
    </div>
</div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Despesas por Rubrica</h2>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm text-gray-800 dark:text-gray-200">
                    <thead>
                        <tr class="bg-gray-100 text-left text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <th class="px-3 py-2">Rubrica</th>
                            <th class="px-3 py-2">Atual</th>
                            <th class="px-3 py-2">Meta</th>
                            <th class="px-3 py-2">% Meta</th>
                            <th class="px-3 py-2">Trend</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-despesas-rubrica" class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-800 dark:text-gray-200"></tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Gráfico de Despesas por Rubrica</h2>
            <div class="mt-4 h-[320px]">
                <canvas id="chart-despesas" class="h-full w-full"></canvas>
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
                <table class="min-w-full text-sm text-gray-800 dark:text-gray-200">
                    <thead>
                        <tr class="bg-gray-100 text-left text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <th class="px-3 py-2">Nº</th>
                            <th class="px-3 py-2">Cliente</th>
                            <th class="px-3 py-2">Valor</th>
                            <th class="px-3 py-2">Dias</th>
                        </tr>
                    </thead>
                    <tbody id="tbl-atrasos" class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-800 dark:text-gray-200"></tbody>
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
            <table class="min-w-full text-sm text-gray-800 dark:text-gray-200">
                <thead>
                    <tr class="bg-gray-100 text-left text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <th class="px-3 py-2">Métrica</th>
                        <th class="px-3 py-2">-2</th>
                        <th class="px-3 py-2">-1</th>
                        <th class="px-3 py-2">Atual</th>
                        <th class="px-3 py-2">Tendência</th>
                    </tr>
                </thead>
                <tbody id="tbl-comparativo" class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-800 dark:text-gray-200"></tbody>
            </table>
        </div>
    </div>

</div>


<!-- KPIs adicionais: Concentração do Atraso e Rubricas MoM -->
<div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Concentração do Atraso (Top Clientes)</h2>
            <span class="text-xs text-gray-500 dark:text-gray-400">Top3 = {{ number_format((float)($topAtraso['top3SharePct'] ?? 0), 1, ',', '.') }}%</span>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        <th class="px-3 py-2">Cliente</th>
                        <th class="px-3 py-2">Valor (R$)</th>
                        <th class="px-3 py-2">Share</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-gray-800 dark:text-gray-200">
                    @foreach(($topAtraso['top'] ?? []) as $r)
                        <tr>
                            <td class="px-3 py-2">{{ $r['cliente_nome'] ?? '' }}</td>
                            <td class="px-3 py-2">{{ $fmtMoeda($r['valor'] ?? 0) }}</td>
                            <td class="px-3 py-2">{{ $fmtPct($r['sharePct'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                    @if(empty($topAtraso['top']))
                        <tr><td class="px-3 py-2 text-gray-500 dark:text-gray-400" colspan="3">Sem contas vencidas em aberto.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Rubricas — Variação MoM (Top 5 ↑ / ↓)</h2>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="overflow-x-auto">
                <p class="mb-2 text-xs font-semibold text-gray-600 dark:text-gray-300">Maiores aumentos</p>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <th class="px-3 py-2">Rubrica</th>
                            <th class="px-3 py-2">Var%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-gray-800 dark:text-gray-200">
                        @foreach(($rubMoM['topAumentos'] ?? []) as $r)
                            <tr>
                                <td class="px-3 py-2">{{ $r['rubrica'] ?? '' }}</td>
                                <td class="px-3 py-2">{{ $fmtPct($r['pct'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                        @if(empty($rubMoM['topAumentos']))
                            <tr><td class="px-3 py-2 text-gray-500 dark:text-gray-400" colspan="2">Sem dados.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div class="overflow-x-auto">
                <p class="mb-2 text-xs font-semibold text-gray-600 dark:text-gray-300">Maiores reduções</p>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            <th class="px-3 py-2">Rubrica</th>
                            <th class="px-3 py-2">Var%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800 text-gray-800 dark:text-gray-200">
                        @foreach(($rubMoM['topReducoes'] ?? []) as $r)
                            <tr>
                                <td class="px-3 py-2">{{ $r['rubrica'] ?? '' }}</td>
                                <td class="px-3 py-2">{{ $fmtPct($r['pct'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                        @if(empty($rubMoM['topReducoes']))
                            <tr><td class="px-3 py-2 text-gray-500 dark:text-gray-400" colspan="2">Sem dados.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Regra de variação: se mês anterior = 0 e atual &gt; 0, variação = 100%.
        </p>
    </div>
</div>

<script type="application/json" id="dashboard-exec-data-json">@json($dashboardData ?? [])</script>
<script>
    // Mantém compatibilidade com versões anteriores (e facilita debug pelo console)
    window.__DASHBOARD_EXEC_DATA__ = window.__DASHBOARD_EXEC_DATA__ ?? @json($dashboardData ?? []);
    window.__DASHBOARD_API_URL__ = window.__DASHBOARD_API_URL__ ?? "{{ route('api.visao-gerencial') }}";
    window.__DASHBOARD_EXPORT_URL__ = window.__DASHBOARD_EXPORT_URL__ ?? "{{ route('visao-gerencial.export') }}";
</script>

{{-- Cache-buster: evita o browser manter JS antigo (problema típico: gráfico “pisca” e some) --}}
<script src="{{ asset('js/dashboard-charts.js') }}?v={{ filemtime(public_path('js/dashboard-charts.js')) }}"></script>

<script>
(function () {
    'use strict';

    var LOG_PREFIX = '[VisaoGerencial][INLINE]';

    function log(level, msg, extra) {
        try {
            var fn = console[level] || console.log;
            if (extra !== undefined) fn.call(console, LOG_PREFIX, msg, extra);
            else fn.call(console, LOG_PREFIX, msg);
        } catch (e) { /* noop */ }
    }

    function $(id) { return document.getElementById(id); }

    function parseJsonScript(id) {
        var el = $(id);
        if (!el) return {};
        try {
            var txt = (el.textContent || el.innerText || '').trim();
            if (!txt) return {};
            return JSON.parse(txt);
        } catch (e) {
            log('error', 'Falha ao fazer parse do JSON embedado (' + id + '): ' + e.message);
            return {};
        }
    }

    function getData() {
        if (window.__DASHBOARD_EXEC_DATA__ && typeof window.__DASHBOARD_EXEC_DATA__ === 'object') {
            return window.__DASHBOARD_EXEC_DATA__;
        }
        var parsed = parseJsonScript('dashboard-exec-data-json');
        window.__DASHBOARD_EXEC_DATA__ = parsed;
        return parsed;
    }

    function asArray(v) { return Array.isArray(v) ? v : []; }
    function asNumber(v) { var n = Number(v); return isFinite(n) ? n : 0; }

    function padToLen(arr, len) {
        arr = asArray(arr).slice(0, len);
        while (arr.length < len) arr.push(0);
        return arr;
    }


    function ensure12Labels(fallbackLabels) {
        // Preferimos labels do PJ se existirem; senão, geramos últimos 12 meses (pt-BR).
        if (Array.isArray(fallbackLabels) && fallbackLabels.length === 12) return fallbackLabels.slice();
        var now = new Date();
        var fmt = new Intl.DateTimeFormat('pt-BR', { month: 'short' });
        var labels = [];
        for (var i = 11; i >= 0; i--) {
            var d = new Date(now.getFullYear(), now.getMonth() - i, 1);
            var mon = fmt.format(d);
            // Normaliza "set." => "Set", etc.
            mon = mon.replace('.', '');
            labels.push(mon.charAt(0).toUpperCase() + mon.slice(1) + '/' + String(d.getFullYear()).slice(-2));
        }
        return labels;
    }

    function normalize12Series(obj, fallbackLabels) {
        obj = (obj && typeof obj === 'object') ? obj : {};
        var labels = Array.isArray(obj.meses) ? obj.meses.slice() : [];
        if (labels.length !== 12) {
            labels = ensure12Labels(fallbackLabels);
        }
        var meta = padToLen((Array.isArray(obj.meta) ? obj.meta : []).map(asNumber), 12);
        var realizado = padToLen((Array.isArray(obj.realizado) ? obj.realizado : []).map(asNumber), 12);
        return { meses: labels, meta: meta, realizado: realizado };
    }

    function destroyIfExists(canvasId) {
        if (!window.Chart || typeof Chart.getChart !== 'function') return;
        try {
            var existing = Chart.getChart(canvasId);
            if (existing) {
                existing.destroy();
                log('info', 'Chart destruído: ' + canvasId);
            }
        } catch (e) {
            // Se der erro aqui, não interrompe o fluxo
            log('warn', 'Não foi possível destruir chart existente (' + canvasId + '): ' + e.message);
        }
    }

    function makeBarChart(canvasId, labels, meta, realizado) {
        var canvas = $(canvasId);
        if (!canvas) { log('warn', 'Canvas não encontrado: ' + canvasId); return null; }
        destroyIfExists(canvasId);

        var ctx = canvas.getContext('2d');
        var len = asArray(labels).length;

        var cfg = {
            type: 'bar',
            data: {
                labels: asArray(labels),
                datasets: [
                    {
                        label: 'Meta',
                        data: padToLen(meta, len),
                        backgroundColor: '#3B82F6',
                        borderRadius: 6,
                        maxBarThickness: 28
                    },
                    {
                        label: 'Realizado',
                        data: padToLen(realizado, len),
                        backgroundColor: '#10B981',
                        borderRadius: 6,
                        maxBarThickness: 28
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { display: true }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        };

        var chart = new Chart(ctx, cfg);
        log('info', 'Gráfico criado: ' + canvasId, { ok: true });
        return chart;
    }

    function makeLineChart(canvasId, labels, values) {
        var canvas = $(canvasId);
        if (!canvas) { log('warn', 'Canvas não encontrado: ' + canvasId); return null; }
        destroyIfExists(canvasId);

        var ctx = canvas.getContext('2d');
        var len = asArray(labels).length;

        var cfg = {
            type: 'line',
            data: {
                labels: asArray(labels),
                datasets: [{
                    label: 'Rentabilidade (%)',
                    data: padToLen(values, len),
                    borderColor: '#8B5CF6',
                    backgroundColor: 'rgba(139,92,246,0.12)',
                    tension: 0.25,
                    fill: true,
                    pointRadius: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: { legend: { display: true } },
                scales: { y: { beginAtZero: true } }
            }
        };

        var chart = new Chart(ctx, cfg);
        log('info', 'Gráfico criado: ' + canvasId, { ok: true });
        return chart;
    }

    function makeDoughnut(canvasId, labels, values) {
        var canvas = $(canvasId);
        if (!canvas) { log('warn', 'Canvas não encontrado: ' + canvasId); return null; }
        destroyIfExists(canvasId);

        var ctx = canvas.getContext('2d');
        var palette = ['#60A5FA', '#34D399', '#FBBF24', '#F87171', '#A78BFA', '#22C55E', '#FB7185', '#38BDF8', '#F59E0B', '#C084FC'];

        var cfg = {
            type: 'doughnut',
            data: {
                labels: asArray(labels),
                datasets: [{
                    data: asArray(values),
                    backgroundColor: asArray(values).map(function (_, i) { return palette[i % palette.length]; }),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: { legend: { display: true, position: 'bottom' } }
            }
        };

        var chart = new Chart(ctx, cfg);
        log('info', 'Gráfico criado: ' + canvasId, { ok: true });
        return chart;
    }

    function makeSimpleBar(canvasId, labels, values) {
        var canvas = document.getElementById(canvasId.replace('#', ''));
        if (!canvas) { log('warn', 'Canvas não encontrado: ' + canvasId); return null; }
        destroyIfExists(canvasId);

        var ctx = canvas.getContext('2d');
        var cfg = {
            type: 'bar',
            data: {
                labels: asArray(labels),
                datasets: [{
                    label: 'Valores',
                    data: asArray(values),
                    backgroundColor: '#10B981',
                    borderRadius: 6,
                    maxBarThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        };

        var chart = new Chart(ctx, cfg);
        log('info', 'Gráfico criado: ' + canvasId, { ok: true });
        return chart;
    }

    function fmtBRL(v) {
        try {
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(asNumber(v));
        } catch (e) {
            return 'R$ ' + (Math.round(asNumber(v) * 100) / 100).toFixed(2);
        }
    }

    function escapeHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderTables(d) {
        // Despesas por rubrica (tabela)
        var tbodyDes = $('tbl-despesas');
        if (tbodyDes) {
            var rows = asArray(d.despesasRubrica);
            tbodyDes.innerHTML = rows.map(function (r) {
                var atual = asNumber(r.valor);
                var meta = asNumber(r.meta);
                var perc = meta > 0 ? ((atual / meta) * 100) : 0;
                return (
                    '<tr>' +
                        '<td class="px-3 py-2">' + escapeHtml(r.rubrica) + '</td>' +
                        '<td class="px-3 py-2">' + fmtBRL(atual) + '</td>' +
                        '<td class="px-3 py-2">' + fmtBRL(meta) + '</td>' +
                        '<td class="px-3 py-2">' + (Math.round(perc * 10) / 10).toFixed(1) + '%</td>' +
                        '<td class="px-3 py-2">' + escapeHtml(r.trend || '') + '</td>' +
                    '</tr>'
                );
            }).join('');
        }

        // Contas em atraso (tabela)
        // CORRIGIDO: Usar campos corretos (numero, cliente, dias_atraso)
        var tbodyAtr = $('tbl-atrasos');
        if (tbodyAtr) {
            var atrasos = asArray(d.contasAtrasoLista);
            tbodyAtr.innerHTML = atrasos.map(function (c) {
                return (
                    '<tr>' +
                        '<td class="px-3 py-2">' + escapeHtml(c.numero || '') + '</td>' +
                        '<td class="px-3 py-2">' + escapeHtml(c.cliente || '') + '</td>' +
                        '<td class="px-3 py-2">' + fmtBRL(c.valor) + '</td>' +
                        '<td class="px-3 py-2">' + escapeHtml((c.dias_atraso || 0) + ' dias') + '</td>' +
                    '</tr>'
                );
            }).join('');
        }

        // Comparativo mensal (tabela)
        var tbodyComp = $('tbl-comparativo');
        if (tbodyComp) {
            var comp = d.comparativoMensal || [];
            if (Array.isArray(comp)) {
                tbodyComp.innerHTML = comp.map(function (row) {
                    var label = row.metrica || '';
                    var isPct = label.indexOf('Margem') !== -1;
                    var isTaxa = label.indexOf('Taxa') !== -1;
                    function show(v) {
                        v = asNumber(v);
                        if (isPct || isTaxa) return (Math.round(v * 10) / 10).toFixed(1) + '%';
                        return fmtBRL(v);
                    }
                    var trendVal = asNumber(row.trend);
                    var trendClass = trendVal > 0 ? 'text-emerald-600' : (trendVal < 0 ? 'text-rose-600' : '');
                    var trendArrow = trendVal > 0 ? '\u2191 ' : (trendVal < 0 ? '\u2193 ' : '');
                    return (
                        '<tr>' +
                            '<td class="px-3 py-2">' + escapeHtml(label) + '</td>' +
                            '<td class="px-3 py-2">' + show(row.mes1) + '</td>' +
                            '<td class="px-3 py-2">' + show(row.mes2) + '</td>' +
                            '<td class="px-3 py-2">' + show(row.mes3) + '</td>' +
                            '<td class="px-3 py-2 ' + trendClass + '">' + trendArrow + (Math.round(trendVal * 10) / 10).toFixed(1) + '%</td>' +
                        '</tr>'
                    );
                }).join('');
            }
        }
    }

    function renderCharts(d) {
        // Se existir renderer externo, pode rodar também — mas NÃO retornamos aqui,
        // para garantir que o PF sempre renderize (mesmo sem dados) em todos os browsers.
        if (window.__DASHBOARD_CHARTS_BOOTSTRAP__) {
            try { window.__DASHBOARD_CHARTS_BOOTSTRAP__({ force: true, source: 'inline-guard' }); } catch (e) {}
        }

        d = d || {};


        // 1) Receita PF (NUNCA abortar: se não houver dados, renderiza com zeros)
        var pjLabels = (d.receitaPJ12Meses && Array.isArray(d.receitaPJ12Meses.meses)) ? d.receitaPJ12Meses.meses : null;
        var pf = normalize12Series(d.receitaPF12Meses, pjLabels);
        makeBarChart('chart-receita-pf', pf.meses, pf.meta, pf.realizado);


        // 2) Receita PJ
        var pj = d.receitaPJ12Meses || {};
        makeBarChart('chart-receita-pj', pj.meses || [], pj.meta || [], pj.realizado || []);

        // 3) Rentabilidade (%) (calculada a partir de lucratividade/receita)
        var l = d.lucratividade12Meses || {};
        var meses = asArray(l.meses);
        var receita = asArray(l.receita);
        var lucro = asArray(l.lucratividade);

        var perc = meses.map(function (_, i) {
            var r = asNumber(receita[i]);
            var lu = asNumber(lucro[i]);
            if (!r) return 0;
            return Math.round(((lu / r) * 100) * 100) / 100;
        });

        makeLineChart('chart-rentabilidade', meses, perc);

        // Despesas por rubrica (gráfico)
        var dr = asArray(d.despesasRubrica);
        if (dr.length) {
            // top 8 + "Outras" para evitar gráfico ilegível
            var sorted = dr.slice().sort(function (a, b) { return asNumber(b.valor) - asNumber(a.valor); });
            var top = sorted.slice(0, 8);
            var rest = sorted.slice(8);

            var labels = top.map(function (r) { return r.rubrica; });
            var values = top.map(function (r) { return asNumber(r.valor); });

            if (rest.length) {
                var soma = rest.reduce(function (acc, r) { return acc + asNumber(r.valor); }, 0);
                labels.push('Outras');
                values.push(soma);
            }

            makeDoughnut('chart-despesas', labels, values);
        } else {
            log('info', 'Sem dados para despesasRubrica (gráfico).');
        }

        // Aging de contas (gráfico)
        var a = d.agingContas || {};
        var agingLabels = ['0-15', '16-30', '31-60', '61-90', '91-120', '120+'];
        var agingValues = [
            asNumber(a.dias0_15),
            asNumber(a.dias16_30),
            asNumber(a.dias31_60),
            asNumber(a.dias61_90),
            asNumber(a.dias91_120),
            asNumber(a.dias120_plus)
        ];
        makeSimpleBar('chart-aging', agingLabels, agingValues);

        // Teste de sucesso pedido: Chart.getChart() deve existir (se Chart.js suportar)
        if (window.Chart && typeof Chart.getChart === 'function') {
            log('info', 'Validação Chart.getChart()', {
                pf: !!Chart.getChart('chart-receita-pf'),
                pj: !!Chart.getChart('chart-receita-pj'),
                rent: !!Chart.getChart('chart-rentabilidade')
            });
        }
    }

    function wireFiltersAndExport() {
        // Filtros: opção mais estável é recarregar a página com querystring
        var selAno = $('filter-ano');
        var selMes = $('filter-mes');

        function applyFilters() {
            if (!selAno || !selMes) return;
            var ano = selAno.value;
            var mes = selMes.value;
            var url = new URL(window.location.href);
            url.searchParams.set('ano', ano);
            url.searchParams.set('mes', mes);
            window.location.href = url.toString();
        }

        if (selAno) selAno.addEventListener('change', applyFilters);
        if (selMes) selMes.addEventListener('change', applyFilters);

        // Export menu (toggle)
        var btn = $('btn-export');
        var menu = $('menu-export');
        if (btn && menu) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var isHidden = menu.classList.contains('hidden');
                if (isHidden) menu.classList.remove('hidden');
                else menu.classList.add('hidden');
            });

            document.addEventListener('click', function (e) {
                if (e.target === btn || btn.contains(e.target)) return;
                if (e.target === menu || menu.contains(e.target)) return;
                if (!menu.classList.contains('hidden')) menu.classList.add('hidden');
            });
        }

        // Links de export (CSV/PDF) - mantém o href atualizado
        var linkCsv = $('export-csv');
        var linkPdf = $('export-pdf');
        if (linkCsv || linkPdf) {
            var base = window.__DASHBOARD_EXPORT_URL__ || '';
            try {
                var url = new URL(base, window.location.origin);
                var current = new URL(window.location.href);
                var ano = current.searchParams.get('ano');
                var mes = current.searchParams.get('mes');

                if (ano) url.searchParams.set('ano', ano);
                if (mes) url.searchParams.set('mes', mes);

                if (linkCsv) linkCsv.href = url.toString() + (url.searchParams.toString() ? '&' : '?') + 'format=csv';
                if (linkPdf) linkPdf.href = url.toString() + (url.searchParams.toString() ? '&' : '?') + 'format=pdf';
            } catch (e) {
                // não quebra a página se a URL base for relativa/estranha
            }
        }
    }

    function bootstrap() {
        var d = getData();
        if (!d || typeof d !== 'object') {
            log('error', 'window.__DASHBOARD_EXEC_DATA__ inválido/ausente.');
            return;
        }

        renderTables(d);
        renderCharts(d);
        wireFiltersAndExport();
        log('info', 'bootstrap() finalizado.');
    }

    function waitForChartJs(maxTries) {
        var tries = 0;
        (function tick() {
            tries++;
            if (window.Chart && typeof window.Chart === 'function') {
                log('info', 'Chart.js detectado. Inicializando...');
                bootstrap();
                return;
            }
            if (tries >= maxTries) {
                log('error', 'Chart.js não carregou após ' + maxTries + ' tentativas. Gráficos não serão renderizados.');
                return;
            }
            setTimeout(tick, 150);
        })();
    }

    document.addEventListener('DOMContentLoaded', function () {
        log('info', 'DOMContentLoaded');
        waitForChartJs(20);
    });

})();
</script>

@endsection