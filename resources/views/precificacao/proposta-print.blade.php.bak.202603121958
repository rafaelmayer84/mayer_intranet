<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Proposta de Honorários — {{ $proposta->nome_proponente }}</title>
    <style>
        @page {
            size: A4;
            margin: 25mm 20mm 20mm 20mm;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #1a1a1a;
        }

        /* ====== HEADER ====== */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #C4A35A;
            padding-bottom: 12px;
            margin-bottom: 28px;
        }
        .header-left { font-family: 'Arial', sans-serif; }
        .header-left .firm-name {
            font-size: 16pt;
            font-weight: bold;
            letter-spacing: 2px;
            color: #1B334A;
        }
        .header-left .firm-sub {
            font-size: 8pt;
            letter-spacing: 3px;
            color: #666;
            text-transform: uppercase;
        }
        .header-left .firm-oab {
            font-size: 8pt;
            color: #999;
            margin-top: 2px;
        }
        .header-right {
            text-align: right;
            font-size: 8pt;
            color: #666;
            line-height: 1.6;
        }

        /* ====== CORPO ====== */
        .data-local {
            text-align: right;
            font-size: 10pt;
            color: #444;
            margin-bottom: 20px;
        }
        .destinatario {
            margin-bottom: 8px;
            font-size: 10.5pt;
        }
        .destinatario strong {
            color: #1B334A;
        }
        .ref-line {
            font-size: 10.5pt;
            font-weight: bold;
            color: #1B334A;
            margin: 16px 0 20px 0;
            padding-bottom: 6px;
            border-bottom: 1px solid #ddd;
        }

        /* Seções */
        .secao-titulo {
            font-size: 11pt;
            font-weight: bold;
            color: #1B334A;
            margin: 22px 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .secao-texto {
            text-align: justify;
            margin-bottom: 10px;
        }

        /* Tabela de honorários */
        .honorarios-box {
            background: #f8f9fb;
            border: 1px solid #dde2e8;
            border-radius: 4px;
            padding: 16px 20px;
            margin: 12px 0 16px 0;
        }
        .honorarios-box .valor-destaque {
            font-size: 14pt;
            font-weight: bold;
            color: #1B334A;
        }
        .honorarios-box .valor-detalhe {
            font-size: 9.5pt;
            color: #555;
            margin-top: 4px;
        }

        /* Tabela genérica */
        table.tabela-fases {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 16px 0;
            font-size: 10pt;
        }
        table.tabela-fases th {
            background: #1B334A;
            color: #fff;
            padding: 6px 10px;
            text-align: left;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table.tabela-fases td {
            padding: 6px 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        table.tabela-fases tr:nth-child(even) {
            background: #f9fafb;
        }

        /* Assinaturas */
        .assinaturas {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            page-break-inside: avoid;
        }
        .assinatura-box {
            text-align: center;
            width: 42%;
        }
        .assinatura-linha {
            border-top: 1px solid #333;
            padding-top: 6px;
            font-size: 10pt;
            font-weight: bold;
            color: #1B334A;
        }
        .assinatura-cargo {
            font-size: 8.5pt;
            color: #666;
        }

        /* Footer */
        .footer-cidades {
            text-align: center;
            margin-top: 40px;
            padding-top: 10px;
            border-top: 1px solid #C4A35A;
            font-family: 'Arial', sans-serif;
            font-size: 8pt;
            letter-spacing: 4px;
            color: #999;
            text-transform: uppercase;
        }
        .footer-cidades span { margin: 0 15px; }

        /* Botão imprimir */
        .btn-print {
            position: fixed;
            top: 15px;
            right: 20px;
            background: #385776;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 10pt;
            font-family: Arial, sans-serif;
            z-index: 9999;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
        .btn-print:hover { background: #1B334A; }

        @media print {
            .btn-print { display: none !important; }
            body { font-size: 10.5pt; }
        }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print()">Imprimir / Salvar PDF</button>

    <!-- HEADER -->
    <div class="header">
        <div class="header-left">
            <div class="firm-name">MAYER</div>
            <div class="firm-sub">Sociedade de Advogados</div>
            <div class="firm-oab">OAB/SC 2097</div>
        </div>
        <div class="header-right">
            Rua Samuel Heusi, 284 — Centro<br>
            Itajaí/SC — CEP 88301-040<br>
            (47) 3842-1050<br>
            contato@mayeradvogados.adv.br
        </div>
    </div>

    <!-- DATA -->
    <div class="data-local">
        {{ $dataFormatada }}
    </div>

    <!-- DESTINATÁRIO -->
    <div class="destinatario">
        A<br>
        <strong>{{ $proposta->nome_proponente }}</strong>
        @if($proposta->documento_proponente)
            <br><span style="font-size:9.5pt;color:#666;">{{ $proposta->tipo_pessoa === 'PJ' ? 'CNPJ' : 'CPF' }}: {{ $proposta->documento_proponente }}</span>
        @endif
    </div>

    <!-- REF -->
    <div class="ref-line">
        Ref.: Proposta de Honorários — {{ $proposta->area_direito }}{{ $proposta->tipo_acao ? ' / ' . $proposta->tipo_acao : '' }}
    </div>

    <!-- CONTEÚDO GERADO PELA IA -->
    @php
        $texto = $proposta->texto_proposta_cliente;
        $secoes = is_array($texto) ? $texto : (json_decode($texto, true) ?? []);
    @endphp

    @if(!empty($secoes['saudacao']))
        <div class="secao-texto">{!! nl2br(e($secoes['saudacao'])) !!}</div>
    @endif

    @if(!empty($secoes['contexto_demanda']))
        <div class="secao-titulo">Contexto da Demanda</div>
        <div class="secao-texto">{!! nl2br(e($secoes['contexto_demanda'])) !!}</div>
    @endif

    @if(!empty($secoes['diagnostico']))
        <div class="secao-titulo">Diagnóstico Preliminar</div>
        <div class="secao-texto">{!! nl2br(e($secoes['diagnostico'])) !!}</div>
    @endif

    @if(!empty($secoes['escopo_servicos']))
        <div class="secao-titulo">Escopo dos Serviços</div>
        <div class="secao-texto">{!! nl2br(e($secoes['escopo_servicos'])) !!}</div>
    @endif

    @if(!empty($secoes['fases']) && is_array($secoes['fases']))
        <div class="secao-titulo">Fases e Atividades</div>
        <table class="tabela-fases">
            <thead>
                <tr>
                    <th style="width:25%;">Fase</th>
                    <th>Descrição</th>
                </tr>
            </thead>
            <tbody>
                @foreach($secoes['fases'] as $fase)
                    <tr>
                        <td style="font-weight:bold;color:#1B334A;">{{ $fase['nome'] ?? '' }}</td>
                        <td>{{ $fase['descricao'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(!empty($secoes['estrategia']))
        <div class="secao-titulo">Estratégia Jurídica</div>
        <div class="secao-texto">{!! nl2br(e($secoes['estrategia'])) !!}</div>
    @endif

    @if(!empty($secoes['honorarios']))
        <div class="secao-titulo">Honorários</div>
        <div class="honorarios-box">
            <div class="valor-destaque">{{ $secoes['honorarios']['descricao_valor'] ?? '' }}</div>
            @if(!empty($secoes['honorarios']['forma_pagamento']))
                <div class="valor-detalhe">{{ $secoes['honorarios']['forma_pagamento'] }}</div>
            @endif
            @if(!empty($secoes['honorarios']['observacao']))
                <div class="valor-detalhe" style="margin-top:8px;">{{ $secoes['honorarios']['observacao'] }}</div>
            @endif
        </div>
    @endif

    @if(!empty($secoes['honorarios_exito']))
        <div class="secao-titulo">Honorários de Êxito</div>
        <div class="secao-texto">{!! nl2br(e($secoes['honorarios_exito'])) !!}</div>
    @endif

    @if(!empty($secoes['despesas']))
        <div class="secao-titulo">Despesas Processuais</div>
        <div class="secao-texto">{!! nl2br(e($secoes['despesas'])) !!}</div>
    @endif

    @if(!empty($secoes['diferenciais']))
        <div class="secao-titulo">Por que o Escritório Mayer</div>
        <div class="secao-texto">{!! nl2br(e($secoes['diferenciais'])) !!}</div>
    @endif

    @if(!empty($secoes['vigencia']))
        <div class="secao-titulo">Vigência e Condições</div>
        <div class="secao-texto">{!! nl2br(e($secoes['vigencia'])) !!}</div>
    @endif

    @if(!empty($secoes['encerramento']))
        <div class="secao-texto" style="margin-top:16px;">{!! nl2br(e($secoes['encerramento'])) !!}</div>
    @endif

    <!-- ASSINATURAS -->
    <div class="assinaturas">
        <div class="assinatura-box">
            <div class="assinatura-linha">{{ Auth::user()->name ?? 'Advogado Responsável' }}</div>
            <div class="assinatura-cargo">{{ Auth::user()->cargo ?? 'Advogado(a)' }}<br>Mayer Sociedade de Advogados</div>
        </div>
        <div class="assinatura-box">
            <div class="assinatura-linha">{{ $proposta->nome_proponente }}</div>
            <div class="assinatura-cargo">Proponente</div>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer-cidades">
        <span>ITAJAÍ</span>
        <span>FLORIANÓPOLIS</span>
        <span>SÃO PAULO</span>
    </div>
</body>
</html>
