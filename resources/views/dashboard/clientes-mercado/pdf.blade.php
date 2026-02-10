<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Clientes & Mercado - {{ $data['competencia']['label'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
        .header h1 { font-size: 18px; color: #1e40af; }
        .header p { font-size: 10px; color: #666; margin-top: 5px; }
        .section { margin-bottom: 15px; }
        .section-title { font-size: 12px; font-weight: bold; color: #1e40af; border-bottom: 1px solid #ddd; padding-bottom: 3px; margin-bottom: 8px; }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 15px; }
        .kpi-card { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; background: #f9fafb; }
        .kpi-card .label { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .kpi-card .value { font-size: 16px; font-weight: bold; color: #111; margin-top: 3px; }
        .kpi-card .detail { font-size: 8px; color: #9ca3af; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; color: #374151; }
        tr:nth-child(even) { background: #f9fafb; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; }
        .two-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .highlight-green { color: #059669; }
        .highlight-red { color: #dc2626; }
        .highlight-purple { color: #7c3aed; }
        @media print {
            body { padding: 10px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer;">🖨️ Imprimir / Salvar PDF</button>
        <button onclick="window.close()" style="padding: 8px 16px; background: #6b7280; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 5px;">Fechar</button>
    </div>

    <div class="header">
        <h1>📊 Dashboard Clientes & Mercado</h1>
        <p>Competência: {{ $data['competencia']['label'] }} | Gerado em: {{ $data['gerado_em'] }}</p>
    </div>

    {{-- KPIs Principais --}}
    <div class="section">
        <div class="section-title">KPIs Principais</div>
        <div class="kpi-grid">
            @foreach($data['kpis_principais'] as $kpi)
            <div class="kpi-card">
                <div class="label">{{ $kpi['icon'] }} {{ $kpi['label'] }}</div>
                <div class="value">
                    @if($kpi['formato'] === 'moeda')
                        R$ {{ number_format($kpi['valor'], 2, ',', '.') }}
                    @else
                        {{ number_format($kpi['valor'], 0, ',', '.') }}
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- KPIs Secundários --}}
    <div class="section">
        <div class="section-title">KPIs Secundários</div>
        <div class="kpi-grid">
            @foreach($data['kpis_secundarios'] as $kpi)
            <div class="kpi-card">
                <div class="label">{{ $kpi['icon'] }} {{ $kpi['label'] }}</div>
                <div class="value">
                    @if($kpi['formato'] === 'moeda')
                        R$ {{ number_format($kpi['valor'], 2, ',', '.') }}
                    @elseif($kpi['formato'] === 'percentual')
                        {{ number_format($kpi['valor'], 1, ',', '.') }}%
                    @else
                        {{ number_format($kpi['valor'], 0, ',', '.') }}
                    @endif
                </div>
                @if(isset($kpi['detalhe']))
                <div class="detail">{{ $kpi['detalhe'] }}</div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    <div class="two-columns">
        {{-- Oportunidades por Estágio --}}
        <div class="section">
            <div class="section-title">Oportunidades por Estágio</div>
            <table>
                <thead>
                    <tr>
                        <th>Estágio</th>
                        <th class="text-center">Qtd</th>
                        <th class="text-right">Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="highlight-green">✅ Ganhas</td>
                        <td class="text-center">{{ $data['oportunidades_por_estagio']['ganhas']['qtd'] }}</td>
                        <td class="text-right">R$ {{ number_format($data['oportunidades_por_estagio']['ganhas']['valor'], 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="highlight-red">❌ Perdidas</td>
                        <td class="text-center">{{ $data['oportunidades_por_estagio']['perdidas']['qtd'] }}</td>
                        <td class="text-right">R$ {{ number_format($data['oportunidades_por_estagio']['perdidas']['valor'], 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td class="highlight-purple">📈 Pipeline</td>
                        <td class="text-center">{{ $data['oportunidades_por_estagio']['pipeline']['qtd'] }}</td>
                        <td class="text-right">R$ {{ number_format($data['oportunidades_por_estagio']['pipeline']['valor'], 2, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Totais Acumulados --}}
        <div class="section">
            <div class="section-title">Totais Acumulados</div>
            <table>
                <tbody>
                    <tr><td>📊 Total Clientes</td><td class="text-right"><strong>{{ number_format($totais['total_clientes'], 0, ',', '.') }}</strong></td></tr>
                    <tr><td>👥 Total Leads</td><td class="text-right"><strong>{{ number_format($totais['total_leads'], 0, ',', '.') }}</strong></td></tr>
                    <tr><td>🎯 Total Oportunidades</td><td class="text-right"><strong>{{ number_format($totais['total_oportunidades'], 0, ',', '.') }}</strong></td></tr>
                    <tr><td>⚖️ Processos Ativos</td><td class="text-right"><strong>{{ number_format($totais['processos_ativos'], 0, ',', '.') }}</strong></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Série 12 Meses --}}
    <div class="section">
        <div class="section-title">Série Histórica (12 meses)</div>
        <table>
            <thead>
                <tr>
                    <th>Mês</th>
                    <th class="text-center">Leads Novos</th>
                    <th class="text-center">Convertidos</th>
                    <th class="text-center">Ops Ganhas</th>
                    <th class="text-right">Valor Ganho</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['serie_12_meses'] as $m)
                <tr>
                    <td>{{ $m['label'] }}</td>
                    <td class="text-center">{{ $m['leads_novos'] }}</td>
                    <td class="text-center">{{ $m['leads_convertidos'] }}</td>
                    <td class="text-center">{{ $m['oportunidades_ganhas'] }}</td>
                    <td class="text-right">R$ {{ number_format($m['valor_ganho'], 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Top 10 Clientes --}}
    <div class="section">
        <div class="section-title">🏆 Top 10 Clientes por Processos Ativos</div>
        <table>
            <thead>
                <tr>
                    <th class="text-center">#</th>
                    <th>Cliente</th>
                    <th class="text-center">Tipo</th>
                    <th class="text-center">Processos Ativos</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['top_10_clientes'] as $i => $cliente)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $cliente->nome }}</td>
                    <td class="text-center">{{ $cliente->tipo ?? 'PF' }}</td>
                    <td class="text-center"><strong>{{ $cliente->qtd_processos_ativos }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        Mayer & Albânez Advogados | Sistema RESULTADOS! | {{ $data['gerado_em'] }}
    </div>
</body>
</html>
