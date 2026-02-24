<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; background: #f4f4f7; padding: 20px;">
<div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden;">
    <div style="background: #1e3a5f; padding: 24px; text-align: center;">
        <h1 style="color: #fff; margin: 0; font-size: 22px;">MAYER ADVOGADOS</h1>
        <p style="color: #a0c4e8; margin: 4px 0 0; font-size: 13px;">Gestão de Desempenho de Pessoas</p>
    </div>
    <div style="padding: 32px 24px;">
        <p style="font-size: 16px; color: #333;">Olá, <strong>{{ $advogado->name }}</strong>.</p>

        @if($isLembrete)
        <p style="font-size: 15px; color: #c0392b; font-weight: bold;">
            ⚠️ Seu Acordo de Desempenho do ciclo {{ $cicloNome }} ainda não foi assinado.
        </p>
        <p style="font-size: 14px; color: #555;">
            Este é um lembrete automático. A assinatura do acordo é obrigatória para o acompanhamento das suas metas.
        </p>
        @else
        <p style="font-size: 15px; color: #333;">
            Seu Acordo de Desempenho do ciclo <strong>{{ $cicloNome }}</strong> foi elaborado e está disponível para revisão e assinatura.
        </p>
        <p style="font-size: 14px; color: #555;">
            Revise atentamente as metas definidas para cada indicador e, estando de acordo, efetue a assinatura digital.
        </p>
        @endif

        <div style="text-align: center; margin: 32px 0;">
            <a href="{{ $linkAceite }}" style="background: #1e3a5f; color: #fff; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-size: 15px; font-weight: bold;">
                Revisar e Assinar Acordo
            </a>
        </div>

        <p style="font-size: 12px; color: #999; text-align: center;">
            Caso o botão não funcione, copie e cole este link no navegador:<br>
            <a href="{{ $linkAceite }}" style="color: #1e3a5f;">{{ $linkAceite }}</a>
        </p>
    </div>
    <div style="background: #f0f0f0; padding: 16px; text-align: center;">
        <p style="font-size: 11px; color: #888; margin: 0;">Intranet Mayer Advogados — Sistema RESULTADOS!</p>
    </div>
</div>
</body>
</html>
