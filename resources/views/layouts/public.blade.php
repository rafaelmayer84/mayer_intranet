<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <title>@yield('title', 'Mayer Advogados — Portal do Cliente')</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        serif: ['Playfair Display', 'Georgia', 'serif'],
                    },
                    colors: {
                        navy:  { DEFAULT: '#1a2e4a', 50: '#f0f4f9', 100: '#d6e2ef', 300: '#7aa3c8', 500: '#3b6fa0', 700: '#1a2e4a', 900: '#0d1e32' },
                        gold:  { DEFAULT: '#b8962e', 50: '#fdf8ec', 100: '#f7e9c0', 300: '#d4ad55', 500: '#b8962e', 700: '#8a6f1f' },
                    }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Inter', sans-serif; background: #f6f7f9; }
        .font-serif { font-family: 'Playfair Display', Georgia, serif; }

        /* Linha dourada decorativa */
        .gold-line { height: 3px; background: linear-gradient(90deg, #b8962e 0%, #d4ad55 50%, #b8962e 100%); }

        /* Card padrão */
        .card { @apply bg-white rounded-2xl shadow-sm border border-gray-100 p-6; }

        /* Badge de status */
        .badge { @apply inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold; }

        /* Timeline item */
        .tl-dot { @apply w-2.5 h-2.5 rounded-full bg-gold-500 ring-4 ring-gold-50 shrink-0 mt-1; }

        /* Separator */
        .sep { @apply border-t border-gray-100 my-5; }

        /* Hover link */
        .hover-navy { @apply text-navy-700 hover:text-gold-500 transition-colors; }
    </style>

    @stack('styles')
</head>
<body class="min-h-screen flex flex-col text-gray-800 antialiased">

    {{-- ── HEADER ── --}}
    <header class="bg-navy-700 relative">
        <div class="gold-line absolute top-0 left-0 right-0"></div>
        <div class="max-w-3xl mx-auto px-6 py-5 flex items-center justify-between">
            {{-- Logo --}}
            <div class="flex items-center gap-4">
                <img src="{{ asset('logo-mayer.png') }}" alt="Mayer Advogados"
                     class="h-12 w-auto object-contain">
                <div class="border-l border-white/20 pl-4 hidden sm:block">
                    <p class="text-white/60 text-xs tracking-widest uppercase font-medium">Portal do Cliente</p>
                    <p class="text-white text-xs mt-0.5">Acompanhamento processual</p>
                </div>
            </div>

            {{-- Sinal de segurança --}}
            <div class="flex items-center gap-1.5 text-white/50 text-xs">
                <svg class="w-3.5 h-3.5 text-gold-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <span class="hidden sm:inline">Acesso seguro e privado</span>
                <span class="sm:hidden">Seguro</span>
            </div>
        </div>
        <div class="gold-line absolute bottom-0 left-0 right-0 opacity-30"></div>
    </header>

    {{-- ── CONTEÚDO ── --}}
    <main class="flex-1 max-w-3xl w-full mx-auto px-4 sm:px-6 py-8 pb-16">
        @yield('content')
    </main>

    {{-- ── FOOTER ── --}}
    <footer class="bg-navy-900 text-white/50">
        <div class="gold-line opacity-40"></div>
        <div class="max-w-3xl mx-auto px-6 py-8">
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 sm:gap-12">

                {{-- Logo footer --}}
                <div class="shrink-0">
                    <img src="{{ asset('logo-mayer.png') }}" alt="Mayer Advogados"
                         class="h-10 w-auto object-contain opacity-60">
                </div>

                {{-- Info escritório --}}
                <div class="text-xs leading-relaxed text-center sm:text-left flex-1">
                    <p class="text-white/70 font-semibold mb-1">Mayer Sociedade de Advogados</p>
                    <p>OAB/SC — Blumenau • SC</p>
                    <p class="mt-1">contato@mayeradvogados.adv.br</p>
                    <p>(47) 3842-1050</p>
                </div>

                {{-- Aviso LGPD --}}
                <div class="text-xs leading-relaxed text-center sm:text-right max-w-xs">
                    <p class="text-white/40">Este portal é de acesso exclusivo e temporário. As informações aqui exibidas são protegidas pelo sigilo profissional e pela LGPD.</p>
                </div>

            </div>

            <div class="sep opacity-20 mt-6"></div>
            <p class="text-xs text-center text-white/30">© {{ date('Y') }} Mayer Advogados — Todos os direitos reservados.</p>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
