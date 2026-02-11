{{--
    _charts-financeiro-extra.blade.php
    Gráficos adicionais para o Dashboard Financeiro (Visão Gerencial):
      1. Waterfall DRE: Receita → Deduções → Despesas → Resultado
      2. Pareto Inadimplência: Top clientes em atraso + linha % acumulado

    Variáveis esperadas (do $dashboardData / $d):
      $d['resumoExecutivo']     — receitaTotal, deducoesTotal, despesasTotal, resultadoLiquido
      $d['topAtrasoClientes']   — top[] (array com cliente_nome, valor, sharePct)
--}}

{{-- ═══ 1. WATERFALL DRE ═══ --}}
<div class="rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-sm">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
        <span>📊</span> DRE Simplificada (Waterfall)
    </h3>
    <div class="h-72">
        <canvas id="chartWaterfallDRE"></canvas>
    </div>
    <p class="mt-2 text-[11px] text-gray-400 dark:text-gray-500 text-center">
        Receita Bruta → (Deduções) → (Despesas) → Resultado Líquido
    </p>
</div>

{{-- ═══ 2. PARETO INADIMPLÊNCIA ═══ --}}
<div class="rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-sm">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
        <span>📉</span> Pareto — Inadimplência por Cliente
    </h3>
    @php
        $topClientes = $d['topAtrasoClientes']['top'] ?? [];
    @endphp
    @if(count($topClientes) > 0)
        <div class="h-72">
            <canvas id="chartParetoInadimplencia"></canvas>
        </div>
        <p class="mt-2 text-[11px] text-gray-400 dark:text-gray-500 text-center">
            Barras = valor vencido por cliente · Linha = % acumulado do total
        </p>
    @else
        <div class="flex items-center justify-center h-40 text-gray-400 dark:text-gray-500 text-sm">
            Sem dados de inadimplência no período selecionado.
        </div>
    @endif
</div>

{{-- ═══ DADOS JSON PARA OS GRÁFICOS ═══ --}}
@php
    $waterfallJson = [
        'receita'   => (float) ($d['resumoExecutivo']['receitaTotal'] ?? 0),
        'deducoes'  => (float) ($d['resumoExecutivo']['deducoesTotal'] ?? 0),
        'despesas'  => (float) ($d['resumoExecutivo']['despesasTotal'] ?? 0),
        'resultado' => (float) ($d['resumoExecutivo']['resultadoLiquido'] ?? 0),
    ];
    $paretoJson = $topClientes;
@endphp

<script id="waterfallData" type="application/json">{!! json_encode($waterfallJson, JSON_UNESCAPED_UNICODE) !!}</script>
<script id="paretoData" type="application/json">{!! json_encode($paretoJson, JSON_UNESCAPED_UNICODE) !!}</script>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.documentElement.classList.contains('dark');
    const textColor = isDark ? '#9ca3af' : '#6b7280';
    const gridColor = isDark ? '#374151' : '#e5e7eb';

    // ── Helper: formatar moeda BR ──
    const fmtBRL = (v) => 'R$ ' + Number(v).toLocaleString('pt-BR', {minimumFractionDigits: 0, maximumFractionDigits: 0});

    // ════════════════════════════════════════════════════════
    // 1. WATERFALL DRE
    //    Implementação como floating bars (bar chart com [base, topo])
    // ════════════════════════════════════════════════════════
    const wfRaw = JSON.parse(document.getElementById('waterfallData').textContent);
    const wfReceita = wfRaw.receita;
    const wfDeducoes = wfRaw.deducoes;
    const wfDespesas = wfRaw.despesas;
    const wfResultado = wfRaw.resultado;

    // Cada barra é [base, topo] — para floating bars
    // Receita: começa em 0, sobe até receita
    // Deduções: começa em receita, desce (receita - deducoes)
    // Despesas: começa em (receita - deducoes), desce
    // Resultado: começa em 0, sobe até resultado
    const afterDed = wfReceita - wfDeducoes;
    const afterDesp = afterDed - wfDespesas; // = resultado

    const wfCtx = document.getElementById('chartWaterfallDRE');
    if (wfCtx) {
        new Chart(wfCtx, {
            type: 'bar',
            data: {
                labels: ['Receita', 'Deduções', 'Despesas', 'Resultado'],
                datasets: [{
                    label: 'Valor',
                    data: [
                        [0, wfReceita],              // Receita: 0 → receita
                        [afterDed, wfReceita],        // Deduções: caindo de receita para afterDed
                        [afterDesp, afterDed],         // Despesas: caindo de afterDed para resultado
                        [0, Math.max(wfResultado, 0)], // Resultado: 0 → resultado
                    ],
                    backgroundColor: [
                        '#10b981', // verde — receita
                        '#f59e0b', // amarelo — deduções
                        '#ef4444', // vermelho — despesas
                        wfResultado >= 0 ? '#3b82f6' : '#ef4444', // azul se positivo, vermelho se negativo
                    ],
                    borderColor: [
                        '#059669',
                        '#d97706',
                        '#dc2626',
                        wfResultado >= 0 ? '#2563eb' : '#dc2626',
                    ],
                    borderWidth: 1,
                    borderRadius: 4,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                const raw = ctx.raw;
                                const val = Math.abs(raw[1] - raw[0]);
                                return fmtBRL(val);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: textColor, font: { weight: 'bold' } },
                        grid: { display: false }
                    },
                    y: {
                        ticks: {
                            color: textColor,
                            callback: function(v) { return fmtBRL(v); }
                        },
                        grid: { color: gridColor },
                        beginAtZero: true,
                    }
                }
            }
        });
    }

    // ════════════════════════════════════════════════════════
    // 2. PARETO INADIMPLÊNCIA
    //    Barras (valor por cliente) + Linha (% acumulado)
    //    Eixo Y esquerdo = R$ · Eixo Y direito = %
    // ════════════════════════════════════════════════════════
    const paretoRaw = JSON.parse(document.getElementById('paretoData').textContent);
    const paretoCtx = document.getElementById('chartParetoInadimplencia');

    if (paretoCtx && paretoRaw.length > 0) {
        // Calcular total e % acumulado
        const totalVencido = paretoRaw.reduce((sum, c) => sum + (c.valor || 0), 0);
        let acum = 0;
        const labels = [];
        const valores = [];
        const acumulados = [];

        paretoRaw.forEach(c => {
            // Truncar nome do cliente para caber no gráfico
            const nome = (c.cliente_nome || '—').length > 20
                ? (c.cliente_nome || '—').substring(0, 18) + '…'
                : (c.cliente_nome || '—');
            labels.push(nome);
            valores.push(c.valor || 0);
            acum += (c.valor || 0);
            acumulados.push(totalVencido > 0 ? Math.round((acum / totalVencido) * 100) : 0);
        });

        new Chart(paretoCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Valor Vencido',
                        data: valores,
                        backgroundColor: 'rgba(239, 68, 68, 0.7)',
                        borderColor: '#dc2626',
                        borderWidth: 1,
                        borderRadius: 4,
                        yAxisID: 'y',
                        order: 2,
                    },
                    {
                        label: '% Acumulado',
                        data: acumulados,
                        type: 'line',
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: '#f59e0b',
                        fill: false,
                        tension: 0.3,
                        yAxisID: 'y2',
                        order: 1,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: textColor, boxWidth: 12, font: { size: 11 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                if (ctx.dataset.yAxisID === 'y2') {
                                    return ctx.dataset.label + ': ' + ctx.raw + '%';
                                }
                                return ctx.dataset.label + ': ' + fmtBRL(ctx.raw);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: textColor, font: { size: 10 }, maxRotation: 45, minRotation: 25 },
                        grid: { display: false }
                    },
                    y: {
                        position: 'left',
                        ticks: {
                            color: textColor,
                            callback: function(v) { return fmtBRL(v); },
                            font: { size: 10 }
                        },
                        grid: { color: gridColor },
                        beginAtZero: true,
                    },
                    y2: {
                        position: 'right',
                        min: 0,
                        max: 100,
                        ticks: {
                            color: '#f59e0b',
                            callback: function(v) { return v + '%'; },
                            font: { size: 10 }
                        },
                        grid: { display: false },
                    }
                }
            }
        });
    }
});
</script>
@endpush
