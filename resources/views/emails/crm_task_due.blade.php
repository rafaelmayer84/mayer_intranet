<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px;">
<div style="max-width: 500px; margin: 0 auto; background: #fff; border-radius: 8px; padding: 24px; border: 1px solid #ddd;">
    <h2 style="color: #1B334A; margin-top: 0;">⚠️ Tarefa de Cadência Vencida</h2>

    <p>Olá, <strong>{{ $userName }}</strong>,</p>

    <p>A seguinte tarefa de cadência está <span style="color: #EF4444; font-weight: bold;">vencida</span>:</p>

    <table style="width: 100%; font-size: 14px; border-collapse: collapse;">
        <tr><td style="padding: 6px 0; color: #666;">Tarefa:</td><td style="padding: 6px 0; font-weight: bold;">{{ $task->title }}</td></tr>
        <tr><td style="padding: 6px 0; color: #666;">Cliente:</td><td style="padding: 6px 0;">{{ $account->name ?? '—' }}</td></tr>
        <tr><td style="padding: 6px 0; color: #666;">Oportunidade:</td><td style="padding: 6px 0;">{{ $opp->title ?? '—' }}</td></tr>
        <tr><td style="padding: 6px 0; color: #666;">Vencimento:</td><td style="padding: 6px 0; color: #EF4444;">{{ $task->due_date->format('d/m/Y') }}</td></tr>
    </table>

    @if($task->description)
    <p style="color: #666; font-size: 13px; margin-top: 12px;">{{ $task->description }}</p>
    @endif

    <div style="margin-top: 20px; text-align: center;">
        <a href="{{ route('crm.opportunities.show', $opp->id ?? 0) }}" style="display: inline-block; background: #385776; color: #fff; padding: 10px 24px; border-radius: 6px; text-decoration: none; font-size: 14px;">Ver Oportunidade</a>
    </div>

    <p style="font-size: 12px; color: #999; margin-top: 20px;">— Sistema CRM | Mayer Advogados</p>
</div>
</body></html>
