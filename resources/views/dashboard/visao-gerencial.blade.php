@extends('layouts.app')

@section('title', 'Dashboard Financeira Executiva')

@section('content')
<link rel="stylesheet" href="{{ asset('css/dashboard-custom.css') }}">

@php
    /* ═══════════════════════════════════════════════════════════════════
       DADOS — extraídos do DashboardFinanceProdService::getDashboardData()
       ═══════════════════════════════════════════════════════════════════ */
    $d       = $dashboardData ?? [];
    $resumo  = $d['resumoExecutivo'] ?? [];
    $saude   = $d['saudeFinanceira'] ?? [];
    $topAtraso    = $d['topAtrasoClientes'] ?? [];
    $contasLista  = $d['contasAtrasoLista'] ?? [];
    $aging        = $d['agingContas'] ?? [];
    $expense      = $d['expenseRatio'] ?? [];
    $rubMoM       = $d['rubricasMoM'] ?? [];
    $spark        = $sparklines ?? [];

    /* Dados adicionais para seções restauradas */
    $recPF12       = $d['receitaPF12Meses'] ?? [];
    $recPJ12       = $d['receitaPJ12Meses'] ?? [];
    $lucrat12      = $d['lucratividade12Meses'] ?? [];
    $despRubrica   = $d['despesasRubrica'] ?? [];
    $mixReceita    = $d['mixReceita'] ?? [];
    $receitaYoY    = $d['receitaYoY'] ?? [];
    $inadimplencia = $d['inadimplencia'] ?? [];
    $qualidade     = $d['qualidadeDados'] ?? [];
    $comparativo   = $d['comparativoMensal'] ?? [];

    /* Helpers de formatação */
    $fmt = fn($v) => $v == 0 ? '—' : 'R$ ' . number_format(abs((float)$v), 2, ',', '.');
    $fmtS = fn($v) => $v == 0 ? '—' : (($v < 0) ? '-R$ ' : 'R$ ') . number_format(abs((float)$v), 2, ',', '.');
    $fmtPct = fn($v) => $v == 0 ? '—' : number_format((float)$v, 1, ',', '.') . '%';

    /* Trend arrow helper: retorna [arrow, colorClass, text] */
    $trendInfo = function($val, $invert = false) use ($fmtPct) {
        $v = (float) $val;
        if ($v == 0) return ['', 'text-gray-400', '—'];
        $positive = $invert ? ($v < 0) : ($v > 0);
        $arrow = $v > 0 ? '↑' : '↓';
        $color = $positive ? 'text-emerald-500' : 'text-rose-500';
        return [$arrow, $color, number_format(abs($v), 1, ',', '.') . '%'];
    };

    /* Sparkline SVG builder */
    $buildSparkline = function(?array $data, string $color = '#10b981') {
        if (!$data || count(array_filter($data, fn($v) => $v != 0)) < 2) return '';
        $w = 80; $h = 28; $pad = 2;
        $vals = array_map('floatval', array_values($data));
        $min = min($vals); $max = max($vals);
        $range = $max - $min ?: 1;
        $points = [];
        $n = count($vals);
        for ($i = 0; $i < $n; $i++) {
            $x = $pad + ($i / max($n - 1, 1)) * ($w - 2 * $pad);
            $y = $h - $pad - (($vals[$i] - $min) / $range) * ($h - 2 * $pad);
            $points[] = round($x,1) . ',' . round($y,1);
        }
        $polyline = implode(' ', $points);
        $lastX = round($pad + (($n-1) / max($n-1,1)) * ($w - 2*$pad), 1);
        $lastY = round($h - $pad - (($vals[$n-1] - $min) / $range) * ($h - 2*$pad), 1);
        $areaPoints = $polyline . " {$lastX},{$h} {$pad},{$h}";
        return '<svg viewBox="0 0 '.$w.' '.$h.'" class="w-20 h-7 inline-block" preserveAspectRatio="none">'
            . '<polygon points="'.$areaPoints.'" fill="'.$color.'" opacity="0.15"/>'
            . '<polyline points="'.$polyline.'" fill="none" stroke="'.$color.'" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
            . '<circle cx="'.$lastX.'" cy="'.$lastY.'" r="2" fill="'.$color.'"/>'
            . '</svg>';
    };

    /* Valores dos 4 KPI cards do topo (mockup hero) */
    $cards = [
        [
            'label' => 'Resultado', 'icon' => '💰',
            'value' => $resumo['resultadoLiquido'] ?? 0,
            'trend' => $resumo['resultadoTrend'] ?? 0,
            'spark' => $spark['resultado'] ?? null,
            'color' => '#8b5cf6', 'accent' => 'border-l-violet-500',
            'bg' => 'bg-violet-50 dark:bg-violet-950/20', 'invert' => false,
        ],
        [
            'label' => 'Receita', 'icon' => '📈',
            'value' => $resumo['receitaTotal'] ?? 0,
            'trend' => $resumo['receitaTrend'] ?? 0,
            'spark' => $spark['receita'] ?? null,
            'color' => '#10b981', 'accent' => 'border-l-emerald-500',
            'bg' => 'bg-emerald-50 dark:bg-emerald-950/20', 'invert' => false,
        ],
        [
            'label' => 'Despesas', 'icon' => '🔴',
            'value' => $resumo['despesasTotal'] ?? 0,
            'trend' => $resumo['despesasTrend'] ?? 0,
            'spark' => $spark['despesas'] ?? null,
            'color' => '#ef4444', 'accent' => 'border-l-rose-500',
            'bg' => 'bg-rose-50 dark:bg-rose-950/20', 'invert' => true,
        ],

    ];

    /* Contas em atraso — resumo */
    $qtdAtraso = count($contasLista);
    $vlrAtraso = (float) ($saude['contasAtraso'] ?? 0);
    $pctConcentracao = (float) ($topAtraso['top3SharePct'] ?? 0);
    $totalVencido = (float) ($topAtraso['totalVencido'] ?? 0);

    /* Aging por faixa para badges */
    $faixas = [
        ['label' => '0–15d',  'valor' => (float)($aging['dias0_15'] ?? 0),  'cor' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'],
        ['label' => '16–30d', 'valor' => (float)($aging['dias16_30'] ?? 0), 'cor' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200'],
        ['label' => '31–60d', 'valor' => (float)($aging['dias31_60'] ?? 0), 'cor' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200'],
        ['label' => '61–90d', 'valor' => (float)($aging['dias61_90'] ?? 0), 'cor' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'],
        ['label' => '90d+',   'valor' => (float)(($aging['dias91_120'] ?? 0) + ($aging['dias120_plus'] ?? 0)), 'cor' => 'bg-red-200 text-red-900 dark:bg-red-900/60 dark:text-red-100'],
    ];

    /* Insights — 3 regras fixas */
    $insights = [];
    $topAum = ($rubMoM['topAumentos'] ?? [])[0] ?? null;
    if ($topAum && (float)($topAum['diff'] ?? 0) > 0 && (float)($topAum['pct'] ?? 0) > 10) {
        $insights[] = ['icon' => '⚡', 'color' => 'text-rose-600', 'bg' => 'bg-rose-50 dark:bg-rose-950/20',
            'text' => 'Despesa +' . number_format(abs((float)$topAum['pct']), 0) . '% com ' . ($topAum['rubrica'] ?? 'rubrica')];
    } else {
        $insights[] = ['icon' => '✅', 'color' => 'text-emerald-600', 'bg' => 'bg-emerald-50 dark:bg-emerald-950/20',
            'text' => 'Nenhuma rubrica com variação significativa no mês'];
    }
    if ($totalVencido > 0 && $pctConcentracao > 50) {
        $topNomes = array_slice(array_column($topAtraso['top'] ?? [], 'cliente_nome'), 0, 2);
        $insights[] = ['icon' => '⚠️', 'color' => 'text-amber-600', 'bg' => 'bg-amber-50 dark:bg-amber-950/20',
            'text' => 'Inadimplência concentrada em ' . count($topNomes) . ' clientes (R$ ' . number_format($totalVencido, 0, ',', '.') . ')'];
    } elseif ($totalVencido > 0) {
        $insights[] = ['icon' => 'ℹ️', 'color' => 'text-blue-600', 'bg' => 'bg-blue-50 dark:bg-blue-950/20',
            'text' => 'Inadimplência distribuída entre múltiplos clientes'];
    } else {
        $insights[] = ['icon' => '✅', 'color' => 'text-emerald-600', 'bg' => 'bg-emerald-50 dark:bg-emerald-950/20',
            'text' => 'Sem contas vencidas no período'];
    }
    $expPct = (float)($expense['pct'] ?? 0);
    $margemLiq = (float)($resumo['margemLiquida'] ?? 0);
    if ($expPct > 70) {
        $insights[] = ['icon' => '🔴', 'color' => 'text-rose-600', 'bg' => 'bg-rose-50 dark:bg-rose-950/20',
            'text' => 'Expense ratio ' . $fmtPct($expPct) . ' — margem líquida ' . $fmtPct($margemLiq)];
    } elseif ($expPct > 50) {
        $insights[] = ['icon' => '⚡', 'color' => 'text-amber-600', 'bg' => 'bg-amber-50 dark:bg-amber-950/20',
            'text' => 'Expense ratio ' . $fmtPct($expPct) . ' — margem líquida ' . $fmtPct($margemLiq)];
    } else {
        $insights[] = ['icon' => '✅', 'color' => 'text-emerald-600', 'bg' => 'bg-emerald-50 dark:bg-emerald-950/20',
            'text' => 'Margem saudável de ' . $fmtPct($margemLiq)];
    }

    /* KPIs adicionais — array para renderizar grid */
    $kpisAdicionais = [
        ['label' => 'Mix PF/PJ', 'valor' => ($fmtPct($mixReceita['pfPct'] ?? 0)) . ' / ' . ($fmtPct($mixReceita['pjPct'] ?? 0)), 'sub' => $fmt($mixReceita['pfValor'] ?? 0) . ' PF  ·  ' . $fmt($mixReceita['pjValor'] ?? 0) . ' PJ', 'icon' => '📊'],
        ['label' => 'Receita YoY', 'valor' => $fmtPct($receitaYoY['yoyPct'] ?? 0), 'sub' => 'Atual ' . $fmt($receitaYoY['atual'] ?? 0) . '  ·  Ant. ' . $fmt($receitaYoY['anoAnterior'] ?? 0), 'icon' => '📅'],
        ['label' => 'Expense Ratio', 'valor' => $fmtPct($expense['pct'] ?? 0), 'sub' => 'Desp. ' . $fmt($expense['despesas'] ?? 0) . '  ·  Ded. ' . $fmt($expense['deducoes'] ?? 0), 'icon' => '⚖️'],
        ['label' => 'Inadimplência', 'valor' => $fmtPct($inadimplencia['pctVencidoSobreAberto'] ?? 0), 'sub' => 'Venc. ' . $fmt($inadimplencia['totalVencido'] ?? 0) . '  ·  Aberto ' . $fmt($inadimplencia['totalAberto'] ?? 0), 'icon' => '📉'],
        ['label' => 'Qualidade Dados', 'valor' => $fmtPct($qualidade['pctConciliadoCount'] ?? 0), 'sub' => 'Classificados sobre total de movimentos', 'icon' => '🎯'],
    ];

    /* Health cards */
    $healthCards = [
        ['label' => 'Contas em Atraso', 'valor' => $fmt($saude['contasAtraso'] ?? 0), 'trend' => $saude['contasAtrasoTrend'] ?? 0, 'meta' => '—', 'sub' => $fmtPct($saude['contasAtrasoPercent'] ?? 0) . ' da receita', 'invert' => true],
        ['label' => 'Dias Médio Atraso', 'valor' => ($saude['diasMedioAtraso'] ?? 0) > 0 ? $saude['diasMedioAtraso'] . ' dias' : '—', 'trend' => $saude['diasMedioAtrasoTrend'] ?? 0, 'meta' => ($saude['diasMedioAtrasoMeta'] ?? 0) > 0 ? $saude['diasMedioAtrasoMeta'] . ' dias' : '—', 'sub' => '', 'invert' => true],
        ['label' => 'Taxa de Cobrança', 'valor' => $fmtPct($saude['taxaCobranca'] ?? 0), 'trend' => $saude['taxaCobrancaTrend'] ?? 0, 'meta' => $fmtPct($saude['taxaCobrancaMeta'] ?? 0), 'sub' => '', 'invert' => false],
    ];

    /* Despesas por rubrica — total para doughnut */
    $totalDespRub = array_sum(array_column($despRubrica, 'valor'));
@endphp

{{-- ═══════════════════════════════════════════════════════════════════════
     LAYOUT — HERO (mockup) + SEÇÕES DETALHADAS (original)
     ═══════════════════════════════════════════════════════════════════════ --}}
<div id="dashboard-root" class="max-w-[1400px] mx-auto space-y-6 pb-12"
     data-api-url="{{ route('api.visao-gerencial') }}"
     data-export-url="{{ route('visao-gerencial.export') }}">

    {{-- ─── HEADER ─── --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-gray-100">
                <span class="text-lg">📊</span> Dashboard Financeira Executiva
            </h1>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Visão gerencial por competência com métricas, inadimplência e alertas.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <select id="filter-ano" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm focus:ring-2 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                @foreach(($anosDisponiveis ?? []) as $a)
                    <option value="{{ $a }}" @selected((int)$a === (int)$ano)>{{ $a }}</option>
                @endforeach
            </select>
            <select id="filter-mes" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm focus:ring-2 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                @foreach(($mesesDisponiveis ?? []) as $mNum => $mLabel)
                    <option value="{{ $mNum }}" @selected((int)$mNum === (int)$mes)>{{ $mLabel }}</option>
                @endforeach
            </select>
            <div class="relative">
                <button id="btn-export" type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                    📥 Exportar
                </button>
                <div id="export-menu" class="absolute right-0 z-20 mt-1 hidden w-40 rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
                    <a id="export-csv" class="block px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800" href="#">Excel (CSV)</a>
                    <button id="export-pdf" type="button" class="block w-full px-4 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800">PDF</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
         PARTE 1 — HERO LAYOUT (mockup)
         ═══════════════════════════════════════════════════════════════════ --}}

    {{-- ═══ SEÇÃO 1: 4 KPI CARDS HERO ═══ --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($cards as $c)
            @php
                $val = (float) $c['value'];
                $accentMap = ['border-l-violet-500'=>'purple','border-l-emerald-500'=>'green','border-l-rose-500'=>'red','border-l-blue-500'=>'blue'];
                $cardAccent = $accentMap[$c['accent']] ?? 'blue';
            @endphp
            @include('dashboard.partials._kpi-card', [
                'id' => Str::slug($c['label']),
                'title' => $c['label'],
                'value' => $val != 0 ? $fmtS($val) : '—',
                'meta' => '',
                'percent' => 0,
                'trend' => (float) ($c['trend'] ?? 0),
                'invertTrend' => $c['invert'] ?? false,
                'icon' => $c['icon'],
                'accent' => $cardAccent,
                'sparkline' => $c['spark'] ?? null,
            ])
        @endforeach
    </div>

    {{-- ═══ SEÇÃO 2: WATERFALL DRE + PARETO ═══ --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Metas do Mês <span class="font-normal text-gray-400">(Gap vs Meta)</span></h2>
            <div class="mt-4 h-[260px]"><canvas id="chart-waterfall"></canvas></div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Pareto — Inadimplência por Cliente</h2>
            <div class="mt-4 h-[260px]"><canvas id="chart-pareto"></canvas></div>
            @if(empty($topAtraso['top']))
                <p class="mt-2 text-center text-xs text-gray-400">Sem dados de inadimplência no período.</p>
            @endif
        </div>
    </div>

    {{-- ═══ SEÇÃO 3: CONTAS EM ATRASO ═══ --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Esquerda: Resumo visual + tabela --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Contas em Atraso</h2>
                <div class="flex items-center gap-1">
                    @foreach($faixas as $f)
                        @if($f['valor'] > 0)
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $f['cor'] }}">{{ $f['label'] }}</span>
                        @endif
                    @endforeach
                </div>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-3">
                <div class="rounded-xl bg-rose-50 p-3 text-center dark:bg-rose-950/20">
                    <p class="text-[10px] font-medium uppercase text-rose-600 dark:text-rose-400">Total em Atraso</p>
                    <p class="mt-1 text-xl font-bold text-rose-700 dark:text-rose-300">{{ $qtdAtraso > 0 ? $qtdAtraso : '—' }}</p>
                    <p class="text-[10px] text-rose-500">{{ $vlrAtraso > 0 ? $fmt($vlrAtraso) : '—' }}</p>
                </div>
                <div class="rounded-xl bg-amber-50 p-3 text-center dark:bg-amber-950/20">
                    <p class="text-[10px] font-medium uppercase text-amber-600 dark:text-amber-400">Dias Médio</p>
                    <p class="mt-1 text-xl font-bold text-amber-700 dark:text-amber-300">{{ ($saude['diasMedioAtraso'] ?? 0) > 0 ? $saude['diasMedioAtraso'] : '—' }}</p>
                    <p class="text-[10px] text-amber-500">{{ ($saude['diasMedioAtraso'] ?? 0) > 0 ? 'dias' : '—' }}</p>
                </div>
                <div class="rounded-xl bg-blue-50 p-3 text-center dark:bg-blue-950/20">
                    <p class="text-[10px] font-medium uppercase text-blue-600 dark:text-blue-400">% Concentração</p>
                    <p class="mt-1 text-xl font-bold text-blue-700 dark:text-blue-300">{{ $pctConcentracao > 0 ? $fmtPct($pctConcentracao) : '—' }}</p>
                    <p class="text-[10px] text-blue-500">top 3 clientes</p>
                </div>
            </div>
            @if(!empty($topAtraso['top']))
                <div class="mt-4 space-y-2">
                    @php $maxVal = max(array_column($topAtraso['top'], 'valor')); @endphp
                    @foreach(array_slice($topAtraso['top'], 0, 5) as $cli)
                        @php
                            $barW = $maxVal > 0 ? round(($cli['valor'] / $maxVal) * 100) : 0;
                            $barColor = $cli['valor'] > ($totalVencido * 0.3) ? 'bg-rose-400' : 'bg-amber-400';
                        @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="truncate max-w-[180px] text-gray-700 dark:text-gray-300">{{ $cli['cliente_nome'] }}</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $fmt($cli['valor']) }}</span>
                            </div>
                            <div class="mt-0.5 h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-2 rounded-full {{ $barColor }} transition-all" style="width: {{ $barW }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-4 text-center text-xs text-gray-400">Sem contas vencidas no período.</p>
            @endif
            @if(!empty($contasLista))
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="bg-gray-50 text-left text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                <th class="px-2 py-1.5">Nº</th>
                                <th class="px-2 py-1.5">Cliente</th>
                                <th class="px-2 py-1.5 text-right">Valor (R$)</th>
                                <th class="px-2 py-1.5 text-right">Dias</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach(array_slice($contasLista, 0, 6) as $ct)
                                @php
                                    $diasBadge = match(true) {
                                        $ct['diasAtraso'] > 30 => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
                                        $ct['diasAtraso'] > 15 => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                                        default => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
                                    };
                                @endphp
                                <tr class="text-gray-700 dark:text-gray-300">
                                    <td class="px-2 py-1.5 font-mono text-gray-500">{{ $ct['numero'] }}</td>
                                    <td class="px-2 py-1.5 truncate max-w-[160px]">{{ $ct['cliente'] }}</td>
                                    <td class="px-2 py-1.5 text-right font-semibold">{{ $fmt($ct['valor']) }}</td>
                                    <td class="px-2 py-1.5 text-right">
                                        <span class="inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $diasBadge }}">{{ $ct['diasAtraso'] }}d</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        @if($qtdAtraso > 0)
                            <tfoot>
                                <tr class="bg-gray-50 font-semibold text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                    <td class="px-2 py-1.5" colspan="2">Total</td>
                                    <td class="px-2 py-1.5 text-right">{{ $fmt($vlrAtraso) }}</td>
                                    <td class="px-2 py-1.5 text-right text-gray-500">{{ $fmtPct($saude['contasAtrasoPercent'] ?? 0) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            @endif
        </div>

        {{-- Direita: Concentração + Expense donut + Insights --}}
        <div class="space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Concentração em Atraso</h2>
                    @if(!empty($topAtraso['top']))
                        <span class="text-xs text-gray-400">{{ $fmtPct($pctConcentracao) }} top 3</span>
                    @endif
                </div>
                @if(!empty($topAtraso['top']))
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500 dark:text-gray-400">
                                    <th class="pb-2 pr-2">Cliente</th>
                                    <th class="pb-2 pr-2 text-right">Valor (R$)</th>
                                    <th class="pb-2 text-right">Share%</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach(array_slice($topAtraso['top'], 0, 5) as $r)
                                    <tr class="text-gray-700 dark:text-gray-300">
                                        <td class="py-2 pr-2 flex items-center gap-1.5">
                                            <span class="inline-block h-2.5 w-2.5 rounded-sm {{ $r['sharePct'] > 30 ? 'bg-rose-500' : 'bg-blue-500' }}"></span>
                                            <span class="truncate max-w-[200px]">{{ $r['cliente_nome'] }}</span>
                                        </td>
                                        <td class="py-2 pr-2 text-right font-semibold">{{ $fmt($r['valor']) }}</td>
                                        <td class="py-2 text-right">
                                            <span class="{{ $r['sharePct'] > 30 ? 'text-rose-500' : 'text-blue-500' }}">{{ $r['sharePct'] > 0 ? $fmtPct($r['sharePct']) : '—' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="mt-3 text-center text-xs text-gray-400">Sem contas vencidas em aberto.</p>
                @endif
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-4">
                    <div class="relative flex-shrink-0"><canvas id="chart-expense-donut" width="80" height="80"></canvas></div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Expense Ratio {{ $d['ano'] ?? '' }}</p>
                        <p class="mt-0.5 text-lg font-bold {{ $expPct > 70 ? 'text-rose-600' : ($expPct > 50 ? 'text-amber-600' : 'text-emerald-600') }}">{{ $fmtPct($expPct) }}</p>
                        <p class="text-[10px] text-gray-400">Desp. {{ $fmt($expense['despesas'] ?? 0) }} / Rec. {{ $fmt($expense['receita'] ?? 0) }}</p>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="flex items-center gap-1.5 text-sm font-semibold text-gray-900 dark:text-gray-100">💡 Insights do Mês</h2>
                <div class="mt-3 space-y-2">
                    @foreach($insights as $ins)
                        <div class="flex items-start gap-2 rounded-lg {{ $ins['bg'] }} px-3 py-2">
                            <span class="text-sm">{{ $ins['icon'] }}</span>
                            <p class="text-xs font-medium {{ $ins['color'] }}">{{ $ins['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
         PARTE 2 — SEÇÕES DETALHADAS (restauradas da view original)
         ═══════════════════════════════════════════════════════════════════ --}}

    {{-- Separador visual --}}
    <div class="flex items-center gap-3 pt-2">
        <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></div>
        <span class="text-xs font-medium text-gray-400 dark:text-gray-500">Detalhamento Analítico</span>
        <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></div>
    </div>

    {{-- ═══ 5 KPIs ADICIONAIS ═══ --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach($kpisAdicionais as $idx => $kpi)
            @include('dashboard.partials._kpi-card', [
                'id' => 'add-' . $idx,
                'title' => $kpi['label'],
                'value' => $kpi['valor'],
                'meta' => '',
                'percent' => 0,
                'icon' => $kpi['icon'],
                'accent' => 'blue',
            ])
        @endforeach
    </div>

    {{-- ═══ 3 HEALTH CARDS ═══ --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach($healthCards as $idx => $hc)
            @include('dashboard.partials._kpi-card', [
                'id' => 'health-' . $idx,
                'title' => $hc['label'],
                'value' => $hc['valor'],
                'meta' => $hc['meta'] !== '—' ? $hc['meta'] : '',
                'percent' => 0,
                'trend' => (float) ($hc['trend'] ?? 0),
                'invertTrend' => $hc['invert'] ?? false,
                'icon' => '🏥',
                'accent' => 'red',
            ])
        @endforeach
    </div>

    {{-- ═══ RECEITA PF + PJ 12 MESES ═══ --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">📊 Receita PF — 12 meses</h2>
            <div class="mt-4 h-[260px]"><canvas id="chart-receita-pf"></canvas></div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">📊 Receita PJ — 12 meses</h2>
            <div class="mt-4 h-[260px]"><canvas id="chart-receita-pj"></canvas></div>
        </div>
    </div>

    {{-- ═══ RENTABILIDADE 12 MESES ═══ --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">📈 Rentabilidade — 12 meses</h2>
        <div class="mt-4 h-[260px]"><canvas id="chart-rentabilidade"></canvas></div>
    </div>

    {{-- ═══ DESPESAS POR RUBRICA (tabela + doughnut) ═══ --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">💳 Despesas por Rubrica</h2>
            @if(!empty($despRubrica))
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="bg-gray-50 text-left text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                <th class="px-2 py-1.5">Rubrica</th>
                                <th class="px-2 py-1.5 text-right">Valor (R$)</th>
                                <th class="px-2 py-1.5 text-right">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach(array_slice($despRubrica, 0, 10) as $rb)
                                @php $rbPct = $totalDespRub > 0 ? round(((float)$rb['valor'] / $totalDespRub) * 100, 1) : 0; @endphp
                                <tr class="text-gray-700 dark:text-gray-300">
                                    <td class="px-2 py-1.5 truncate max-w-[200px]">{{ $rb['rubrica'] ?? $rb['classificacao'] ?? '—' }}</td>
                                    <td class="px-2 py-1.5 text-right font-semibold">{{ $fmt($rb['valor']) }}</td>
                                    <td class="px-2 py-1.5 text-right text-gray-500">{{ $fmtPct($rbPct) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 font-semibold text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                <td class="px-2 py-1.5">Total</td>
                                <td class="px-2 py-1.5 text-right">{{ $fmt($totalDespRub) }}</td>
                                <td class="px-2 py-1.5 text-right">100%</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <p class="mt-3 text-center text-xs text-gray-400">Sem despesas classificadas no período.</p>
            @endif
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Distribuição de Despesas</h2>
            <div class="mt-4 flex items-center justify-center h-[260px]"><canvas id="chart-rubrica-doughnut"></canvas></div>
        </div>
    </div>

    {{-- ═══ AGING CHART ═══ --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">⏳ Aging — Contas em Atraso por Faixa</h2>
        <div class="mt-4 h-[220px]"><canvas id="chart-aging"></canvas></div>
    </div>

    {{-- ═══ COMPARATIVO MENSAL (3 meses) ═══ --}}
    @if(!empty($comparativo))
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">📅 Comparativo Mensal</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="bg-gray-50 text-left text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            <th class="px-3 py-2">Indicador</th>
                            @foreach($comparativo as $cm)
                                <th class="px-3 py-2 text-right">{{ $cm['label'] ?? '' }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @php
                            $indicadores = ['receitaTotal' => 'Receita', 'deducoesTotal' => 'Deduções', 'despesasTotal' => 'Despesas', 'resultadoLiquido' => 'Resultado', 'margemLiquida' => 'Margem %'];
                        @endphp
                        @foreach($indicadores as $key => $label)
                            <tr class="text-gray-700 dark:text-gray-300">
                                <td class="px-3 py-2 font-medium">{{ $label }}</td>
                                @foreach($comparativo as $cm)
                                    <td class="px-3 py-2 text-right font-semibold">
                                        @if($key === 'margemLiquida')
                                            {{ $fmtPct($cm[$key] ?? 0) }}
                                        @else
                                            {{ $fmtS($cm[$key] ?? 0) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ═══ RUBRICAS MoM (Top 5 Aumentos + Reduções) ═══ --}}
    @if(!empty($rubMoM['topAumentos']) || !empty($rubMoM['topReducoes']))
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            {{-- Top Aumentos --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-sm font-semibold text-rose-600">🔺 Top 5 Aumentos MoM</h2>
                @if(!empty($rubMoM['topAumentos']))
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500 dark:text-gray-400">
                                    <th class="pb-2 pr-2">Rubrica</th>
                                    <th class="pb-2 pr-2 text-right">Anterior</th>
                                    <th class="pb-2 pr-2 text-right">Atual</th>
                                    <th class="pb-2 text-right">Var%</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach(array_slice($rubMoM['topAumentos'], 0, 5) as $ra)
                                    <tr class="text-gray-700 dark:text-gray-300">
                                        <td class="py-1.5 pr-2 truncate max-w-[160px]">{{ $ra['rubrica'] ?? '—' }}</td>
                                        <td class="py-1.5 pr-2 text-right">{{ $fmt($ra['anterior'] ?? 0) }}</td>
                                        <td class="py-1.5 pr-2 text-right font-semibold">{{ $fmt($ra['atual'] ?? 0) }}</td>
                                        <td class="py-1.5 text-right text-rose-500 font-semibold">+{{ $fmtPct($ra['pct'] ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="mt-3 text-center text-xs text-gray-400">Sem variações significativas.</p>
                @endif
            </div>
            {{-- Top Reduções --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-sm font-semibold text-emerald-600">🔻 Top 5 Reduções MoM</h2>
                @if(!empty($rubMoM['topReducoes']))
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-500 dark:text-gray-400">
                                    <th class="pb-2 pr-2">Rubrica</th>
                                    <th class="pb-2 pr-2 text-right">Anterior</th>
                                    <th class="pb-2 pr-2 text-right">Atual</th>
                                    <th class="pb-2 text-right">Var%</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach(array_slice($rubMoM['topReducoes'], 0, 5) as $rr)
                                    <tr class="text-gray-700 dark:text-gray-300">
                                        <td class="py-1.5 pr-2 truncate max-w-[160px]">{{ $rr['rubrica'] ?? '—' }}</td>
                                        <td class="py-1.5 pr-2 text-right">{{ $fmt($rr['anterior'] ?? 0) }}</td>
                                        <td class="py-1.5 pr-2 text-right font-semibold">{{ $fmt($rr['atual'] ?? 0) }}</td>
                                        <td class="py-1.5 text-right text-emerald-500 font-semibold">{{ $fmtPct($rr['pct'] ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="mt-3 text-center text-xs text-gray-400">Sem variações significativas.</p>
                @endif
            </div>
        </div>
    @endif

</div>

{{-- ═══════════════════════════════════════════════════════════════════════
     JAVASCRIPT — Charts + Filtros + Export
     ═══════════════════════════════════════════════════════════════════════ --}}
@php
    $waterfallJson = [
        'receita'   => (float) ($resumo['receitaTotal'] ?? 0),
        'deducoes'  => (float) ($resumo['deducoesTotal'] ?? 0),
        'despesas'  => (float) ($resumo['despesasTotal'] ?? 0),
        'resultado' => (float) ($resumo['resultadoLiquido'] ?? 0),
    ];

    $paretoJson = [];
    foreach (($topAtraso['top'] ?? []) as $p) {
        $paretoJson[] = ['nome' => $p['cliente_nome'] ?? '', 'valor' => (float) ($p['valor'] ?? 0)];
    }

    $expenseJson = [
        'pct'      => (float) ($expense['pct'] ?? 0),
        'despesas' => (float) ($expense['despesas'] ?? 0),
        'receita'  => (float) ($expense['receita'] ?? 0),
    ];

    /* Dados para gráficos restaurados */
    $recPFJson = [
        'meses' => $recPF12['meses'] ?? [],
        'meta'  => $recPF12['meta'] ?? [],
        'real'  => $recPF12['realizado'] ?? [],
    ];
    $recPJJson = [
        'meses' => $recPJ12['meses'] ?? [],
        'meta'  => $recPJ12['meta'] ?? [],
        'real'  => $recPJ12['realizado'] ?? [],
    ];
    $lucratJson = $lucrat12;

    $rubricaDoughnutJson = [];
    foreach (array_slice($despRubrica, 0, 8) as $rb) {
        $rubricaDoughnutJson[] = ['label' => $rb['rubrica'] ?? $rb['classificacao'] ?? '—', 'valor' => (float)($rb['valor'] ?? 0)];
    }

    $agingJson = [
        ['label' => '0-15d', 'valor' => (float)($aging['dias0_15'] ?? 0)],
        ['label' => '16-30d', 'valor' => (float)($aging['dias16_30'] ?? 0)],
        ['label' => '31-60d', 'valor' => (float)($aging['dias31_60'] ?? 0)],
        ['label' => '61-90d', 'valor' => (float)($aging['dias61_90'] ?? 0)],
        ['label' => '91-120d', 'valor' => (float)($aging['dias91_120'] ?? 0)],
        ['label' => '120d+', 'valor' => (float)($aging['dias120_plus'] ?? 0)],
    ];
@endphp

<script id="waterfallData" type="application/json">{!! json_encode($waterfallJson, JSON_UNESCAPED_UNICODE) !!}</script>
<script id="paretoData" type="application/json">{!! json_encode($paretoJson, JSON_UNESCAPED_UNICODE) !!}</script>
<script id="expenseData" type="application/json">{!! json_encode($expenseJson, JSON_UNESCAPED_UNICODE) !!}</script>
<script id="recPFData" type="application/json">{!! json_encode($recPFJson, JSON_UNESCAPED_UNICODE) !!}</script>
<script id="recPJData" type="application/json">{!! json_encode($recPJJson, JSON_UNESCAPED_UNICODE) !!}</script>
<script id="lucratData" type="application/json">{!! json_encode($lucratJson, JSON_UNESCAPED_UNICODE) !!}</script>
<script id="rubDoughnutData" type="application/json">{!! json_encode($rubricaDoughnutJson, JSON_UNESCAPED_UNICODE) !!}</script>
<script id="agingData" type="application/json">{!! json_encode($agingJson, JSON_UNESCAPED_UNICODE) !!}</script>

<script type="application/json" id="dashboard-exec-data-json">{!! json_encode($dashboardData ?? [], JSON_UNESCAPED_UNICODE) !!}</script>
<script>
    window.__DASHBOARD_EXEC_DATA__ = window.__DASHBOARD_EXEC_DATA__ ?? {!! json_encode($dashboardData ?? [], JSON_UNESCAPED_UNICODE) !!};
    window.__DASHBOARD_API_URL__ = "{{ route('api.visao-gerencial') }}";
    window.__DASHBOARD_EXPORT_URL__ = "{{ route('visao-gerencial.export') }}";
</script>

<script src="{{ asset('js/dashboard-charts.js') }}?v={{ filemtime(public_path('js/dashboard-charts.js')) }}"></script>

<script>
(function() {
    'use strict';

    function parseJSON(id) {
        try { return JSON.parse(document.getElementById(id).textContent); }
        catch(e) { return null; }
    }

    function fmtBRL(v) {
        if (!v || v === 0) return '—';
        return new Intl.NumberFormat('pt-BR', { style:'currency', currency:'BRL' }).format(v);
    }

    function truncate(s, max) {
        return s && s.length > max ? s.substring(0, max) + '…' : s;
    }

    /* Paleta de cores para doughnut */
    var palette = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6','#f97316'];

    function waitChart(cb, tries) {
        tries = tries || 0;
        if (window.Chart) { cb(); return; }
        if (tries > 30) return;
        setTimeout(function() { waitChart(cb, tries + 1); }, 150);
    }

    waitChart(function() {

        /* ─── WATERFALL DRE ─── */
        var wd = parseJSON('waterfallData');
        if (wd) {
            var rec = wd.receita || 0, ded = wd.deducoes || 0, desp = wd.despesas || 0, res = wd.resultado || 0;
            var afterDed = rec - ded;
            new Chart(document.getElementById('chart-waterfall'), {
                type: 'bar',
                data: {
                    labels: ['Receita', 'Dedução', 'Despesas', 'Resultado'],
                    datasets: [{
                        label: 'DRE', data: [[0, rec], [afterDed, rec], [res, afterDed], [0, res]],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444', res >= 0 ? '#3b82f6' : '#ef4444'],
                        borderRadius: 4, borderSkipped: false, maxBarThickness: 48
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, animation: { duration: 600 },
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) { var d = ctx.raw; var val = Array.isArray(d) ? d[1]-d[0] : d; return fmtBRL(Math.abs(val)); } } } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 10 }, callback: function(v) { return 'R$ '+(v/1000).toFixed(0)+'k'; } } }
                    }
                }
            });
        }

        /* ─── PARETO ─── */
        var pd = parseJSON('paretoData');
        if (pd && pd.length > 0) {
            var labels = pd.map(function(p){ return truncate(p.nome,18); });
            var valores = pd.map(function(p){ return p.valor; });
            var total = valores.reduce(function(a,b){ return a+b; },0);
            var acum = [], running = 0;
            valores.forEach(function(v){ running += v; acum.push(total > 0 ? (running/total)*100 : 0); });
            new Chart(document.getElementById('chart-pareto'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        { type:'bar', label:'Valor (R$)', data:valores, backgroundColor:'rgba(239,68,68,0.7)', borderRadius:4, maxBarThickness:36, yAxisID:'y' },
                        { type:'line', label:'% Acum.', data:acum, borderColor:'#f59e0b', backgroundColor:'rgba(245,158,11,0.1)', tension:0.3, fill:true, pointRadius:3, pointBackgroundColor:'#f59e0b', yAxisID:'y1' }
                    ]
                },
                options: {
                    responsive:true, maintainAspectRatio:false, animation:{duration:600},
                    plugins: { legend:{display:true,position:'bottom',labels:{boxWidth:10,font:{size:10}}}, tooltip:{callbacks:{label:function(ctx){ return ctx.datasetIndex===0?fmtBRL(ctx.raw):ctx.raw.toFixed(1)+'%'; }}} },
                    scales: {
                        x:{grid:{display:false},ticks:{font:{size:9},maxRotation:45}},
                        y:{position:'left',grid:{color:'rgba(0,0,0,0.04)'},ticks:{font:{size:10},callback:function(v){return 'R$ '+(v/1000).toFixed(0)+'k';}}},
                        y1:{position:'right',min:0,max:100,grid:{display:false},ticks:{font:{size:10},callback:function(v){return v+'%';}}}
                    }
                }
            });
        }

        /* ─── EXPENSE DONUT ─── */
        var ed = parseJSON('expenseData');
        if (ed && ed.receita > 0) {
            var pct = ed.pct || 0;
            new Chart(document.getElementById('chart-expense-donut'), {
                type: 'doughnut',
                data: { labels:['Despesas','Margem'], datasets:[{ data:[Math.min(pct,100),Math.max(100-pct,0)], backgroundColor:[pct>70?'#ef4444':(pct>50?'#f59e0b':'#10b981'),'#e5e7eb'], borderWidth:0 }] },
                plugins: [{ id:'ct', afterDraw:function(chart){ var ctx=chart.ctx,w=chart.width,h=chart.height; ctx.save(); ctx.font='bold 16px sans-serif'; ctx.fillStyle='#111827'; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText(pct.toFixed(0)+'%',w/2,h/2-6); ctx.font='9px sans-serif'; ctx.fillStyle='#6b7280'; ctx.fillText('Desp',w/2,h/2+10); ctx.restore(); } }],
                options: { responsive:false, cutout:'70%', animation:{duration:600}, plugins:{legend:{display:false},tooltip:{enabled:false}} }
            });
        }

        /* ═══════════════════════════════════════════════════════════════
           GRÁFICOS RESTAURADOS (seções detalhadas)
           ═══════════════════════════════════════════════════════════════ */

        /* Helper: bar chart com meta vs realizado */
        function makeBarMetaReal(canvasId, dataId, color) {
            var dd = parseJSON(dataId);
            if (!dd || !dd.meses || dd.meses.length === 0) return;
            new Chart(document.getElementById(canvasId), {
                type: 'bar',
                data: {
                    labels: dd.meses,
                    datasets: [
                        { label:'Realizado', data:dd.real, backgroundColor:color, borderRadius:3, maxBarThickness:28 },
                        { label:'Meta', data:dd.meta, backgroundColor:'rgba(156,163,175,0.3)', borderRadius:3, maxBarThickness:28 }
                    ]
                },
                options: {
                    responsive:true, maintainAspectRatio:false, animation:{duration:500},
                    plugins: { legend:{display:true,position:'bottom',labels:{boxWidth:10,font:{size:10}}} },
                    scales: {
                        x:{grid:{display:false},ticks:{font:{size:9}}},
                        y:{grid:{color:'rgba(0,0,0,0.04)'},ticks:{font:{size:10},callback:function(v){return 'R$ '+(v/1000).toFixed(0)+'k';}}}
                    }
                }
            });
        }

        /* ─── RECEITA PF 12 MESES ─── */
        makeBarMetaReal('chart-receita-pf', 'recPFData', 'rgba(16,185,129,0.7)');

        /* ─── RECEITA PJ 12 MESES ─── */
        makeBarMetaReal('chart-receita-pj', 'recPJData', 'rgba(59,130,246,0.7)');

        /* ─── RENTABILIDADE 12 MESES ─── */
        var ld = parseJSON('lucratData');
        if (ld) {
            var mLabels = ld.meses || ld.labels || [];
            var lData = ld.lucratividade || ld.data || [];
            if (mLabels.length > 0 && lData.length > 0) {
                new Chart(document.getElementById('chart-rentabilidade'), {
                    type: 'line',
                    data: {
                        labels: mLabels,
                        datasets: [{
                            label: 'Resultado', data: lData,
                            borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.1)',
                            tension: 0.3, fill: true, pointRadius: 3, pointBackgroundColor: '#8b5cf6'
                        }]
                    },
                    options: {
                        responsive:true, maintainAspectRatio:false, animation:{duration:500},
                        plugins: { legend:{display:false} },
                        scales: {
                            x:{grid:{display:false},ticks:{font:{size:9}}},
                            y:{grid:{color:'rgba(0,0,0,0.04)'},ticks:{font:{size:10},callback:function(v){return 'R$ '+(v/1000).toFixed(0)+'k';}}}
                        }
                    }
                });
            }
        }

        /* ─── RUBRICA DOUGHNUT ─── */
        var rd = parseJSON('rubDoughnutData');
        if (rd && rd.length > 0) {
            new Chart(document.getElementById('chart-rubrica-doughnut'), {
                type: 'doughnut',
                data: {
                    labels: rd.map(function(r){ return truncate(r.label, 20); }),
                    datasets: [{ data: rd.map(function(r){ return r.valor; }), backgroundColor: palette.slice(0, rd.length), borderWidth: 1 }]
                },
                options: {
                    responsive:true, maintainAspectRatio:false, animation:{duration:500},
                    plugins: { legend:{display:true,position:'right',labels:{boxWidth:10,font:{size:10}}}, tooltip:{callbacks:{label:function(ctx){ return fmtBRL(ctx.raw); }}} }
                }
            });
        }

        /* ─── AGING ─── */
        var ag = parseJSON('agingData');
        if (ag && ag.length > 0) {
            var agColors = ['#fbbf24','#f59e0b','#f97316','#ef4444','#dc2626','#991b1b'];
            new Chart(document.getElementById('chart-aging'), {
                type: 'bar',
                data: {
                    labels: ag.map(function(a){ return a.label; }),
                    datasets: [{
                        label: 'Valor (R$)', data: ag.map(function(a){ return a.valor; }),
                        backgroundColor: agColors.slice(0, ag.length), borderRadius: 4, maxBarThickness: 48
                    }]
                },
                options: {
                    responsive:true, maintainAspectRatio:false, animation:{duration:500},
                    plugins: { legend:{display:false}, tooltip:{callbacks:{label:function(ctx){ return fmtBRL(ctx.raw); }}} },
                    scales: {
                        x:{grid:{display:false},ticks:{font:{size:11}}},
                        y:{grid:{color:'rgba(0,0,0,0.04)'},ticks:{font:{size:10},callback:function(v){return 'R$ '+(v/1000).toFixed(0)+'k';}}}
                    }
                }
            });
        }

    }); /* end waitChart */

    /* ─── FILTROS ─── */
    function $(id) { return document.getElementById(id); }

    function applyFilters() {
        var a = $('filter-ano'), m = $('filter-mes');
        if (!a || !m) return;
        var url = new URL(window.location.href);
        url.searchParams.set('ano', a.value);
        url.searchParams.set('mes', m.value);
        window.location.href = url.toString();
    }

    var sa = $('filter-ano'), sm = $('filter-mes');
    if (sa) sa.addEventListener('change', applyFilters);
    if (sm) sm.addEventListener('change', applyFilters);

    /* ─── EXPORT MENU ─── */
    var btn = $('btn-export'), menu = $('export-menu');
    if (btn && menu) {
        btn.addEventListener('click', function(e) { e.stopPropagation(); menu.classList.toggle('hidden'); });
        document.addEventListener('click', function() { menu.classList.add('hidden'); });
    }

    var csvLink = $('export-csv');
    if (csvLink) {
        var base = window.__DASHBOARD_EXPORT_URL__ || '/visao-gerencial/export';
        var cur = new URL(window.location.href);
        csvLink.href = base + '?ano=' + (cur.searchParams.get('ano') || new Date().getFullYear()) + '&mes=' + (cur.searchParams.get('mes') || (new Date().getMonth()+1));
    }

    var pdfBtn = $('export-pdf');
    if (pdfBtn) pdfBtn.addEventListener('click', function() { window.print(); });

})();
</script>

@endsection
