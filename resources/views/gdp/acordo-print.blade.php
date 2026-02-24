<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Acordo de Desempenho — {{ $targetUser->name }}</title>
    <style>
        @page {
            size: A4;
            margin: 25mm 20mm 30mm 20mm;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #1a1a1a;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #C4A35A;
            padding-bottom: 16px;
            margin-bottom: 28px;
        }
        .header img {
            height: 60px;
            margin-bottom: 6px;
        }
        .header .firm-oab {
            font-size: 7.5pt;
            color: #999;
            letter-spacing: 1px;
        }
        .titulo {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            color: #1B334A;
            margin: 20px 0 8px 0;
            letter-spacing: 1px;
        }
        .profissional {
            text-align: left;
            font-size: 11pt;
            margin-bottom: 20px;
        }
        .profissional strong { color: #1B334A; }
        .secao-titulo {
            font-size: 11pt;
            font-weight: bold;
            color: #1B334A;
            margin: 20px 0 8px 0;
        }
        .secao-texto {
            text-align: justify;
            margin-bottom: 12px;
        }
        .eixo-label {
            font-size: 10pt;
            font-weight: bold;
            color: #385776;
            margin: 14px 0 4px 0;
            padding: 4px 8px;
            background: #f0f4f8;
            border-left: 3px solid #385776;
        }
        table.metas {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-bottom: 10px;
        }
        table.metas th {
            background: #1B334A;
            color: #fff;
            padding: 5px 4px;
            text-align: center;
            font-weight: 600;
            font-size: 8pt;
        }
        table.metas th:first-child { text-align: left; padding-left: 8px; }
        table.metas td {
            border: 1px solid #ddd;
            padding: 4px;
            text-align: center;
        }
        table.metas td:first-child { text-align: left; padding-left: 8px; font-size: 8.5pt; }
        table.metas tr:nth-child(even) { background: #fafafa; }
        .responsabilidades { margin: 16px 0; }
        .responsabilidades p { text-indent: 20px; text-align: justify; margin-bottom: 4px; }
        .assinaturas {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .assinatura-box { width: 40%; text-align: center; }
        .assinatura-linha {
            border-top: 1px solid #333;
            padding-top: 4px;
            font-size: 9pt;
        }
        .assinatura-cargo { font-size: 8pt; color: #666; }
        .hash-box {
            margin-top: 24px;
            padding: 8px;
            background: #f9f9f9;
            border: 1px dashed #ccc;
            font-family: monospace;
            font-size: 7pt;
            color: #999;
            text-align: center;
        }
        .footer-cidades {
            margin-top: 30px;
            display: flex;
            justify-content: space-around;
            font-size: 8pt;
            font-family: 'Arial', sans-serif;
            font-weight: bold;
            color: #1B334A;
            letter-spacing: 2px;
            border-top: 1px solid #C4A35A;
            padding-top: 8px;
        }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
        }
        .btn-print {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 24px;
            background: #385776;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .btn-print:hover { background: #1B334A; }
    </style>
</head>
<body>
    <button class="btn-print no-print" onclick="window.print()">🖨️ Imprimir / Salvar PDF</button>

    {{-- HEADER --}}
    <div class="header">
            <img src="/logo.png" alt="Mayer Sociedade de Advogados">
            <div class="firm-oab">OAB/SC 2097</div>
        </div>

    {{-- TÍTULO --}}
    <div class="titulo">ACORDO DE DESEMPENHO</div>
    <div class="profissional">
        <strong>Profissional:</strong> {{ $targetUser->name }}{{ $targetUser->cargo ? ' — '.$targetUser->cargo : '' }}<br>
        <strong>Ciclo:</strong> {{ $ciclo->nome }} ({{ $mesesNomes[$mesInicio] }} a {{ $mesesNomes[$mesFim] }} de {{ $ano }})
    </div>

    {{-- 1. OBJETIVO --}}
    <div class="secao-titulo">1. OBJETIVO</div>
    <div class="secao-texto">
        O presente instrumento tem por finalidade formalizar as metas e critérios de desempenho acordados entre o ESCRITÓRIO e o(a) PROFISSIONAL, garantindo alinhamento com os objetivos estratégicos da equipe e o desenvolvimento profissional contínuo.
    </div>

    {{-- 2. METAS --}}
    <div class="secao-titulo">2. METAS ESTABELECIDAS</div>
    <div class="secao-texto">
        O(A) PROFISSIONAL compromete-se a buscar o atingimento das metas definidas abaixo durante o período de
        <strong>{{ $mesesNomes[$mesInicio] }} a {{ $mesesNomes[$mesFim] }} de {{ $ano }}</strong>:
    </div>

    @foreach($eixos as $eixo)
    @if($eixo->indicadores->isNotEmpty())
    <div class="eixo-label">
        @if($eixo->codigo === 'JURIDICO') ⚖️
        @elseif($eixo->codigo === 'FINANCEIRO') 💰
        @elseif($eixo->codigo === 'DESENVOLVIMENTO') 📚
        @else 💬
        @endif
        {{ $eixo->nome }} (peso {{ number_format($eixo->peso, 0) }}%)
    </div>
    <table class="metas">
        <thead>
            <tr>
                <th style="width:30%;">Indicador</th>
                @for($m = $mesInicio; $m <= $mesFim; $m++)
                    <th>{{ mb_substr($mesesNomes[$m], 0, 3) }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
        @foreach($eixo->indicadores as $ind)
            <tr>
                <td>{{ $ind->codigo }} — {{ $ind->nome }}</td>
                @for($m = $mesInicio; $m <= $mesFim; $m++)
                    @php
                        $key = $ind->id . '_' . $m;
                        $meta = $metas->get($key);
                        $val = $meta ? (float) $meta->valor_meta : 0;
                    @endphp
                    <td>
                        @if($val > 0)
                            @if($ind->unidade === 'reais')
                                R$ {{ number_format($val, 2, ',', '.') }}
                            @elseif($ind->unidade === 'percentual')
                                {{ number_format($val, 1, ',', '.') }}%
                            @elseif($ind->unidade === 'horas')
                                {{ number_format($val, 1, ',', '.') }}h
                            @elseif($ind->unidade === 'minutos')
                                {{ number_format($val, 0) }}min
                            @else
                                {{ number_format($val, 0) }}
                            @endif
                        @else
                            —
                        @endif
                    </td>
                @endfor
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
    @endforeach

    {{-- 3. RESPONSABILIDADES --}}
    <div class="secao-titulo">3. RESPONSABILIDADES DO(A) PROFISSIONAL</div>
    <div class="responsabilidades">
        <p>O(A) PROFISSIONAL compromete-se a:</p>
        <p>a) Buscar ativamente o atingimento das metas estabelecidas no presente termo;</p>
        <p>b) Realizar atendimentos jurídicos com excelência e dentro dos padrões do ESCRITÓRIO;</p>
        <p>c) Cumprir prazos processuais e administrativos com diligência e pontualidade;</p>
        <p>d) Participar dos treinamentos previstos semestralmente;</p>
        <p>e) Atuar com ética, profissionalismo e transparência, zelando pelo nome do ESCRITÓRIO;</p>
        <p>f) Relatar mensalmente o cumprimento das metas e eventuais desafios enfrentados.</p>
    </div>

    {{-- 4. ACOMPANHAMENTO --}}
    <div class="secao-titulo">4. ACOMPANHAMENTO</div>
    <div class="secao-texto">
        O cumprimento das metas será acompanhado mensalmente pelo ESCRITÓRIO, com base nos relatórios de produtividade gerados automaticamente pelo Sistema RESULTADOS!, monitoramento dos indicadores por eixo de desempenho, avaliação qualitativa dos atendimentos prestados e feedbacks internos para ajustes e melhorias.
    </div>
    <div class="secao-texto">
        Caso o(a) PROFISSIONAL não atinja as metas de forma recorrente e sem justificativa razoável, as partes poderão reavaliar este acordo e propor ajustes conforme necessário.
    </div>

    {{-- 5. VIGÊNCIA --}}
    <div class="secao-titulo">5. VIGÊNCIA E REVISÃO</div>
    <div class="secao-texto">
        O presente termo vigorará de <strong>{{ $mesesNomes[$mesInicio] }} a {{ $mesesNomes[$mesFim] }} de {{ $ano }}</strong>, podendo ser revisto a qualquer momento por acordo mútuo entre as partes. Ao final do período, será feita análise de desempenho e ajustadas novas metas, caso aplicável.
    </div>

    {{-- ASSINATURAS --}}
    <div class="assinaturas">
        <div class="assinatura-box">
            <div class="assinatura-linha">ESCRITÓRIO</div>
            <div class="assinatura-cargo">Mayer Sociedade de Advogados</div>
        </div>
        <div class="assinatura-box">
            <div class="assinatura-linha">{{ $targetUser->name }}</div>
            <div class="assinatura-cargo">{{ $targetUser->cargo ?? 'Advogado(a)' }}</div>
        </div>
    </div>

    {{-- HASH --}}
    <div class="hash-box">
        Itajaí/SC, {{ now()->format('d') }} de {{ $mesesNomes[(int)now()->format('n')] }} de {{ now()->format('Y') }}.<br>
        Código de verificação: {{ $hash }}
        @if($acordoAceito)
            <br>Aceito digitalmente em {{ \Carbon\Carbon::parse($acordoAceito->congelado_em)->format('d/m/Y H:i') }}
        @endif
    </div>

    {{-- FOOTER --}}
    <div class="footer-cidades">
        <span>ITAJAÍ</span>
        <span>FLORIANÓPOLIS</span>
        <span>SÃO PAULO</span>
    </div>
</body>
</html>
