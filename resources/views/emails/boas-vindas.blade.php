<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo à Intranet</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 40px 0;">
                <table role="presentation" style="width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%); padding: 30px 40px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 600;">
                                ⚖️ Mayer Advogados
                            </h1>
                            <p style="color: #a8c5e2; margin: 10px 0 0 0; font-size: 14px;">
                                Sistema de Gestão Interna
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Conteúdo Principal -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #1e3a5f; margin: 0 0 20px 0; font-size: 24px;">
                                Olá, {{ $user->name }}! 👋
                            </h2>
                            
                            <p style="color: #555555; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Seja muito bem-vindo(a) à <strong>Intranet Mayer Advogados</strong>! Seu acesso ao sistema foi criado com sucesso.
                            </p>
                            
                            <!-- Credenciais -->
                            <div style="background-color: #f8f9fa; border-left: 4px solid #1e3a5f; padding: 20px; margin: 25px 0; border-radius: 0 8px 8px 0;">
                                <h3 style="color: #1e3a5f; margin: 0 0 15px 0; font-size: 16px;">
                                    🔐 Suas credenciais de acesso:
                                </h3>
                                <table style="width: 100%;">
                                    <tr>
                                        <td style="padding: 8px 0; color: #666666; width: 100px;">Email:</td>
                                        <td style="padding: 8px 0; color: #333333; font-weight: 600;">{{ $user->email }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #666666;">Senha:</td>
                                        <td style="padding: 8px 0;">
                                            <code style="background-color: #e9ecef; padding: 4px 12px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 15px; color: #d63384;">{{ $senhaTemporaria }}</code>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #666666;">Perfil:</td>
                                        <td style="padding: 8px 0; color: #333333;">
                                            @if($user->role === 'admin')
                                                <span style="background-color: #dc3545; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">Administrador</span>
                                            @elseif($user->role === 'coordenador')
                                                <span style="background-color: #0d6efd; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">Coordenador</span>
                                            @else
                                                <span style="background-color: #198754; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">Sócio</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Botão de Acesso -->
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="https://intranet.mayeradvogados.adv.br/login" 
                                   style="display: inline-block; background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%); color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 6px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 6px rgba(30, 58, 95, 0.3);">
                                    Acessar a Intranet →
                                </a>
                            </div>
                            
                            <!-- Instruções -->
                            <div style="background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px 20px; border-radius: 8px; margin: 25px 0;">
                                <p style="color: #856404; margin: 0; font-size: 14px;">
                                    <strong>⚠️ Importante:</strong> Por segurança, recomendamos que você altere sua senha no primeiro acesso através do menu <em>Configurações</em>.
                                </p>
                            </div>
                            
                            <!-- O que você pode fazer -->
                            <h3 style="color: #1e3a5f; margin: 30px 0 15px 0; font-size: 18px;">
                                📊 O que você pode fazer na Intranet:
                            </h3>
                            <ul style="color: #555555; font-size: 14px; line-height: 1.8; padding-left: 20px;">
                                <li><strong>RESULTADOS!</strong> - Acompanhe os KPIs financeiros e de mercado do escritório</li>
                                <li><strong>Clientes & Mercado</strong> - Visualize métricas de leads, oportunidades e clientes</li>
                                <li><strong>Quadro de Avisos</strong> - Fique por dentro das comunicações internas</li>
                                <li><strong>Gestão de Desempenho</strong> - Acompanhe sua performance individual</li>
                            </ul>
                            
                            <!-- Suporte -->
                            <p style="color: #888888; font-size: 14px; margin: 30px 0 0 0; padding-top: 20px; border-top: 1px solid #eeeeee;">
                                Precisa de ajuda? Entre em contato com a equipe de TI ou responda este email.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 25px 40px; text-align: center; border-top: 1px solid #eeeeee;">
                            <p style="color: #888888; font-size: 12px; margin: 0;">
                                © {{ date('Y') }} Mayer Advogados Associados<br>
                                Este é um email automático, por favor não responda diretamente.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
