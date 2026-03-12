<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Proposta de Honorários — {{ $proposta->nome_proponente }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4;
            margin: 20mm 18mm 18mm 18mm;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', 'Arial', sans-serif;
            font-size: 10pt;
            line-height: 1.7;
            color: #1a1a1a;
            background: #fff;
            position: relative;
        }

        /* ====== MARCA D'AGUA (icone M estilizado) ====== */
        body::before {
            content: 'M';
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-family: 'Montserrat', sans-serif;
            font-size: 400pt;
            font-weight: 700;
            color: rgba(56, 87, 118, 0.03);
            pointer-events: none;
            z-index: 0;
        }

        /* ====== HEADER ====== */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 16px;
            margin-bottom: 28px;
            border-bottom: 3px solid #385776;
            position: relative;
            z-index: 1;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-logo {
            display: flex;
            flex-direction: column;
        }
        .header-logo .firm-name {
            font-size: 22pt;
            font-weight: 700;
            color: #1B334A;
            letter-spacing: 1px;
            line-height: 1.1;
        }
        .header-logo .firm-sub {
            font-size: 7pt;
            font-weight: 500;
            letter-spacing: 4px;
            color: #385776;
            text-transform: uppercase;
            margin-top: 2px;
        }
        .header-logo .firm-oab {
            font-size: 7.5pt;
            color: #999;
            margin-top: 3px;
            font-weight: 400;
        }
        .header-right {
            text-align: right;
            font-size: 7.5pt;
            color: #666;
            line-height: 1.8;
            font-weight: 400;
        }
        .header-right strong {
            color: #1B334A;
            font-weight: 600;
        }

        /* ====== LINHA DOURADA ACCENT ====== */
        .accent-line {
            height: 2px;
            background: linear-gradient(90deg, #C4A35A 0%, #385776 100%);
            margin-bottom: 24px;
            border-radius: 2px;
        }

        /* ====== DATA/LOCAL ====== */
        .data-local {
            text-align: right;
            font-size: 9.5pt;
            color: #555;
            margin-bottom: 20px;
            font-weight: 400;
        }

        /* ====== DESTINATARIO ====== */
        .destinatario {
            margin-bottom: 6px;
            font-size: 10pt;
        }
        .destinatario .label {
            font-size: 8pt;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        .destinatario .nome {
            font-size: 12pt;
            font-weight: 700;
            color: #1B334A;
            margin-top: 2px;
        }
        .destinatario .doc {
            font-size: 8.5pt;
            color: #777;
        }

        /* ====== REF ====== */
        .ref-line {
            font-size: 10pt;
            font-weight: 600;
            color: #1B334A;
            margin: 18px 0 24px 0;
            padding: 10px 16px;
            background: linear-gradient(135deg, #f0f4f8 0%, #e8edf3 100%);
            border-left: 4px solid #385776;
            border-radius: 0 6px 6px 0;
        }

        /* ====== SECOES ====== */
        .secao-titulo {
            font-size: 10pt;
            font-weight: 700;
            color: #385776;
            margin: 26px 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding-bottom: 6px;
            border-bottom: 1.5px solid #e0e6ed;
            position: relative;
        }
        .secao-titulo::after {
            content: '';
            position: absolute;
            bottom: -1.5px;
            left: 0;
            width: 60px;
            height: 1.5px;
            background: #C4A35A;
        }
        .secao-texto {
            text-align: justify;
            margin-bottom: 8px;
            font-weight: 400;
            color: #2a2a2a;
        }

        /* ====== HONORARIOS BOX ====== */
        .honorarios-box {
            background: linear-gradient(135deg, #1B334A 0%, #385776 100%);
            color: #fff;
            border-radius: 8px;
            padding: 20px 24px;
            margin: 14px 0 18px 0;
            position: relative;
            overflow: hidden;
        }
        .honorarios-box::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 100px;
            height: 100px;
            background: rgba(196, 163, 90, 0.15);
            border-radius: 50%;
        }
        .honorarios-box .valor-destaque {
            font-size: 15pt;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .honorarios-box .valor-detalhe {
            font-size: 9pt;
            opacity: 0.85;
            margin-top: 6px;
            font-weight: 400;
            line-height: 1.6;
        }

        /* ====== TABELA ====== */
        table.tabela-fases {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 18px 0;
            font-size: 9pt;
        }
        table.tabela-fases th {
            background: #1B334A;
            color: #fff;
            padding: 8px 12px;
            text-align: left;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        table.tabela-fases td {
            padding: 8px 12px;
            border-bottom: 1px solid #e8ecf1;
            color: #333;
        }
        table.tabela-fases tr:nth-child(even) {
            background: #f7f9fb;
        }
        table.tabela-fases tr:hover {
            background: #eef2f7;
        }

        /* ====== ASSINATURAS ====== */
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
            border-top: 2px solid #1B334A;
            padding-top: 8px;
            font-size: 10pt;
            font-weight: 700;
            color: #1B334A;
        }
        .assinatura-cargo {
            font-size: 8pt;
            color: #777;
            font-weight: 400;
            margin-top: 2px;
        }

        /* ====== FOOTER ====== */
        .footer {
            margin-top: 40px;
            padding-top: 14px;
            border-top: 3px solid #385776;
            text-align: center;
        }
        .footer-cidades {
            font-size: 7.5pt;
            letter-spacing: 5px;
            color: #999;
            text-transform: uppercase;
            font-weight: 600;
        }
        .footer-cidades span { margin: 0 12px; }
        .footer-contato {
            font-size: 7pt;
            color: #bbb;
            margin-top: 6px;
            font-weight: 400;
        }

        /* ====== BOTAO IMPRIMIR ====== */
        .btn-print {
            position: fixed;
            top: 15px;
            right: 20px;
            background: linear-gradient(135deg, #1B334A, #385776);
            color: #fff;
            border: none;
            padding: 12px 28px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 10pt;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(27, 51, 74, 0.3);
            letter-spacing: 0.5px;
        }
        .btn-print:hover { background: #1B334A; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(27, 51, 74, 0.4); }

        @media print {
            .btn-print { display: none !important; }
            body { font-size: 9.5pt; }
            body::before { position: absolute; }
            .honorarios-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            table.tabela-fases th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .ref-line { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print()">Imprimir / Salvar PDF</button>

    <!-- HEADER -->
    <div class="header">
        <div class="header-left">
            <div class="header-logo">
                <div class="firm-name">MAYER</div>
                <div class="firm-sub">Sociedade de Advogados</div>
                <div class="firm-oab">OAB/SC 2097</div>
            </div>
        </div>
        <div class="header-right">
            Rua Samuel Heusi, 284 — Centro<br>
            Itajaí/SC — CEP 88301-040<br>
            <strong>(47) 3842-1050</strong><br>
            contato@mayeradvogados.adv.br
        </div>
    </div>

    <div class="accent-line"></div>

    <!-- DATA -->
    <div class="data-local">{{ $dataFormatada }}</div>

    <!-- DESTINATARIO -->
    <div class="destinatario">
        <div class="label">Destinatário</div>
        <div class="nome">{{ $proposta->nome_proponente }}</div>
        @if($proposta->documento_proponente)
            <div class="doc">{{ $proposta->tipo_pessoa === 'PJ' ? 'CNPJ' : 'CPF' }}: {{ $proposta->documento_proponente }}</div>
        @endif
    </div>

    <!-- REF -->
    <div class="ref-line">
        Ref.: Proposta de Honorários — {{ $proposta->area_direito }}{{ $proposta->tipo_acao ? ' / ' . $proposta->tipo_acao : '' }}
    </div>

    <!-- CONTEUDO GERADO PELA IA -->
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
                    <th style="width:28%;">Fase</th>
                    <th>Descrição</th>
                </tr>
            </thead>
            <tbody>
                @foreach($secoes['fases'] as $fase)
                    <tr>
                        <td style="font-weight:600;color:#1B334A;">{{ $fase['nome'] ?? '' }}</td>
                        <td>{{ $fase['descricao'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(!empty($secoes['fases_horas']) && is_array($secoes['fases_horas']))
        <div class="secao-titulo">Atividades e Horas Estimadas</div>
        <p class="secao-texto" style="font-size:8.5pt;color:#777;margin-bottom:10px;">As horas representam a estimativa de dedicação da equipe jurídica, absorvidas pelo pró-labore contratado.</p>
        @foreach($secoes['fases_horas'] as $faseH)
            <p style="font-weight:700;color:#1B334A;font-size:9.5pt;margin:14px 0 6px 0;">{{ $faseH['nome'] ?? '' }}</p>
            <table class="tabela-fases">
                <thead>
                    <tr>
                        <th style="width:28%;">Atividade</th>
                        <th>Descrição</th>
                        <th style="width:9%;text-align:center;">Hrs Mín</th>
                        <th style="width:9%;text-align:center;">Hrs Máx</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(($faseH['atividades'] ?? []) as $ativ)
                    <tr>
                        <td style="font-weight:600;color:#1B334A;">{{ $ativ['atividade'] ?? '' }}</td>
                        <td>{{ $ativ['descricao'] ?? '' }}</td>
                        <td style="text-align:center;">{{ $ativ['horas_min'] ?? '-' }}</td>
                        <td style="text-align:center;">{{ $ativ['horas_max'] ?? '-' }}</td>
                    </tr>
                    @endforeach
                    <tr style="background:#eef2f7;">
                        <td colspan="2" style="font-weight:700;text-align:right;color:#1B334A;">Subtotal</td>
                        <td style="text-align:center;font-weight:700;">{{ $faseH['subtotal_min'] ?? '-' }}</td>
                        <td style="text-align:center;font-weight:700;">{{ $faseH['subtotal_max'] ?? '-' }}</td>
                    </tr>
                </tbody>
            </table>
        @endforeach
        @php
            $totalMin = collect($secoes['fases_horas'])->sum('subtotal_min');
            $totalMax = collect($secoes['fases_horas'])->sum('subtotal_max');
        @endphp
        <div class="honorarios-box" style="background:linear-gradient(135deg,#385776,#1B334A);margin-top:8px;">
            <div style="font-size:10pt;font-weight:700;">Total estimado: {{ $totalMin }} a {{ $totalMax }} horas de trabalho</div>
        </div>
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
                <div class="valor-detalhe" style="margin-top:8px;opacity:0.75;">{{ $secoes['honorarios']['observacao'] }}</div>
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
        <div class="secao-texto" style="margin-top:20px;">{!! nl2br(e($secoes['encerramento'])) !!}</div>
    @endif

    <!-- ASSINATURAS -->
    <div class="assinaturas">
        <div class="assinatura-box">
            <div class="assinatura-linha">Rafael Mayer</div>
            <div class="assinatura-cargo">Sócio Proprietário<br>Mayer Sociedade de Advogados<br>OAB/SC 2097</div>
        </div>
        <div class="assinatura-box">
            <div class="assinatura-linha">{{ $proposta->nome_proponente }}</div>
            <div class="assinatura-cargo">Proponente</div>
        </div>
    </div>

    <!-- FOOTER -->
    <div class="footer">
        <div class="footer-cidades">
            <span>Itajaí</span>
            <span>Florianópolis</span>
            <span>São Paulo</span>
        </div>
        <div class="footer-contato">
            (47) 3842-1050 &nbsp;|&nbsp; contato@mayeradvogados.adv.br &nbsp;|&nbsp; www.mayeradvogados.adv.br
        </div>
    </div>
</body>
</html>
