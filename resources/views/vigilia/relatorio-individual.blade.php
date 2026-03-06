@extends('layouts.app')
@section('title', 'VIGÍLIA — Relatório Individual')
@section('content')

<style>
    @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&display=swap');
    .rel-container { font-family: 'IBM Plex Mono', 'Courier New', monospace; background: #fff; color: #1a1a1a; }
    .rel-container h1, .rel-container h2, .rel-container h3 { font-family: 'IBM Plex Mono', monospace; }
    .rel-header { border-bottom: 3px double #1B334A; padding-bottom: 12px; margin-bottom: 20px; }
    .rel-header .logo-line { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 2px; }
    .rel-header .title { font-size: 18px; font-weight: 700; color: #1B334A; margin-top: 4px; }
    .rel-header .subtitle { font-size: 11px; color: #888; margin-top: 2px; }
    .rel-header .meta { font-size: 10px; color: #aaa; margin-top: 8px; border-top: 1px solid #ddd; padding-top: 6px; }
    .zebra-table { width: 100%; border-collapse: collapse; font-size: 11px; }
    .zebra-table thead th { background: #1B334A; color: #fff; padding: 6px 10px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
    .zebra-table tbody tr:nth-child(odd) { background: #fff; }
    .zebra-table tbody tr:nth-child(even) { background: #e8f5e9; }
    .zebra-table tbody td { padding: 5px 10px; border-bottom: 1px solid #e0e0e0; vertical-align: top; }
    .zebra-table tbody tr:hover { background: #c8e6c9 !important; }
    .rel-section { margin-top: 24px; margin-bottom: 8px; }
    .rel-section-title { font-size: 12px; font-weight: 700; color: #1B334A; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #1B334A; padding-bottom: 4px; display: inline-block; }
    .rel-kpi-row { display: flex; gap: 16px; margin: 16px 0; }
    .rel-kpi { border: 1px solid #ccc; padding: 10px 16px; text-align: center; flex: 1; }
    .rel-kpi .val { font-size: 22px; font-weight: 700; }
    .rel-kpi .label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #666; margin-top: 2px; }
    .rel-badge { display: inline-block; padding: 1px 6px; font-size: 10px; font-weight: 600; border-radius: 2px; }
    .rel-badge-red { background: #ffcdd2; color: #b71c1c; }
    .rel-badge-amber { background: #fff9c4; color: #f57f17; }
    .rel-badge-green { background: #c8e6c9; color: #1b5e20; }
    .rel-badge-gray { background: #e0e0e0; color: #616161; }
    .rel-footer { margin-top: 24px; padding-top: 8px; border-top: 3px double #1B334A; font-size: 9px; color: #999; }
    .rel-bar { height: 10px; background: #e0e0e0; display: inline-block; vertical-align: middle; }
    .rel-bar-fill { height: 10px; display: inline-block; vertical-align: middle; }
    .text-danger { color: #b71c1c; } .text-warning { color: #f57f17; } .text-success { color: #1b5e20; } .text-muted { color: #999; }
    @media print {
        nav, .sidebar, .no-print, header, .navbar, #sidebar, [data-tooltip] { display: none !important; }
        body, .rel-container { background: #fff !important; margin: 0; padding: 0; }
        .rel-container { padding: 20px; }
        .zebra-table tbody tr:nth-child(even) { background: #e8f5e9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .zebra-table thead th { background: #1B334A !important; color: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .rel-badge-red, .rel-badge-amber, .rel-badge-green, .rel-badge-gray { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
    @page { margin: 15mm; }
</style>

<div class="rel-container max-w-5xl mx-auto px-4 py-6">
    <div class="no-print flex justify-between items-center mb-4">
        <form class="flex gap-2" method="GET" action="/vigilia/relatorio/individual">
            <select name="responsavel" class="rounded border border-gray-200 px-3 py-1.5 text-sm" style="font-family:monospace;">
                @foreach($responsaveis as $r)
                    <option value="{{ $r }}" {{ $r === $responsavel ? 'selected' : '' }}>{{ $r }}</option>
                @endforeach
            </select>
            <select name="periodo" class="rounded border border-gray-200 px-3 py-1.5 text-sm" style="font-family:monospace;">
                <option value="mes-atual">Mês Atual</option>
                <option value="mes-anterior">Mês Anterior</option>
                <option value="trimestre">Trimestre</option>
                <option value="semestre">Semestre</option>
            </select>
            <button class="px-3 py-1.5 rounded text-xs font-medium text-white" style="background:#385776;">Gerar</button>
        </form>
        <div class="flex gap-2">
            <button onclick="window.print()" class="px-3 py-1.5 rounded text-xs font-medium border border-[#385776] text-[#385776]">🖨 Imprimir</button>
            <a href="/vigilia" class="px-3 py-1.5 rounded text-xs font-medium border border-gray-300 text-gray-500">← Voltar</a>
        </div>
    </div>

    @if($dados)
    <div class="rel-header">
        <div class="logo-line">Mayer Advogados · Sistema RESULTADOS! · Módulo VIGÍLIA</div>
        <div class="title">RELATÓRIO INDIVIDUAL: {{ strtoupper($dados['responsavel']) }}</div>
        <div class="subtitle">Período: {{ $inicio ? \Carbon\Carbon::parse($inicio)->format('d/m/Y') : '—' }} a {{ $fim ? \Carbon\Carbon::parse($fim)->format('d/m/Y') : '—' }}</div>
        <div class="meta">Gerado em {{ now()->format('d/m/Y H:i') }} · Dados DataJuri + Cruzamento VIGÍLIA</div>
    </div>

    <div class="rel-kpi-row">
        <div class="rel-kpi"><div class="val">{{ $dados['total'] }}</div><div class="label">Total</div></div>
        <div class="rel-kpi"><div class="val text-success">{{ $dados['concluidos'] }}</div><div class="label">Concluídos</div></div>
        <div class="rel-kpi"><div class="val text-warning">{{ $dados['nao_iniciados'] }}</div><div class="label">Não Iniciados</div></div>
        <div class="rel-kpi"><div class="val text-muted">{{ $dados['cancelados'] }}</div><div class="label">Cancelados</div></div>
        <div class="rel-kpi"><div class="val {{ $dados['taxa'] >= 80 ? 'text-success' : ($dados['taxa'] >= 50 ? 'text-warning' : 'text-danger') }}">{{ $dados['taxa'] }}%</div><div class="label">Taxa Cumpr.</div></div>
        @if($dados['confiabilidade'] !== null)
        <div class="rel-kpi"><div class="val {{ $dados['confiabilidade'] >= 90 ? 'text-success' : ($dados['confiabilidade'] >= 70 ? 'text-warning' : 'text-danger') }}">{{ $dados['confiabilidade'] }}%</div><div class="label">Confiabilidade</div></div>
        @endif
    </div>

    <div class="rel-section"><div class="rel-section-title">▸ Compromissos ({{ count($dados['compromissos']) }})</div></div>
    <table class="zebra-table">
        <thead><tr><th>Status</th><th>Tipo</th><th>Processo</th><th>Data</th><th>Prazo Fatal</th><th>Cruzamento</th></tr></thead>
        <tbody>
        @foreach($dados['compromissos'] as $c)
            @php $c = (object)$c; @endphp
            <tr>
                <td>
                    @if($c->status === 'Concluído' && $c->status_cruzamento === 'suspeito')
                        <span class="rel-badge rel-badge-amber">⚠ Suspeito</span>
                    @elseif($c->status === 'Concluído')
                        <span class="rel-badge rel-badge-green">Concluído</span>
                    @elseif($c->status === 'Não iniciado')
                        <span class="rel-badge rel-badge-red">Não iniciado</span>
                    @else
                        <span class="rel-badge rel-badge-gray">{{ $c->status }}</span>
                    @endif
                </td>
                <td>{{ $c->tipo_atividade }}</td>
                <td style="font-family:monospace;font-size:10px;">{{ $c->processo_pasta ?? '—' }}</td>
                <td>{{ $c->data_hora ? \Carbon\Carbon::parse($c->data_hora)->format('d/m/Y') : '—' }}</td>
                <td>{{ $c->data_prazo_fatal ?? '—' }}</td>
                <td>
                    @if($c->status_cruzamento === 'verificado') <span class="text-success font-bold">✓</span>
                    @elseif($c->status_cruzamento === 'suspeito') <span class="text-warning font-bold">⚠</span>
                    @elseif($c->status_cruzamento === 'sem_acao') <span class="text-danger font-bold">✕</span>
                    @else <span class="text-muted">—</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if(count($dados['alertas']) > 0)
    <div class="rel-section"><div class="rel-section-title">▸ Alertas ({{ count($dados['alertas']) }})</div></div>
    <table class="zebra-table">
        <thead><tr><th>Severidade</th><th>Tipo</th><th>Processo</th><th>Prazo</th><th>Dias</th></tr></thead>
        <tbody>
        @foreach($dados['alertas'] as $a)
            <tr>
                <td><span class="rel-badge {{ $a['severidade'] === 'critico' ? 'rel-badge-red' : ($a['severidade'] === 'alto' ? 'rel-badge-amber' : 'rel-badge-gray') }}">{{ strtoupper($a['severidade']) }}</span></td>
                <td>{{ $a['tipo_atividade'] }}</td>
                <td style="font-family:monospace;font-size:10px;">{{ $a['processo'] ?? '—' }}</td>
                <td>{{ $a['prazo_fatal'] ?? '—' }}</td>
                <td>{{ $a['dias_atraso'] > 0 ? $a['dias_atraso'] . 'd atraso' : '' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif

    <div class="rel-footer">VIGÍLIA v1.0 · Relatório Individual · Mayer Advogados © 2026</div>
    @else
    <div class="text-center py-12" style="font-family:monospace;color:#999;">Selecione um responsável e clique em "Gerar".</div>
    @endif
</div>
@endsection
