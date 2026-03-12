<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Proposta de Honorários — {{ $proposta->nome_proponente }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4; margin: 22mm 20mm 20mm 20mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', 'Arial', sans-serif;
            font-size: 10pt;
            line-height: 1.7;
            color: #222;
            background: #fff;
        }

        /* ====== HEADER ====== */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 14px;
            border-bottom: 2.5px solid #385776;
            margin-bottom: 6px;
        }
        .header-logo img {
            height: 62px;
            width: auto;
        }
        .header-right {
            text-align: right;
            font-size: 7.5pt;
            color: #555;
            line-height: 1.9;
        }
        .header-right .phone {
            font-weight: 600;
            color: #1B334A;
            font-size: 8pt;
        }

        /* Linha accent dourada fina abaixo do header */
        .accent-line {
            height: 1.5px;
            background: linear-gradient(90deg, #C4A35A, transparent);
            margin-bottom: 22px;
        }

        /* ====== DATA ====== */
        .data-local {
            text-align: right;
            font-size: 9pt;
            color: #666;
            margin-bottom: 18px;
        }

        /* ====== DESTINATARIO ====== */
        .destinatario { margin-bottom: 4px; }
        .destinatario .label {
            font-size: 7pt;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 600;
        }
        .destinatario .nome {
            font-size: 11.5pt;
            font-weight: 700;
            color: #1B334A;
            margin-top: 1px;
        }
        .destinatario .doc { font-size: 8pt; color: #888; }

        /* ====== REF ====== */
        .ref-line {
            font-size: 9.5pt;
            font-weight: 600;
            color: #1B334A;
            margin: 14px 0 22px 0;
            padding: 9px 14px;
            background: #f4f6f9;
            border-left: 3px solid #385776;
            border-radius: 0 4px 4px 0;
        }

        /* ====== SECOES ====== */
        .secao-titulo {
            font-size: 9pt;
            font-weight: 700;
            color: #385776;
            margin: 22px 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding-bottom: 5px;
            border-bottom: 1px solid #dde3ea;
            position: relative;
        }
        .secao-titulo::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 45px;
            height: 2px;
            background: #C4A35A;
        }
        .secao-texto {
            text-align: justify;
            margin-bottom: 6px;
            font-size: 9.5pt;
            color: #2a2a2a;
        }

        /* ====== HONORARIOS BOX ====== */
        .honorarios-box {
            background: linear-gradient(135deg, #1B334A 0%, #385776 100%);
            color: #fff;
            border-radius: 6px;
            padding: 16px 20px;
            margin: 12px 0 16px 0;
        }
        .honorarios-box .valor-destaque { font-size: 13pt; font-weight: 700; }
        .honorarios-box .valor-detalhe { font-size: 8.5pt; opacity: 0.85; margin-top: 5px; line-height: 1.6; }

        /* ====== TABELA ====== */
        table.tabela-fases { width: 100%; border-collapse: collapse; margin: 10px 0 16px 0; font-size: 8.5pt; }
        table.tabela-fases th {
            background: #1B334A; color: #fff; padding: 7px 10px;
            text-align: left; font-size: 7pt; text-transform: uppercase;
            letter-spacing: 1px; font-weight: 600;
        }
        table.tabela-fases td { padding: 7px 10px; border-bottom: 1px solid #e8ecf1; }
        table.tabela-fases tr:nth-child(even) { background: #f8f9fb; }

        /* ====== ASSINATURAS ====== */
        .assinaturas {
            display: flex; justify-content: space-between;
            margin-top: 44px; page-break-inside: avoid;
        }
        .assinatura-box { text-align: center; width: 42%; }
        .assinatura-linha {
            border-top: 2px solid #1B334A; padding-top: 7px;
            font-size: 9.5pt; font-weight: 700; color: #1B334A;
        }
        .assinatura-cargo { font-size: 7.5pt; color: #888; margin-top: 2px; }

        /* ====== FOOTER ====== */
        .footer {
            margin-top: 36px; padding-top: 12px;
            border-top: 2.5px solid #385776;
            text-align: center;
        }
        .footer-addr {
            font-size: 7.5pt; color: #777; margin-bottom: 4px;
        }
        .footer-cidades {
            font-size: 7pt; letter-spacing: 4px; color: #aaa;
            text-transform: uppercase; font-weight: 600;
        }
        .footer-cidades span { margin: 0 10px; }

        /* ====== PRINT ====== */
        .btn-print {
            position: fixed; top: 15px; right: 20px;
            background: linear-gradient(135deg, #1B334A, #385776);
            color: #fff; border: none; padding: 12px 28px; border-radius: 8px;
            cursor: pointer; font-size: 10pt; font-family: 'Montserrat', sans-serif;
            font-weight: 600; z-index: 9999;
            box-shadow: 0 4px 12px rgba(27, 51, 74, 0.3);
        }
        .btn-print:hover { background: #1B334A; }
        @media print {
            .btn-print { display: none !important; }
            body { font-size: 9pt; }
            .honorarios-box, table.tabela-fases th, .ref-line {
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print()">Imprimir / Salvar PDF</button>

    <!-- HEADER -->
    <div class="header">
        <div class="header-logo">
            <img src="{{ asset('img/timbrado-mayer.png') }}" alt="Mayer Albanez Sociedade de Advogados">
        </div>
        <div class="header-right">
            <div class="phone">(47) 3842-1050</div>
            @mayeralbanez<br>
            www.mayeradvogados.adv.br<br>
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

    <!-- CONTEUDO IA -->
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
            <thead><tr><th style="width:28%;">Fase</th><th>Descrição</th></tr></thead>
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
        <p class="secao-texto" style="font-size:8pt;color:#999;">Horas absorvidas pelo pró-labore contratado. Extrapolações atípicas serão previamente comunicadas.</p>
        @foreach($secoes['fases_horas'] as $faseH)
            <p style="font-weight:700;color:#1B334A;font-size:9pt;margin:12px 0 4px 0;">{{ $faseH['nome'] ?? '' }}</p>
            <table class="tabela-fases">
                <thead><tr><th style="width:28%;">Atividade</th><th>Descrição</th><th style="width:8%;text-align:center;">Mín</th><th style="width:8%;text-align:center;">Máx</th></tr></thead>
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
        @php $totalMin = collect($secoes['fases_horas'])->sum('subtotal_min'); $totalMax = collect($secoes['fases_horas'])->sum('subtotal_max'); @endphp
        <div class="honorarios-box" style="margin-top:6px;">
            <div style="font-size:9.5pt;font-weight:700;">Total estimado: {{ $totalMin }} a {{ $totalMax }} horas</div>
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
                <div class="valor-detalhe" style="margin-top:6px;opacity:0.75;">{{ $secoes['honorarios']['observacao'] }}</div>
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
        <div class="secao-texto" style="margin-top:18px;">{!! nl2br(e($secoes['encerramento'])) !!}</div>
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
        <div class="footer-addr">Av. Marcos Konder, 1207, sala 062 — Centro, Itajaí/SC — CEP 88301-303</div>
        <div class="footer-cidades">
            <span>Itajaí</span><span>Florianópolis</span><span>São Paulo</span>
        </div>
    </div>
</body>
</html>
