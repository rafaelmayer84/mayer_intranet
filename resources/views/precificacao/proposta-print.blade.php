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
            margin: 0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', 'Arial', sans-serif;
            font-size: 10pt;
            line-height: 1.7;
            color: #1a1a1a;
            background: #fff;
        }

        /* ====== PAGINA COM TIMBRADO ====== */
        .page {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background-image: url('{{ asset("img/timbrado-mayer.png") }}');
            background-size: 210mm auto;
            background-repeat: no-repeat;
            background-position: top center;
        }
        .page-content {
            /* Margens para caber dentro do timbrado */
            /* Header do timbrado ocupa ~115px, footer ~60px */
            padding: 135px 60px 80px 60px;
        }

        /* ====== DATA/LOCAL ====== */
        .data-local {
            text-align: right;
            font-size: 9pt;
            color: #555;
            margin-bottom: 18px;
            font-weight: 400;
        }

        /* ====== DESTINATARIO ====== */
        .destinatario {
            margin-bottom: 6px;
            font-size: 10pt;
        }
        .destinatario .label {
            font-size: 7.5pt;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        .destinatario .nome {
            font-size: 11pt;
            font-weight: 700;
            color: #1B334A;
            margin-top: 2px;
        }
        .destinatario .doc {
            font-size: 8pt;
            color: #777;
        }

        /* ====== REF ====== */
        .ref-line {
            font-size: 9.5pt;
            font-weight: 600;
            color: #1B334A;
            margin: 16px 0 22px 0;
            padding: 8px 14px;
            background: rgba(56, 87, 118, 0.06);
            border-left: 3px solid #385776;
            border-radius: 0 4px 4px 0;
        }

        /* ====== SECOES ====== */
        .secao-titulo {
            font-size: 9.5pt;
            font-weight: 700;
            color: #385776;
            margin: 22px 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e0e6ed;
            position: relative;
        }
        .secao-titulo::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 50px;
            height: 1.5px;
            background: #C4A35A;
        }
        .secao-texto {
            text-align: justify;
            margin-bottom: 6px;
            font-weight: 400;
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
            position: relative;
            overflow: hidden;
        }
        .honorarios-box::before {
            content: '';
            position: absolute;
            top: -15px;
            right: -15px;
            width: 80px;
            height: 80px;
            background: rgba(196, 163, 90, 0.12);
            border-radius: 50%;
        }
        .honorarios-box .valor-destaque {
            font-size: 13pt;
            font-weight: 700;
        }
        .honorarios-box .valor-detalhe {
            font-size: 8.5pt;
            opacity: 0.85;
            margin-top: 5px;
            font-weight: 400;
            line-height: 1.6;
        }

        /* ====== TABELA ====== */
        table.tabela-fases {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 16px 0;
            font-size: 8.5pt;
        }
        table.tabela-fases th {
            background: #1B334A;
            color: #fff;
            padding: 6px 10px;
            text-align: left;
            font-size: 7pt;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        table.tabela-fases td {
            padding: 6px 10px;
            border-bottom: 1px solid #e8ecf1;
            color: #333;
        }
        table.tabela-fases tr:nth-child(even) {
            background: rgba(56, 87, 118, 0.03);
        }

        /* ====== ASSINATURAS ====== */
        .assinaturas {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .assinatura-box {
            text-align: center;
            width: 42%;
        }
        .assinatura-linha {
            border-top: 2px solid #1B334A;
            padding-top: 6px;
            font-size: 9.5pt;
            font-weight: 700;
            color: #1B334A;
        }
        .assinatura-cargo {
            font-size: 7.5pt;
            color: #777;
            font-weight: 400;
            margin-top: 2px;
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
        }
        .btn-print:hover { background: #1B334A; }

        @media print {
            .btn-print { display: none !important; }
            body { font-size: 9pt; }
            .page {
                width: 100%;
                min-height: auto;
                background-size: 100% auto;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .honorarios-box, table.tabela-fases th, .ref-line {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <button class="btn-print" onclick="window.print()">Imprimir / Salvar PDF</button>

    <div class="page">
        <div class="page-content">

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
                <p class="secao-texto" style="font-size:8pt;color:#888;">As horas representam estimativa de dedicação da equipe jurídica, absorvidas pelo pró-labore contratado.</p>
                @foreach($secoes['fases_horas'] as $faseH)
                    <p style="font-weight:700;color:#1B334A;font-size:9pt;margin:12px 0 4px 0;">{{ $faseH['nome'] ?? '' }}</p>
                    <table class="tabela-fases">
                        <thead>
                            <tr>
                                <th style="width:28%;">Atividade</th>
                                <th>Descrição</th>
                                <th style="width:9%;text-align:center;">Mín</th>
                                <th style="width:9%;text-align:center;">Máx</th>
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
                            <tr style="background:rgba(56,87,118,0.06);">
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
                <div class="honorarios-box" style="margin-top:6px;">
                    <div style="font-size:9.5pt;font-weight:700;">Total estimado: {{ $totalMin }} a {{ $totalMax }} horas de trabalho</div>
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

        </div>
    </div>
</body>
</html>
