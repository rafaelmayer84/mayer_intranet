<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 12mm 8mm 12mm 8mm;
        }
        body {
            font-family: "Courier New", Courier, monospace;
            font-size: 9px;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        /* Container principal com furos laterais */
        .page-wrapper {
            position: relative;
            border-left: 18px solid transparent;
            border-right: 18px solid transparent;
        }

        /* Furos laterais simulados */
        .page-wrapper::before,
        .page-wrapper::after {
            content: "";
            position: fixed;
            top: 0;
            bottom: 0;
            width: 14px;
            background-image: radial-gradient(circle 3px, #d1d5db 3px, transparent 3px);
            background-size: 14px 18px;
            background-repeat: repeat-y;
            background-position: center 4px;
        }
        .page-wrapper::before { left: 0; }
        .page-wrapper::after { right: 0; }

        /* Header estilo terminal */
        .header {
            text-align: center;
            padding: 8px 0 6px;
            border-bottom: 2px dashed #6b7280;
            margin-bottom: 4px;
            letter-spacing: 1px;
        }
        .header h1 {
            font-size: 13px;
            font-weight: bold;
            margin: 0 0 2px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .header .sub {
            font-size: 8px;
            color: #6b7280;
            margin: 0;
        }
        .header .line {
            font-size: 7px;
            color: #9ca3af;
            margin: 2px 0 0;
        }

        /* Tabela com listras verde-branco */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        th {
            background-color: #1a1a1a;
            color: #fff;
            padding: 4px 6px;
            text-align: left;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid #000;
        }
        td {
            padding: 3px 6px;
            font-size: 8.5px;
            border-bottom: none;
            letter-spacing: 0.3px;
        }

        /* Listras verde-branco estilo formulário contínuo */
        tr.row-even td {
            background-color: #dcfce7;
        }
        tr.row-odd td {
            background-color: #ffffff;
        }

        /* Hover visual (só CSS, funciona em tela) */
        .currency { text-align: right; font-variant-numeric: tabular-nums; }
        .negative { color: #dc2626; }

        /* Linha de total */
        .total-row td {
            background-color: #e5e7eb !important;
            font-weight: bold;
            border-top: 2px solid #374151;
            border-bottom: 2px double #374151;
            padding: 5px 6px;
        }

        /* Separador tracejado */
        .separator {
            border: none;
            border-top: 1px dashed #9ca3af;
            margin: 4px 0;
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 8px;
            padding-top: 4px;
            border-top: 2px dashed #6b7280;
            font-size: 7px;
            color: #6b7280;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .footer .copy {
            margin-top: 2px;
            font-size: 6.5px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
<div class="page-wrapper">

    <div class="header">
        <h1>╔══ {{ strtoupper($title) }} ══╗</h1>
        <p class="sub">MAYER ADVOGADOS — SISTEMA RESULTADOS!</p>
        <p class="line">════════════════════════════════════════════════════════════════════════</p>
        <p class="sub">Gerado em {{ $generated }} | Confidencial</p>
    </div>

    <table>
        <thead>
            <tr>
                @foreach($columns as $col)
                <th>{{ strtoupper($col['label']) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data as $idx => $row)
            <tr class="{{ $idx % 2 === 0 ? 'row-even' : 'row-odd' }}">
                @foreach($columns as $col)
                <td class="{{ ($col['format'] ?? '') === 'currency' ? 'currency' : '' }}">
                    @php
                        $val = is_array($row) ? ($row[$col['key']] ?? '') : ($row->{$col['key']} ?? '');
                        $fmt = $col['format'] ?? 'text';
                    @endphp
                    @if($fmt === 'currency')
                        <span class="{{ $val < 0 ? 'negative' : '' }}">R$ {{ number_format($val, 2, ',', '.') }}</span>
                    @elseif($fmt === 'percent')
                        {{ number_format($val * 100, 1, ',', '.') }}%
                    @elseif($fmt === 'date' && $val)
                        {{ \Carbon\Carbon::parse($val)->format('d/m/Y') }}
                    @else
                        {{ \Illuminate\Support\Str::limit($val, 70) }}
                    @endif
                </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
        @if(!empty($totals))
        <tfoot>
            <tr class="total-row">
                @foreach($columns as $col)
                <td class="{{ ($col['format'] ?? '') === 'currency' ? 'currency' : '' }}">
                    @if(isset($totals[$col['key']]))
                        @if(($col['format'] ?? '') === 'currency')
                            R$ {{ number_format($totals[$col['key']], 2, ',', '.') }}
                        @else
                            {{ $totals[$col['key']] }}
                        @endif
                    @elseif($loop->first)
                        ► TOTAL
                    @endif
                </td>
                @endforeach
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="footer">
        ════════════════════════════════════════════════════════════════════════
        <br>
        {{ strtoupper($title) }} | Gerado em {{ $generated }} | Pag. {PAGE_NUM}/{PAGE_COUNT}
        <div class="copy">MAYER ADVOGADOS — mayeradvogados.adv.br — CONFIDENCIAL — SISTEMA RESULTADOS!</div>
    </div>

</div>
</body>
</html>
