<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 25mm 20mm 25mm 20mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; line-height: 1.5; }
    .header { border-bottom: 3px solid #385776; padding-bottom: 12px; margin-bottom: 20px; }
    .header-title { font-size: 20px; font-weight: bold; color: #1B334A; margin: 0; }
    .header-subtitle { font-size: 11px; color: #666; margin: 4px 0 0 0; }
    .firm-name { font-size: 13px; color: #385776; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 4px; }
    .section { margin-bottom: 16px; }
    .section-title { font-size: 12px; font-weight: bold; color: #385776; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #e0e0e0; padding-bottom: 4px; margin-bottom: 10px; }
    .field-row { margin-bottom: 6px; }
    .field-label { font-weight: bold; color: #555; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
    .field-value { color: #222; font-size: 11px; }
    .grid-2 { width: 100%; }
    .grid-2 td { width: 50%; vertical-align: top; padding-right: 12px; padding-bottom: 6px; }
    .text-block { background: #f7f9fb; border: 1px solid #e8ecf0; border-radius: 4px; padding: 10px; margin-top: 4px; font-size: 11px; color: #333; white-space: pre-wrap; }
    .badge { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; }
    .badge-positiva { background: #d1fae5; color: #065f46; }
    .badge-neutra { background: #fef3c7; color: #92400e; }
    .badge-negativa { background: #fee2e2; color: #991b1b; }
    .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #e0e0e0; padding-top: 6px; }
    .meta-info { font-size: 9px; color: #999; text-align: right; margin-bottom: 10px; }
</style>
</head>
<body>

<div class="header">
    <div class="firm-name">Mayer Advogados</div>
    <p class="header-title">Relatório de Visita Presencial</p>
    <p class="header-subtitle">{{ $account->name }}</p>
</div>

<div class="meta-info">
    Registrado por {{ $activity->createdBy->name ?? 'Sistema' }} em {{ $activity->created_at->format('d/m/Y H:i') }}
</div>

<div class="section">
    <div class="section-title">Identificação da Visita</div>
    <table class="grid-2">
        <tr>
            <td><div class="field-row"><span class="field-label">Data:</span> <span class="field-value">{{ $activity->created_at->format('d/m/Y') }}</span></div></td>
            <td><div class="field-row"><span class="field-label">Responsável:</span> <span class="field-value">{{ $activity->createdBy->name ?? '-' }}</span></div></td>
        </tr>
        <tr>
            <td><div class="field-row"><span class="field-label">Hora de Chegada:</span> <span class="field-value">{{ $activity->visit_arrival_time ? \Carbon\Carbon::parse($activity->visit_arrival_time)->format('H:i') : '-' }}</span></div></td>
            <td><div class="field-row"><span class="field-label">Hora de Saída:</span> <span class="field-value">{{ $activity->visit_departure_time ? \Carbon\Carbon::parse($activity->visit_departure_time)->format('H:i') : '-' }}</span></div></td>
        </tr>
        <tr>
            <td><div class="field-row"><span class="field-label">Meio de Deslocamento:</span> <span class="field-value">{{ $transportLabels[$activity->visit_transport] ?? $activity->visit_transport ?? '-' }}</span></div></td>
            <td><div class="field-row"><span class="field-label">Local:</span> <span class="field-value">{{ $activity->visit_location ?? 'Sede do cliente' }}</span></div></td>
        </tr>
    </table>
</div>

@if($activity->visit_attendees)
<div class="section">
    <div class="section-title">Participantes (lado do cliente)</div>
    <div class="field-value">{{ $activity->visit_attendees }}</div>
</div>
@endif

<div class="section">
    <div class="section-title">Conteúdo da Visita</div>
    <div class="field-row"><span class="field-label">Objetivo:</span> <span class="field-value">{{ $objectiveLabels[$activity->visit_objective] ?? $activity->visit_objective ?? '-' }}</span></div>
    <div class="field-row" style="margin-top:10px"><span class="field-label">Relato da Visita:</span><div class="text-block">{{ $activity->body }}</div></div>
    @if($activity->decisions)
    <div class="field-row" style="margin-top:10px"><span class="field-label">Decisões / Encaminhamentos:</span><div class="text-block">{{ $activity->decisions }}</div></div>
    @endif
    @if($activity->pending_items)
    <div class="field-row" style="margin-top:10px"><span class="field-label">Pendências Geradas:</span><div class="text-block">{{ $activity->pending_items }}</div></div>
    @endif
</div>

<div class="section">
    <div class="section-title">Follow-up & Percepção</div>
    <table class="grid-2">
        <tr>
            <td><div class="field-row"><span class="field-label">Próximo Contato:</span> <span class="field-value">{{ $activity->visit_next_contact ? $activity->visit_next_contact->format('d/m/Y') : 'Não definido' }}</span></div></td>
            <td><div class="field-row"><span class="field-label">Receptividade:</span>
                @if($activity->visit_receptivity)
                    <span class="badge badge-{{ $activity->visit_receptivity }}">{{ $receptivityLabels[$activity->visit_receptivity] ?? $activity->visit_receptivity }}</span>
                @else
                    <span class="field-value">Não informada</span>
                @endif
            </div></td>
        </tr>
    </table>
</div>

<div class="footer">Documento gerado automaticamente pelo sistema RESULTADOS! — Mayer Advogados — {{ now()->format('d/m/Y H:i') }}</div>

</body>
</html>
