{{--
    _insights-financeiro.blade.php
    Bloco "Insights do Mês" para Dashboard Financeiro (Visão Gerencial)

    Regras objetivas (sem IA):
      1. Maior alta de despesa MoM (rubricasMoM.topAumentos[0])
      2. Concentração inadimplência (topAtrasoClientes.top3SharePct)
      3. Expense ratio vs receita (expenseRatio.pct)

    Variáveis esperadas (do $dashboardData):
      $d — array completo retornado por getDashboardData()
--}}

@php
    $rubricasMoM = $d['rubricasMoM'] ?? ['topAumentos' => [], 'topReducoes' => []];
    $topAtraso = $d['topAtrasoClientes'] ?? ['top3SharePct' => 0, 'totalVencido' => 0, 'top' => []];
    $expRatio = $d['expenseRatio'] ?? ['pct' => 0, 'despesas' => 0, 'receita' => 0];
    $resumo = $d['resumoExecutivo'] ?? [];
    $saude = $d['saudeFinanceira'] ?? [];
    $comparativo = $d['comparativoMensal'] ?? [];

    $insights = [];

    // ── INSIGHT 1: Maior alta de despesa MoM ──
    $topAumento = $rubricasMoM['topAumentos'][0] ?? null;
    if ($topAumento && $topAumento['diff'] > 0 && $topAumento['pct'] > 10) {
        $rubNome = $topAumento['rubrica'] ?? '—';
        $rubPct = number_format(abs($topAumento['pct']), 1, ',', '.');
        $rubDiff = 'R$ ' . number_format($topAumento['diff'], 2, ',', '.');
        $insights[] = [
            'icon' => '📈',
            'accent' => 'red',
            'text' => "A rubrica \"{$rubNome}\" teve a maior alta de despesa: +{$rubPct}% (+{$rubDiff}) em relação ao mês anterior.",
        ];
    } else {
        // Texto neutro quando não há variação relevante
        $insights[] = [
            'icon' => '✅',
            'accent' => 'green',
            'text' => 'Nenhuma rubrica de despesa apresentou variação significativa (>10%) em relação ao mês anterior.',
        ];
    }

    // ── INSIGHT 2: Concentração de inadimplência ──
    $top3Pct = (float) ($topAtraso['top3SharePct'] ?? 0);
    $totalVencido = (float) ($topAtraso['totalVencido'] ?? 0);
    if ($totalVencido > 0 && $top3Pct > 50) {
        $top3Fmt = number_format($top3Pct, 1, ',', '.');
        $nomeTop1 = $topAtraso['top'][0]['cliente_nome'] ?? '—';
        $insights[] = [
            'icon' => '⚠️',
            'accent' => 'orange',
            'text' => "Os 3 maiores devedores concentram {$top3Fmt}% da inadimplência total. Principal: {$nomeTop1}.",
        ];
    } elseif ($totalVencido > 0) {
        $insights[] = [
            'icon' => '📊',
            'accent' => 'blue',
            'text' => 'A inadimplência está distribuída — nenhum cliente concentra mais de 50% do total vencido.',
        ];
    } else {
        $insights[] = [
            'icon' => '✅',
            'accent' => 'green',
            'text' => 'Não há contas vencidas registradas no período. Parabéns!',
        ];
    }

    // ── INSIGHT 3: Expense ratio (saúde operacional) ──
    $expPct = (float) ($expRatio['pct'] ?? 0);
    $margem = (float) ($resumo['margemLiquida'] ?? 0);
    if ($expPct > 70) {
        $expFmt = number_format($expPct, 1, ',', '.');
        $insights[] = [
            'icon' => '🔴',
            'accent' => 'red',
            'text' => "O Expense Ratio está em {$expFmt}% — despesas consomem mais de 70% da receita. Atenção à margem operacional.",
        ];
    } elseif ($expPct > 50) {
        $expFmt = number_format($expPct, 1, ',', '.');
        $margemFmt = number_format($margem, 1, ',', '.');
        $insights[] = [
            'icon' => '🟡',
            'accent' => 'yellow',
            'text' => "Expense Ratio em {$expFmt}% com margem líquida de {$margemFmt}%. Dentro do aceitável, mas monitore as despesas.",
        ];
    } else {
        $margemFmt = number_format($margem, 1, ',', '.');
        $insights[] = [
            'icon' => '🟢',
            'accent' => 'green',
            'text' => "Margem líquida saudável de {$margemFmt}%. Despesas bem controladas em relação à receita.",
        ];
    }
@endphp

<div class="rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-sm">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
        <span>💡</span> Insights do Mês
    </h3>
    <div class="space-y-3">
        @foreach($insights as $ins)
            @php
                $bgMap = [
                    'red'    => 'bg-red-50 dark:bg-red-900/10 border-red-200 dark:border-red-800',
                    'orange' => 'bg-orange-50 dark:bg-orange-900/10 border-orange-200 dark:border-orange-800',
                    'yellow' => 'bg-yellow-50 dark:bg-yellow-900/10 border-yellow-200 dark:border-yellow-800',
                    'green'  => 'bg-emerald-50 dark:bg-emerald-900/10 border-emerald-200 dark:border-emerald-800',
                    'blue'   => 'bg-blue-50 dark:bg-blue-900/10 border-blue-200 dark:border-blue-800',
                ];
                $bg = $bgMap[$ins['accent']] ?? $bgMap['blue'];
            @endphp
            <div class="flex items-start gap-3 rounded-xl border {{ $bg }} p-3">
                <span class="text-lg flex-shrink-0 mt-0.5">{{ $ins['icon'] }}</span>
                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $ins['text'] }}</p>
            </div>
        @endforeach
    </div>
</div>
