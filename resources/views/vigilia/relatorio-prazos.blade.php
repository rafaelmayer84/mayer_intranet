@extends('layouts.app')
@section('title', 'VIGÍLIA — Prazos Críticos')
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
    <div class="no-print flex justify-end gap-2 mb-4">
        <button onclick="window.print()" class="px-3 py-1.5 rounded text-xs font-medium border border-[#385776] text-[#385776] hover:bg-gray-50">🖨 Imprimir</button>
        <a href="/vigilia" class="px-3 py-1.5 rounded text-xs font-medium border border-gray-300 text-gray-500 hover:bg-gray-50">← Voltar</a>
    </div>

    <div class="rel-header">
        <div class="logo-line">Mayer Advogados · Sistema RESULTADOS! · Módulo VIGÍLIA</div>
        <div class="title">RELATÓRIO DE PRAZOS CRÍTICOS</div>
        <div class="subtitle">Cobrança Interna — Gerado em {{ $dados['data_geracao'] }}</div>
        <div class="meta">Dados sincronizados via API DataJuri · Cruzamento automático atividades × andamentos</div>
    </div>

    <div class="rel-kpi-row">
        <div class="rel-kpi"><div class="val text-danger">{{ $dados['contadores']['vencidos'] }}</div><div class="label">Prazos Vencidos</div></div>
        <div class="rel-kpi"><div class="val text-warning">{{ $dados['contadores']['vencendo'] }}</div><div class="label">Vencendo 3 dias</div></div>
        <div class="rel-kpi"><div class="val" style="color:#E8B931;">{{ $dados['contadores']['suspeitas'] }}</div><div class="label">Conclusões Suspeitas</div></div>
    </div>

    @if(count($dados['vencidos']) > 0)
    <div class="rel-section"><div class="rel-section-title">▸ Prazos Vencidos — Ação Imediata</div></div>
    <table class="zebra-table">
        <thead><tr><th>Dias</th><th>Responsável</th><th>Tipo Atividade</th><th>Processo</th><th>Prazo Fatal</th></tr></thead>
        <tbody>
        @foreach($dados['vencidos'] as $v)
            <tr>
                <td><span class="rel-badge rel-badge-red">{{ $v['dias_atraso'] }}d</span></td>
                <td class="font-semibold">{{ $v['responsavel'] }}</td>
                <td>{{ $v['tipo_atividade'] }}</td>
                <td style="font-family:monospace;font-size:10px;">{{ $v['processo_pasta'] ?? '—' }}</td>
                <td>{{ $v['prazo_fatal'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @else
    <p class="text-muted" style="font-size:11px;">Nenhum prazo vencido no momento. ✓</p>
    @endif

    @if(count($dados['vencendo']) > 0)
    <div class="rel-section"><div class="rel-section-title">▸ Vencendo nos Próximos 3 Dias</div></div>
    <table class="zebra-table">
        <thead><tr><th>Dias</th><th>Responsável</th><th>Tipo Atividade</th><th>Processo</th><th>Prazo Fatal</th></tr></thead>
        <tbody>
        @foreach($dados['vencendo'] as $v)
            <tr>
                <td><span class="rel-badge rel-badge-amber">{{ abs($v['dias_atraso']) }}d</span></td>
                <td class="font-semibold">{{ $v['responsavel'] }}</td>
                <td>{{ $v['tipo_atividade'] }}</td>
                <td style="font-family:monospace;font-size:10px;">{{ $v['processo_pasta'] ?? '—' }}</td>
                <td>{{ $v['prazo_fatal'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif

    @if(count($dados['suspeitas']) > 0)
    <div class="rel-section"><div class="rel-section-title">▸ Conclusões Suspeitas</div></div>
    <table class="zebra-table">
        <thead><tr><th>Gap</th><th>Responsável</th><th>Tipo</th><th>Processo</th><th>Conclusão</th><th>Últ. Andamento</th></tr></thead>
        <tbody>
        @foreach($dados['suspeitas'] as $s)
            <tr>
                <td><span class="rel-badge {{ $s->dias_gap > 30 ? 'rel-badge-red' : 'rel-badge-amber' }}">{{ $s->dias_gap }}d</span></td>
                <td class="font-semibold">{{ $s->responsavel_nome }}</td>
                <td>{{ $s->tipo_atividade }}</td>
                <td style="font-family:monospace;font-size:10px;">{{ $s->processo_pasta ?? '—' }}</td>
                <td>{{ $s->data_conclusao ? \Carbon\Carbon::parse($s->data_conclusao)->format('d/m/Y') : '—' }}</td>
                <td>{{ $s->data_ultimo_andamento ? \Carbon\Carbon::parse($s->data_ultimo_andamento)->format('d/m/Y') : '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif

    <div class="rel-footer">
        VIGÍLIA v1.0 · Relatório gerado automaticamente · Compromissos não-jurídicos excluídos · Mayer Advogados © 2026
    </div>
</div>
@endsection
