<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; background: #f4f4f7; padding: 20px;">
<div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden;">

    <div style="background: #1e3a5f; padding: 24px; text-align: center;">
        <h1 style="color: #fff; margin: 0; font-size: 22px;">MAYER ADVOGADOS</h1>
        <p style="color: #a0c4e8; margin: 4px 0 0; font-size: 13px;">NEXO — Gestão de Atendimentos WhatsApp</p>
    </div>

    <div style="padding: 32px 24px;">
        <p style="font-size: 16px; color: #333;">Olá, <strong>{{ $responsavel->name }}</strong>.</p>

        @if(($dados['prioridade'] ?? 'normal') === 'urgente')
        <div style="background: #fff3cd; border-left: 4px solid #e67e22; padding: 12px 16px; border-radius: 4px; margin-bottom: 20px;">
            <p style="margin: 0; font-size: 14px; color: #b7560a; font-weight: bold;">⚠️ Este ticket está marcado como URGENTE.</p>
        </div>
        @endif

        <p style="font-size: 15px; color: #333;">
            Um novo ticket de atendimento WhatsApp foi atribuído a você:
        </p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px;">
            <tr style="background: #f8f9fa;">
                <td style="padding: 10px 14px; color: #666; width: 35%; border-bottom: 1px solid #e9ecef;">Protocolo</td>
                <td style="padding: 10px 14px; color: #1e3a5f; font-weight: bold; border-bottom: 1px solid #e9ecef; font-family: monospace;">
                    {{ $dados['protocolo'] ?? '—' }}
                </td>
            </tr>
            @if(!empty($dados['nome_cliente']))
            <tr>
                <td style="padding: 10px 14px; color: #666; border-bottom: 1px solid #e9ecef;">Cliente</td>
                <td style="padding: 10px 14px; color: #333; border-bottom: 1px solid #e9ecef;">
                    {{ $dados['nome_cliente'] }}
                    @if(!empty($dados['telefone']))
                        <span style="color: #888; font-size: 12px;"> &nbsp;{{ $dados['telefone'] }}</span>
                    @endif
                </td>
            </tr>
            @endif
            @if(!empty($dados['tipo']))
            <tr style="background: #f8f9fa;">
                <td style="padding: 10px 14px; color: #666; border-bottom: 1px solid #e9ecef;">Tipo</td>
                <td style="padding: 10px 14px; color: #333; border-bottom: 1px solid #e9ecef;">{{ $dados['tipo'] }}</td>
            </tr>
            @endif
            <tr @if(empty($dados['tipo'])) style="background: #f8f9fa;" @endif>
                <td style="padding: 10px 14px; color: #666; border-bottom: 1px solid #e9ecef;">Assunto</td>
                <td style="padding: 10px 14px; color: #333; font-weight: 500; border-bottom: 1px solid #e9ecef;">{{ $dados['assunto'] ?? '—' }}</td>
            </tr>
            @if(!empty($dados['mensagem']))
            <tr style="background: #f8f9fa;">
                <td style="padding: 10px 14px; color: #666; vertical-align: top;">Mensagem</td>
                <td style="padding: 10px 14px; color: #555; white-space: pre-wrap;">{{ $dados['mensagem'] }}</td>
            </tr>
            @endif
        </table>

        <div style="text-align: center; margin: 32px 0;">
            <a href="{{ $dados['link'] ?? '#' }}"
               style="background: #1e3a5f; color: #fff; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-size: 15px; font-weight: bold;">
                Abrir Ticket na Intranet
            </a>
        </div>
    </div>

    <div style="background: #f0f0f0; padding: 16px; text-align: center;">
        <p style="font-size: 11px; color: #888; margin: 0;">
            Intranet Mayer Advogados — NEXO · Atendimento WhatsApp<br>
            Este e-mail foi gerado automaticamente. Não responda a este endereço.
        </p>
    </div>
</div>
</body>
</html>
