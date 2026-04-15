<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <title>@yield('title', 'Mayer Advogados')</title>

    {{-- Tailwind CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Alpine.js --}}
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- Google Fonts: Inter --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        navy: { DEFAULT: '#1a2e4a', 50: '#edf2f8', 100: '#c8d8ea', 200: '#94b5d3', 500: '#3b6fa0', 700: '#1a2e4a', 900: '#0f1c2e' },
                        brand: { DEFAULT: '#1a2e4a' },
                    }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Inter', sans-serif; }
        .badge { @apply inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium; }
        .card  { @apply bg-white rounded-2xl shadow-sm border border-gray-100 p-5; }
    </style>

    @stack('styles')
</head>
<body class="h-full bg-gray-50 text-gray-800 antialiased">

    {{-- Header --}}
    <header class="bg-navy-700 text-white sticky top-0 z-30 shadow-md">
        <div class="max-w-2xl mx-auto px-4 py-3 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center font-bold text-lg select-none">M</div>
            <div>
                <p class="font-semibold leading-tight text-sm">Mayer Advogados</p>
                <p class="text-xs text-white/60 leading-tight">Portal do cliente</p>
            </div>
        </div>
    </header>

    {{-- Conteúdo --}}
    <main class="max-w-2xl mx-auto px-4 py-6 pb-28">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="bg-white border-t border-gray-100 text-center py-5 text-xs text-gray-400">
        <p>Precisa de ajuda? Entre em contato via WhatsApp.</p>
        <p class="mt-1">© {{ date('Y') }} Mayer Advogados — Todos os direitos reservados.</p>
    </footer>

    {{-- Botão flutuante WhatsApp --}}
    <a href="{{ $whatsappUrl ?? 'https://wa.me/5548' }}"
       target="_blank" rel="noopener noreferrer"
       class="fixed bottom-6 right-4 z-50 flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white text-sm font-semibold px-4 py-3 rounded-full shadow-lg transition-all active:scale-95">
        <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 24 24">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
        Falar com escritório
    </a>

    @stack('scripts')
</body>
</html>
