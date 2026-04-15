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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans:  ['Lato', 'sans-serif'],
                        serif: ['"Playfair Display"', 'Georgia', 'serif'],
                    },
                    colors: {
                        navy: { DEFAULT: '#1a2e4a', 50: '#f0f4f9', 100: '#d6e2ef', 200: '#a8c3da', 500: '#3b6fa0', 700: '#1a2e4a', 900: '#0d1e32' },
                        gold: { DEFAULT: '#b8962e', 50: '#fdf8ec', 100: '#f5e9bf', 300: '#d4ad55', 500: '#b8962e', 700: '#8a6f1f' },
                        ink:  '#1c1c1e',
                        parchment: '#faf8f4',
                    },
                    animation: {
                        'fade-up': 'fadeUp 0.5s ease both',
                    },
                    keyframes: {
                        fadeUp: {
                            '0%':   { opacity: '0', transform: 'translateY(14px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --navy: #1a2e4a;
            --gold:  #b8962e;
            --gold-light: #d4ad55;
            --parchment: #faf8f4;
        }

        * { -webkit-font-smoothing: antialiased; }

        body {
            font-family: 'Lato', sans-serif;
            background: var(--parchment);
            color: #1c1c1e;
        }

        /* Faixa dourada de 2px */
        .gold-rule { height: 2px; background: linear-gradient(90deg, transparent 0%, var(--gold) 20%, var(--gold-light) 50%, var(--gold) 80%, transparent 100%); }

        /* Badge de status */
        .status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 12px; border-radius: 999px;
            font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
        }
        .status-ativo    { background: rgba(16,185,129,.12); color: #059669; border: 1px solid rgba(16,185,129,.25); }
        .status-encerrado { background: rgba(107,114,128,.1); color: #6b7280; border: 1px solid rgba(107,114,128,.2); }
        .status-default  { background: rgba(184,150,46,.12); color: #8a6f1f; border: 1px solid rgba(184,150,46,.25); }

        /* Card padrão */
        .card { background: #fff; border-radius: 16px; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 1px 4px rgba(0,0,0,.04); padding: 24px; }

        /* Stagger animation */
        .anim-1 { animation: fadeUp .5s ease .05s both; }
        .anim-2 { animation: fadeUp .5s ease .12s both; }
        .anim-3 { animation: fadeUp .5s ease .20s both; }
        .anim-4 { animation: fadeUp .5s ease .30s both; }
        .anim-5 { animation: fadeUp .5s ease .40s both; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>

    @stack('styles')
</head>
<body class="min-h-screen flex flex-col">

    {{-- ── HEADER ── --}}
    <header class="bg-navy-700 relative">
        <div class="gold-rule absolute top-0 left-0 right-0"></div>
        <div class="w-full px-4 sm:px-8 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <img src="{{ asset('logo-mayer.png') }}" alt="Mayer Advogados" class="h-10 sm:h-12 w-auto object-contain">
                <div class="hidden sm:block border-l border-white/15 pl-4">
                    <p class="text-white/50 text-[10px] tracking-[.2em] uppercase font-light">Portal do Cliente</p>
                    <p class="text-white/80 text-xs mt-px">Acompanhamento processual</p>
                </div>
            </div>

            <div class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-gold-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <span class="text-white/40 text-[11px] hidden sm:inline">Acesso seguro e privado</span>
            </div>
        </div>
        <div class="gold-rule absolute bottom-0 left-0 right-0 opacity-20"></div>
    </header>

    {{-- ── CONTEÚDO ── --}}
    <main class="flex-1 w-full">
        @yield('content')
    </main>

    {{-- ── FOOTER ── --}}
    <footer class="bg-navy-900 text-white/40 mt-12">
        <div class="gold-rule opacity-30"></div>
        <div class="w-full px-4 sm:px-8 py-10">
            <div class="flex flex-col sm:flex-row gap-8 sm:gap-16">

                <div class="shrink-0">
                    <img src="{{ asset('logo-mayer.png') }}" alt="Mayer Advogados" class="h-9 w-auto object-contain opacity-50">
                </div>

                <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs">
                    <div>
                        <p class="text-white/70 font-semibold text-sm mb-2">Mayer Sociedade de Advogados</p>
                        <p class="leading-relaxed">OAB/SC 2097<br>Itajaí, Santa Catarina</p>
                    </div>
                    <div>
                        <p class="text-white/50 font-semibold uppercase tracking-widest text-[10px] mb-2">Contato</p>
                        <p class="leading-relaxed">
                            <a href="tel:+554738421050" class="hover:text-white/70 transition-colors">(47) 3842-1050</a><br>
                            <a href="mailto:contato@mayeradvogados.adv.br" class="hover:text-white/70 transition-colors">contato@mayeradvogados.adv.br</a>
                        </p>
                    </div>
                    <div>
                        <p class="text-white/50 font-semibold uppercase tracking-widest text-[10px] mb-2">Privacidade</p>
                        <p class="leading-relaxed text-white/30">Este portal é de acesso exclusivo e temporário. As informações são protegidas pelo sigilo profissional e pela Lei Geral de Proteção de Dados (LGPD).</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-white/10 mt-8 pt-6 text-center text-[11px] text-white/20">
                © {{ date('Y') }} Mayer Advogados — Todos os direitos reservados.
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
