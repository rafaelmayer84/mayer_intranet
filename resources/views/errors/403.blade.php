<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Negado | Mayer Advogados</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Montserrat', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1B334A 0%, #152A3D 50%, #0F2030 100%);
            color: #FFFFFF;
            padding: 1rem;
        }

        .container {
            text-align: center;
            max-width: 520px;
            width: 100%;
        }

        .icon-shield {
            width: 96px;
            height: 96px;
            margin: 0 auto 2rem;
            opacity: 0.85;
        }

        .code {
            font-size: 5rem;
            font-weight: 700;
            letter-spacing: -2px;
            color: #385776;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #E8ECF1;
        }

        .message {
            font-size: 0.95rem;
            font-weight: 400;
            color: #94A3B8;
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: #385776;
            color: #FFFFFF;
        }

        .btn-primary:hover {
            background: #2C4660;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(56, 87, 118, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: #CBD5E1;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #FFFFFF;
            transform: translateY(-1px);
        }

        .footer {
            margin-top: 3rem;
            font-size: 0.75rem;
            color: #475569;
        }

        .footer span {
            color: #385776;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Shield/Lock SVG -->
        <svg class="icon-shield" viewBox="0 0 96 96" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M48 8L16 24V44C16 66.1 29.7 86.5 48 92C66.3 86.5 80 66.1 80 44V24L48 8Z" fill="rgba(56,87,118,0.25)" stroke="#385776" stroke-width="2.5"/>
            <rect x="36" y="42" width="24" height="18" rx="3" fill="none" stroke="#94A3B8" stroke-width="2.5"/>
            <path d="M41 42V36C41 32.1 44.1 29 48 29C51.9 29 55 32.1 55 36V42" fill="none" stroke="#94A3B8" stroke-width="2.5" stroke-linecap="round"/>
            <circle cx="48" cy="51" r="2.5" fill="#94A3B8"/>
            <line x1="48" y1="53.5" x2="48" y2="56" stroke="#94A3B8" stroke-width="2" stroke-linecap="round"/>
        </svg>

        <div class="code">403</div>
        <h1 class="title">Acesso Negado</h1>
        <p class="message">
            {{ $exception->getMessage() ?: 'Você não possui permissão para acessar este recurso. Se acredita que isso é um erro, entre em contato com o administrador do sistema.' }}
        </p>

        <div class="actions">
            <a href="{{ url('/avisos') }}" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Página Inicial
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Voltar
            </a>
        </div>

        <p class="footer">Sistema <span>RESULTADOS!</span> &mdash; Mayer Advogados</p>
    </div>
</body>
</html>
