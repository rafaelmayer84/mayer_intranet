@extends('layouts.app')
@section('title', 'Times & Evolução')
@section('content')
<div class="space-y-6">

    {{-- Cabeçalho com filtros (padrão BSC) --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Times & Evolução</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Dashboard BSC — Maturidade Operacional | Competência: {{ $refDate->translatedFormat('F/Y') }}
            </p>
        </div>
        <form method="GET" action="{{ route('times-evolucao.index') }}" class="flex items-center gap-2">
            <select name="month" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $month === $m ? 'selected' : '' }}>
                        {{ Carbon\Carbon::create(null, $m)->translatedFormat('F') }}
                    </option>
                @endfor
            </select>
            <select name="year" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500">
                @for ($y = 2024; $y <= now()->year; $y++)
                    <option value="{{ $y }}" {{ $year === $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm font-medium">
                Filtrar
            </button>
        </form>
    </div>

    {{-- ═══════ KPI CARDS — Linha 1 (Disciplina Interna) ═══════ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- 1. Aderência ao Registro --}}
        @php
            $ad = $kpis['aderencia_registro'];
            $adMeta = $metas['aderencia_registro'] ?? null;
            $adPercent = $adMeta ? min(($ad['valor'] / max($adMeta['meta_valor'], 1)) * 100, 150) : 0;
        @endphp
        @include('dashboard.partials._kpi-card', [
            'id'       => 'te-aderencia',
            'title'    => 'Aderência ao Registro',
            'value'    => number_format($ad['valor'], 1, ',', '.') . '%',
            'subtitle' => $ad['dias_com_lancto'] . ' de ' . $ad['dias_uteis'] . ' dias úteis com lançamento',
            'icon'     => '📋',
            'accent'   => $ad['valor'] >= 80 ? 'green' : ($ad['valor'] >= 50 ? 'orange' : 'red'),
            'meta'     => $adMeta ? number_format($adMeta['meta_valor'], 0) . '%' : null,
            'percent'  => $adPercent,
            'status'   => $ad['valor'] >= 80 ? 'ok' : ($ad['valor'] >= 50 ? 'atencao' : 'critico'),
        ])

        {{-- 2. Pontualidade --}}
        @php
            $pt = $kpis['pontualidade'];
            $ptMeta = $metas['pontualidade'] ?? null;
            $ptPercent = $ptMeta ? min(($pt['valor'] / max($ptMeta['meta_valor'], 1)) * 100, 150) : 0;
        @endphp
        @include('dashboard.partials._kpi-card', [
            'id'       => 'te-pontualidade',
            'title'    => 'Pontualidade',
            'value'    => number_format($pt['valor'], 1, ',', '.') . '%',
            'subtitle' => $pt['no_prazo'] . ' de ' . $pt['total'] . ' atividades concluídas no prazo',
            'icon'     => '⏱️',
            'accent'   => $pt['valor'] >= 80 ? 'green' : ($pt['valor'] >= 60 ? 'orange' : 'red'),
            'meta'     => $ptMeta ? number_format($ptMeta['meta_valor'], 0) . '%' : null,
            'percent'  => $ptPercent,
            'status'   => $pt['valor'] >= 80 ? 'ok' : ($pt['valor'] >= 60 ? 'atencao' : 'critico'),
        ])

        {{-- 3. Backlog Operacional --}}
        @php
            $bk = $kpis['backlog_operacional'];
            $bkMeta = $metas['backlog_operacional'] ?? null;
            $bkPercent = $bkMeta ? min((max($bkMeta['meta_valor'] - $bk['valor'], 0) / max($bkMeta['meta_valor'], 1)) * 100, 100) : 0;
        @endphp
        @include('dashboard.partials._kpi-card', [
            'id'       => 'te-backlog',
            'title'    => 'Backlog Operacional',
            'value'    => $bk['valor'] . ' atividades',
            'subtitle' => $bk['em_atraso'] . ' em atraso · ' . $bk['no_prazo'] . ' no prazo',
            'icon'     => '📦',
            'accent'   => $bk['em_atraso'] == 0 ? 'green' : ($bk['em_atraso'] <= 10 ? 'orange' : 'red'),
            'meta'     => $bkMeta ? '≤' . number_format($bkMeta['meta_valor'], 0) : null,
            'percent'  => $bkPercent,
            'status'   => $bk['em_atraso'] == 0 ? 'ok' : ($bk['em_atraso'] <= 10 ? 'atencao' : 'critico'),
        ])

        {{-- 4. SLA WhatsApp --}}
        @php
            $sla = $kpis['sla_whatsapp'];
            $slaMeta = $metas['sla_whatsapp'] ?? null;
            $slaPercent = $slaMeta ? min((max($slaMeta['meta_valor'] - $sla['valor'], 0) / max($slaMeta['meta_valor'], 1)) * 100, 100) : 0;
        @endphp
        @include('dashboard.partials._kpi-card', [
            'id'          => 'te-sla-wa',
            'title'       => 'SLA WhatsApp',
            'value'       => number_format($sla['valor'], 1, ',', '.') . ' min',
            'subtitle'    => 'Tempo médio 1ª resposta (' . $sla['total_conversas'] . ' conversas)',
            'icon'        => '💬',
            'accent'      => $sla['valor'] <= 15 ? 'green' : ($sla['valor'] <= 60 ? 'orange' : 'red'),
            'meta'        => $slaMeta ? '≤' . number_format($slaMeta['meta_valor'], 0) . ' min' : null,
            'percent'     => $slaPercent,
            'invertTrend' => true,
            'status'      => $sla['valor'] <= 15 ? 'ok' : ($sla['valor'] <= 60 ? 'atencao' : 'critico'),
        ])
    </div>

    {{-- ═══════ KPI CARDS — Linha 2 (Engajamento e Sistemas) ═══════ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- 5. Conversas sem Resposta --}}
        @php
            $sr = $kpis['conversas_sem_resposta'];
            $srMeta = $metas['conversas_sem_resposta'] ?? null;
            $srPercent = $srMeta ? min((max($srMeta['meta_valor'] - $sr['valor'], 0) / max($srMeta['meta_valor'], 1)) * 100, 100) : 0;
        @endphp
        @include('dashboard.partials._kpi-card', [
            'id'          => 'te-sem-resposta',
            'title'       => 'Conversas sem Resposta',
            'value'       => number_format($sr['valor'], 1, ',', '.') . '%',
            'subtitle'    => $sr['sem_resposta'] . ' de ' . $sr['total'] . ' conversas sem retorno em 4h',
            'icon'        => '🔇',
            'accent'      => $sr['valor'] <= 5 ? 'green' : ($sr['valor'] <= 15 ? 'orange' : 'red'),
            'meta'        => $srMeta ? '≤' . number_format($srMeta['meta_valor'], 0) . '%' : null,
            'percent'     => $srPercent,
            'invertTrend' => true,
            'status'      => $sr['valor'] <= 5 ? 'ok' : ($sr['valor'] <= 15 ? 'atencao' : 'critico'),
        ])

        {{-- 6. Alcance de Avisos --}}
        @php
            $av = $kpis['alcance_avisos'];
            $avMeta = $metas['alcance_avisos'] ?? null;
            $avPercent = $avMeta ? min(($av['valor'] / max($avMeta['meta_valor'], 1)) * 100, 150) : 0;
        @endphp
        @include('dashboard.partials._kpi-card', [
            'id'       => 'te-alcance-avisos',
            'title'    => 'Alcance de Avisos',
            'value'    => number_format($av['valor'], 1, ',', '.') . '%',
            'subtitle' => $av['avisos_publicados'] . ' avisos publicados no mês',
            'icon'     => '📢',
            'accent'   => $av['valor'] >= 80 ? 'green' : ($av['valor'] >= 50 ? 'orange' : 'red'),
            'meta'     => $avMeta ? number_format($avMeta['meta_valor'], 0) . '%' : null,
            'percent'  => $avPercent,
            'status'   => $av['avisos_publicados'] == 0 ? 'sem_meta' : ($av['valor'] >= 80 ? 'ok' : ($av['valor'] >= 50 ? 'atencao' : 'critico')),
        ])

        {{-- 7. Saúde de Sincronização --}}
        @php
            $sy = $kpis['saude_sincronizacao'];
            $syMeta = $metas['saude_sincronizacao'] ?? null;
            $syPercent = $syMeta ? min(($sy['valor'] / max($syMeta['meta_valor'], 1)) * 100, 150) : 0;
        @endphp
        @include('dashboard.partials._kpi-card', [
            'id'       => 'te-saude-sync',
            'title'    => 'Saúde de Sincronização',
            'value'    => number_format($sy['valor'], 1, ',', '.') . '%',
            'subtitle' => $sy['sucesso'] . ' de ' . $sy['total'] . ' syncs com sucesso',
            'icon'     => '🔄',
            'accent'   => $sy['valor'] >= 95 ? 'green' : ($sy['valor'] >= 80 ? 'orange' : 'red'),
            'meta'     => $syMeta ? number_format($syMeta['meta_valor'], 0) . '%' : null,
            'percent'  => $syPercent,
            'status'   => $sy['valor'] >= 95 ? 'ok' : ($sy['valor'] >= 80 ? 'atencao' : 'critico'),
        ])

        {{-- 8. Adoção do CRM --}}
        @php
            $crm = $kpis['adocao_crm'];
            $crmMeta = $metas['adocao_crm'] ?? null;
            $crmPercent = $crmMeta ? min(($crm['valor'] / max($crmMeta['meta_valor'], 1)) * 100, 150) : 0;
        @endphp
        @include('dashboard.partials._kpi-card', [
            'id'       => 'te-adocao-crm',
            'title'    => 'Adoção do CRM',
            'value'    => number_format($crm['valor'], 1, ',', '.') . '%',
            'subtitle' => $crm['com_atividade'] . ' de ' . $crm['total_oportunidades'] . ' oportunidades com atividade',
            'icon'     => '🎯',
            'accent'   => $crm['valor'] >= 70 ? 'green' : ($crm['valor'] >= 40 ? 'purple' : 'red'),
            'meta'     => $crmMeta ? number_format($crmMeta['meta_valor'], 0) . '%' : null,
            'percent'  => $crmPercent,
            'status'   => $crm['total_oportunidades'] == 0 ? 'sem_meta' : ($crm['valor'] >= 70 ? 'ok' : ($crm['valor'] >= 40 ? 'atencao' : 'critico')),
        ])
    </div>

    {{-- ═══════ GRÁFICO TENDÊNCIA 6 MESES ═══════ --}}
    <div class="rounded-2xl bg-white dark:bg-gray-800 p-6 shadow-sm">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Tendência de Maturidade — Últimos 6 meses</h3>
        <canvas id="trendChart" height="100"></canvas>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const trend = @json($trend);
    const labels = trend.map(t => t.label);

    const datasets = [
        { label: 'Aderência Registro (%)',    key: 'aderencia_registro',     color: '#385776', dash: [] },
        { label: 'Pontualidade (%)',          key: 'pontualidade',           color: '#10B981', dash: [] },
        { label: 'SLA WhatsApp (min)',        key: 'sla_whatsapp',           color: '#F59E0B', dash: [5,5] },
        { label: 'Conversas s/ Resposta (%)', key: 'conversas_sem_resposta', color: '#EF4444', dash: [5,5] },
        { label: 'Alcance Avisos (%)',        key: 'alcance_avisos',         color: '#8B5CF6', dash: [] },
        { label: 'Saúde Sync (%)',            key: 'saude_sincronizacao',    color: '#06B6D4', dash: [] },
        { label: 'Adoção CRM (%)',            key: 'adocao_crm',            color: '#EC4899', dash: [] },
    ];

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets.map(ds => ({
                label: ds.label,
                data: trend.map(t => t[ds.key]),
                borderColor: ds.color,
                backgroundColor: ds.color + '20',
                borderWidth: 2,
                borderDash: ds.dash,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: false,
            }))
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { usePointStyle: true, padding: 16, font: { size: 11 } }
                },
                tooltip: {
                    backgroundColor: '#1B334A',
                    titleFont: { size: 12 },
                    bodyFont: { size: 11 },
                    padding: 12,
                    cornerRadius: 8,
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#F3F4F6' },
                    ticks: { font: { size: 11 }, color: '#6B7280' }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 }, color: '#6B7280' }
                }
            }
        }
    });
});
</script>
@endpush
