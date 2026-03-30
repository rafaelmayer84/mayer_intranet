<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Proposta de Honorários — {{ $proposta->nome_proponente }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4; margin: 22mm 20mm 18mm 20mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; font-size: 9.5pt; line-height: 1.75; color: #222; background: #fff; }

        .header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 14px; border-bottom: 2.5px solid #385776; margin-bottom: 4px; }
        .header-logo img { height: 80px; width: auto; display: block; }
        .header-right { text-align: right; font-size: 7.5pt; color: #555; line-height: 2; }
        .header-right .phone { font-weight: 700; color: #1B334A; font-size: 8.5pt; }
        .accent { height: 1.5px; background: linear-gradient(90deg, #C4A35A 30%, transparent); margin-bottom: 20px; }

        .data-local { text-align: right; font-size: 9pt; color: #666; margin-bottom: 16px; }

        .dest { margin-bottom: 4px; }
        .dest-label { font-size: 7pt; color: #aaa; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600; }
        .dest-nome { font-size: 11.5pt; font-weight: 700; color: #1B334A; margin-top: 1px; }
        .dest-doc { font-size: 8pt; color: #888; }

        .ref { font-size: 9.5pt; font-weight: 600; color: #1B334A; margin: 14px 0 20px; padding: 9px 14px; background: #f4f6f9; border-left: 3px solid #385776; border-radius: 0 4px 4px 0; }

        .st { font-size: 9pt; font-weight: 700; color: #385776; margin: 20px 0 7px; text-transform: uppercase; letter-spacing: 1.5px; padding-bottom: 5px; border-bottom: 1px solid #dde3ea; position: relative; }
        .st::after { content: ''; position: absolute; bottom: -1px; left: 0; width: 45px; height: 2px; background: #C4A35A; }
        .sp { text-align: justify; margin-bottom: 5px; font-size: 9.5pt; color: #2a2a2a; }

        .hbox { background: linear-gradient(135deg, #1B334A, #385776); color: #fff; border-radius: 6px; padding: 16px 20px; margin: 10px 0 14px; }
        .hbox .hv { font-size: 13pt; font-weight: 700; }
        .hbox .hd { font-size: 8.5pt; opacity: .85; margin-top: 4px; line-height: 1.6; }

        table.tf { width: 100%; border-collapse: collapse; margin: 8px 0 14px; font-size: 8.5pt; }
        table.tf th { background: #1B334A; color: #fff; padding: 6px 10px; text-align: left; font-size: 7pt; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        table.tf td { padding: 6px 10px; border-bottom: 1px solid #e8ecf1; }
        table.tf tr:nth-child(even) { background: #f8f9fb; }
        table.tf .sub { background: #eef2f7; }
        table.tf .sub td { font-weight: 700; color: #1B334A; }

        .sigs { display: flex; justify-content: space-between; margin-top: 40px; page-break-inside: avoid; }
        .sig { text-align: center; width: 42%; }
        .sig-line { border-top: 2px solid #1B334A; padding-top: 6px; font-size: 9.5pt; font-weight: 700; color: #1B334A; }
        .sig-info { font-size: 7.5pt; color: #888; margin-top: 2px; }

        .footer { margin-top: 32px; padding-top: 10px; border-top: 2.5px solid #385776; text-align: center; }
        .footer-addr { font-size: 7.5pt; color: #777; margin-bottom: 3px; }
        .footer-cities { font-size: 7pt; letter-spacing: 4px; color: #aaa; text-transform: uppercase; font-weight: 600; }
        .footer-cities span { margin: 0 10px; }

        .btn-print { position: fixed; top: 12px; right: 16px; background: linear-gradient(135deg,#1B334A,#385776); color: #fff; border: none; padding: 10px 24px; border-radius: 8px; cursor: pointer; font-size: 9pt; font-family: 'Montserrat', sans-serif; font-weight: 600; z-index: 9999; box-shadow: 0 3px 10px rgba(27,51,74,.3); }
        @media print {
            .btn-print { display: none !important; }
            .hbox, table.tf th, .ref, table.tf .sub { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<button class="btn-print" onclick="window.print()">Imprimir / Salvar PDF</button>

<div class="header">
    <div class="header-logo">
        <img src="{{ asset('img/timbrado-mayer.png') }}?v=2" alt="Mayer Sociedade de Advogados">
    </div>
    <div class="header-right">
        <div class="phone">(47) 3842-1050</div>
        @mayeralbanez<br>
        www.mayeradvogados.adv.br<br>
        contato@mayeradvogados.adv.br
    </div>
</div>
<div class="accent"></div>

<div class="data-local">{{ $dataFormatada }}</div>

<div class="dest">
    <div class="dest-label">Destinatário</div>
    <div class="dest-nome">{{ $proposta->nome_proponente }}</div>
    @if($proposta->documento_proponente)
        <div class="dest-doc">{{ $proposta->tipo_pessoa === 'PJ' ? 'CNPJ' : 'CPF' }}: {{ $proposta->documento_proponente }}</div>
    @endif
</div>

<div class="ref">Ref.: Proposta de Honorários — {{ $proposta->area_direito }}{{ $proposta->tipo_acao ? ' / ' . $proposta->tipo_acao : '' }}</div>

@php
    $texto = $proposta->texto_proposta_cliente;
    $secoes = is_array($texto) ? $texto : (json_decode($texto, true) ?? []);
@endphp

@if(!empty($secoes['saudacao']))<div class="sp">{!! nl2br(e($secoes['saudacao'])) !!}</div>@endif

@if(!empty($secoes['contexto_demanda']))<div class="st">Contexto da Demanda</div><div class="sp">{!! nl2br(e($secoes['contexto_demanda'])) !!}</div>@endif

@if(!empty($secoes['diagnostico']))<div class="st">Diagnóstico Preliminar</div><div class="sp">{!! nl2br(e($secoes['diagnostico'])) !!}</div>@endif

@if(!empty($secoes['escopo_servicos']))<div class="st">Escopo dos Serviços</div><div class="sp">{!! nl2br(e($secoes['escopo_servicos'])) !!}</div>@endif

@if(!empty($secoes['fases']) && is_array($secoes['fases']))
<div class="st">Fases e Atividades</div>
<table class="tf"><thead><tr><th style="width:26%;">Fase</th><th>Descrição</th></tr></thead><tbody>
@foreach($secoes['fases'] as $f)<tr><td style="font-weight:600;color:#1B334A;">{{ $f['nome'] ?? '' }}</td><td>{{ $f['descricao'] ?? '' }}</td></tr>@endforeach
</tbody></table>
@endif

@if(!empty($secoes['fases_horas']) && is_array($secoes['fases_horas']))
<div class="st">Atividades e Horas Estimadas</div>
<p class="sp" style="font-size:7.5pt;color:#999;">Horas absorvidas pelo pró-labore. Extrapolações atípicas comunicadas previamente.</p>
@foreach($secoes['fases_horas'] as $fh)
<p style="font-weight:700;color:#1B334A;font-size:8.5pt;margin:10px 0 3px;">{{ $fh['nome'] ?? '' }}</p>
<table class="tf"><thead><tr><th style="width:26%;">Atividade</th><th>Descrição</th><th style="width:7%;text-align:center;">Mín</th><th style="width:7%;text-align:center;">Máx</th></tr></thead><tbody>
@foreach(($fh['atividades'] ?? []) as $a)<tr><td style="font-weight:600;color:#1B334A;">{{ $a['atividade'] ?? '' }}</td><td>{{ $a['descricao'] ?? '' }}</td><td style="text-align:center;">{{ $a['horas_min'] ?? '-' }}</td><td style="text-align:center;">{{ $a['horas_max'] ?? '-' }}</td></tr>@endforeach
<tr class="sub"><td colspan="2" style="text-align:right;">Subtotal</td><td style="text-align:center;">{{ $fh['subtotal_min'] ?? '-' }}</td><td style="text-align:center;">{{ $fh['subtotal_max'] ?? '-' }}</td></tr>
</tbody></table>
@endforeach
@php $tMin = collect($secoes['fases_horas'])->sum('subtotal_min'); $tMax = collect($secoes['fases_horas'])->sum('subtotal_max'); @endphp
<div class="hbox"><div style="font-size:9.5pt;font-weight:700;">Total: {{ $tMin }}–{{ $tMax }} horas</div></div>
@endif

@if(!empty($secoes['estrategia']))<div class="st">Estratégia Jurídica</div><div class="sp">{!! nl2br(e($secoes['estrategia'])) !!}</div>@endif

@if(!empty($secoes['honorarios']))
<div class="st">Honorários</div>
<div class="hbox">
    <div class="hv">{{ $secoes['honorarios']['descricao_valor'] ?? '' }}</div>
    @if(!empty($secoes['honorarios']['forma_pagamento']))<div class="hd">{{ $secoes['honorarios']['forma_pagamento'] }}</div>@endif
    @if(!empty($secoes['honorarios']['observacao']))<div class="hd" style="margin-top:5px;opacity:.7;">{{ $secoes['honorarios']['observacao'] }}</div>@endif
</div>
@endif

@if(!empty($secoes['honorarios_exito']))<div class="st">Honorários de Êxito</div><div class="sp">{!! nl2br(e($secoes['honorarios_exito'])) !!}</div>@endif

@if(!empty($secoes['despesas']))<div class="st">Despesas Processuais</div><div class="sp">{!! nl2br(e($secoes['despesas'])) !!}</div>@endif

@if(!empty($secoes['diferenciais']))<div class="st">Por que o Escritório Mayer</div><div class="sp">{!! nl2br(e($secoes['diferenciais'])) !!}</div>@endif

@if(!empty($secoes['vigencia']))<div class="st">Vigência e Condições</div><div class="sp">{!! nl2br(e($secoes['vigencia'])) !!}</div>@endif

@if(!empty($secoes['encerramento']))<div class="sp" style="margin-top:16px;">{!! nl2br(e($secoes['encerramento'])) !!}</div>@endif

<div class="sigs">
    <div class="sig"><div class="sig-line">Rafael Mayer</div><div class="sig-info">Sócio Proprietário<br>Mayer Sociedade de Advogados · OAB/SC 2097</div></div>
    <div class="sig"><div class="sig-line">{{ $proposta->nome_proponente }}</div><div class="sig-info">Proponente</div></div>
</div>

<div class="footer">
    <div class="footer-addr">Av. Marcos Konder, 1207, sala 062 — Centro, Itajaí/SC — CEP 88301-303</div>
    @php
        $hashSource = ($proposta->texto_proposta_cliente ?? '') . '|' . $proposta->id . '|' . $proposta->updated_at->toIso8601String();
        $hashRaw = hash('sha256', $hashSource);
        $hashDisplay = 'MAYER-' . strtoupper(substr($hashRaw,0,4) . '-' . substr($hashRaw,4,4) . '-' . substr($hashRaw,8,4) . '-' . substr($hashRaw,12,4));
    @endphp
    <div style="margin-top:12px;padding:10px 16px;background:#f8f9fb;border:1px solid #e8ecf1;border-radius:6px;display:inline-block;">
        <div style="font-size:6pt;color:#aaa;text-transform:uppercase;letter-spacing:2px;font-weight:600;margin-bottom:3px;">Certificação Digital de Autenticidade</div>
        <div style="font-family:'Courier New',monospace;font-size:9pt;color:#1B334A;font-weight:700;letter-spacing:1.5px;">{{ $hashDisplay }}</div>
        <div style="font-size:5.5pt;color:#bbb;margin-top:3px;">Documento gerado eletronicamente em {{ $proposta->updated_at->format('d/m/Y H:i') }} · Proposta #{{ $proposta->id }} · Mayer Sociedade de Advogados · OAB/SC 2097</div>
    </div>
</div>
</body>
</html>
