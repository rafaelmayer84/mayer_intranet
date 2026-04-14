<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Folha de Pagamento - {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}/{{ $ano }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #1B334A; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #385776; padding-bottom: 15px; }
        .header h1 { font-size: 18px; color: #1B334A; margin-bottom: 5px; }
        .header p { font-size: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background-color: #385776; color: white; padding: 8px 6px; text-align: left; font-size: 10px; }
        th.right, td.right { text-align: right; }
        th.center, td.center { text-align: center; }
        td { padding: 7px 6px; border-bottom: 1px solid #ddd; font-size: 10px; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .totais { background-color: #1B334A !important; color: white; font-weight: bold; }
        .totais td { border-bottom: none; }
        .status-ok { color: #16a34a; font-weight: bold; }
        .status-open { color: #d97706; }
        .status-blocked { color: #dc2626; font-size: 9px; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 9px; color: #888; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>MAYER SOCIEDADE DE ADVOGADOS</h1>
        <p>Folha de Pagamento — {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}/{{ $ano }}</p>
        @if($ciclo)
        <p style="font-size: 10px; margin-top: 5px;">Ciclo: {{ $ciclo->nome }}</p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 22%;">Advogado</th>
                <th class="center" style="width: 8%;">Nível</th>
                <th class="right" style="width: 10%;">RB</th>
                <th class="right" style="width: 10%;">Captação</th>
                <th class="center" style="width: 8%;">Score</th>
                <th class="center" style="width: 7%;">Faixa</th>
                <th class="right" style="width: 9%;">RV Bruta</th>
                <th class="center" style="width: 7%;">Redução</th>
                <th class="right" style="width: 10%;">RV Aplicada</th>
                <th class="right" style="width: 10%;">Total Bruto</th>
                <th class="center" style="width: 9%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($apuracoes as $ap)
            <tr>
                <td>{{ $ap->user->name ?? 'N/D' }}</td>
                <td class="center">{{ $ap->user->nivel_senioridade ?? '-' }}</td>
                <td class="right">R$ {{ number_format($ap->rb_valor, 2, ',', '.') }}</td>
                <td class="right">R$ {{ number_format($ap->captacao_valor, 2, ',', '.') }}</td>
                <td class="center">{{ number_format($ap->gdp_score, 1) }}%</td>
                <td class="center">{{ number_format($ap->percentual_faixa, 1) }}%</td>
                <td class="right">R$ {{ number_format($ap->rv_bruta, 2, ',', '.') }}</td>
                <td class="center">
                    @if($ap->reducao_total_pct > 0)
                        <span style="color: #dc2626;">-{{ number_format($ap->reducao_total_pct, 1) }}%</span>
                    @else
                        -
                    @endif
                </td>
                <td class="right" style="font-weight: bold; color: #385776;">R$ {{ number_format($ap->rv_aplicada, 2, ',', '.') }}</td>
                <td class="right" style="font-weight: bold;">R$ {{ number_format($ap->rb_valor + $ap->rv_aplicada, 2, ',', '.') }}</td>
                <td class="center">
                    @if($ap->bloqueio_motivo)
                        <span class="status-blocked">{{ $ap->bloqueio_motivo }}</span>
                    @elseif($ap->status === 'closed')
                        <span class="status-ok">Fechada</span>
                    @else
                        <span class="status-open">Aberta</span>
                    @endif
                </td>
            </tr>
            @endforeach
            <tr class="totais">
                <td colspan="2"><strong>TOTAIS</strong></td>
                <td class="right">R$ {{ number_format($totais['rb'], 2, ',', '.') }}</td>
                <td class="right">R$ {{ number_format($totais['captacao'], 2, ',', '.') }}</td>
                <td class="center">-</td>
                <td class="center">-</td>
                <td class="right">R$ {{ number_format($totais['rv_bruta'], 2, ',', '.') }}</td>
                <td class="center">-</td>
                <td class="right">R$ {{ number_format($totais['rv_aplicada'], 2, ',', '.') }}</td>
                <td class="right">R$ {{ number_format($totais['total_bruto'], 2, ',', '.') }}</td>
                <td class="center">-</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Documento gerado em {{ now()->format('d/m/Y H:i') }} | Sistema SISRH — Intranet RESULTADOS!
    </div>
</body>
</html>
