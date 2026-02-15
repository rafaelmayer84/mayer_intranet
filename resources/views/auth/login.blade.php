<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Intranet Mayer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; }
        .brand-gradient { background: linear-gradient(135deg, #1B334A 0%, #385776 100%); }
        .btn-brand {
            background-color: #385776;
            transition: background-color 0.2s, transform 0.1s;
        }
        .btn-brand:hover { background-color: #1B334A; }
        .btn-brand:active { transform: scale(0.98); }
        .btn-brand:focus-visible {
            outline: 2px solid #385776;
            outline-offset: 2px;
            box-shadow: 0 0 0 4px rgba(56, 87, 118, 0.2);
        }
        input:focus-visible {
            outline: none;
            border-color: #385776;
            box-shadow: 0 0 0 3px rgba(56, 87, 118, 0.15);
        }
    </style>
</head>
<body class="brand-gradient min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 sm:p-8">
        <div class="text-center mb-6">
            <img src="/logo.png" alt="Logo Mayer Advogados" class="h-12 mx-auto mb-3">
            <h1 class="text-xl font-bold text-gray-800">Intranet Mayer</h1>
            <p class="text-sm text-gray-500 mt-1">Sistema RESULTADOS!</p>
        </div>

        @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-300 text-red-700 rounded-lg text-sm" role="alert">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
        @endif

        <form action="{{ route('login') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required
                    autocomplete="email"
                    placeholder="seu@email.com"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
                <input id="password" type="password" name="password" required
                    autocomplete="current-password"
                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm">
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="remember" id="remember"
                        class="rounded border-gray-300 text-[#385776] focus:ring-[#385776]">
                    <span class="text-sm text-gray-600">Lembrar-me</span>
                </label>
                <a href="mailto:rafael@mayeradvogados.adv.br?subject=Recuperar%20senha%20Intranet"
                   class="text-sm text-[#385776] hover:underline font-medium">
                    Esqueci a senha
                </a>
            </div>

            <button type="submit" class="btn-brand w-full text-white py-2.5 rounded-lg font-semibold text-sm">
                Entrar
            </button>
        </form>

        <p class="mt-5 text-center text-xs text-gray-400">
            Acesso restrito aos colaboradores
        </p>
    </div>
</body>
</html>
