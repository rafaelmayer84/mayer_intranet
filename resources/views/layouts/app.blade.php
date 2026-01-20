<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Intranet') - {{ config('app.name', 'Mayer Advogados') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- CSS auxiliar (transições, tema claro/escuro, pequenos fixes) -->
    <link rel="stylesheet" href="{{ asset('css/intranet-ui.css') }}">
    <!-- Hotfix legado (mantido) -->
    <link rel="stylesheet" href="{{ asset('theme.css') }}">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .submenu { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .submenu.open { max-height: 500px; }
        .menu-arrow { transition: transform 0.3s ease; }
        .menu-arrow.rotated { transform: rotate(180deg); }
        
        /* Garantir que o header mobile funcione */
        @media (max-width: 767px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                z-index: 40;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 30;
                display: none;
            }
            
            .sidebar-overlay.active {
                display: block;
            }
            
            .hamburger-btn {
                display: flex;
                flex-direction: column;
                gap: 5px;
                background: none;
                border: none;
                cursor: pointer;
                padding: 5px;
            }
            
            .hamburger-btn span {
                width: 25px;
                height: 3px;
                background-color: currentColor;
                transition: all 0.3s ease;
            }
            
            .hamburger-btn.active span:nth-child(1) {
                transform: rotate(45deg) translate(10px, 10px);
            }
            
            .hamburger-btn.active span:nth-child(2) {
                opacity: 0;
            }
            
            .hamburger-btn.active span:nth-child(3) {
                transform: rotate(-45deg) translate(7px, -7px);
            }
            
            .mobile-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                background-color: #1f2937;
                border-bottom: 1px solid #374151;
                padding: 1rem;
                position: sticky;
                top: 0;
                z-index: 20;
            }
            
            .mobile-header.dark {
                background-color: #111827;
                border-bottom-color: #1f2937;
            }
            
            .main-content {
                margin-top: 0;
            }
        }
        
        @media (min-width: 768px) {
            .hamburger-btn {
                display: none;
            }
            
            .mobile-header {
                display: none;
            }
            
            .sidebar {
                position: static;
                transform: none;
            }
            
            .sidebar-overlay {
                display: none !important;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="sidebar w-64 bg-gray-800 border-r border-gray-700 flex flex-col">
            <!-- Logo/Nome do Sistema -->
            <div class="p-6 border-b border-gray-700 flex items-center justify-between">
                <img src="/logo.png" alt="Mayer Albanez" class="h-16">
                <!-- Botão de Tema (visível em desktop) -->
                <button id="theme-toggle-btn" class="theme-toggle ml-2 hidden md:block" aria-label="Alternar tema">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="w-5 h-5">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </button>
            </div>
            
            <!-- Menu Principal -->
            <nav class="flex-1 p-4 space-y-1">
                <!-- 1) Quadro de Avisos (topo) -->
                <a href="{{ route('avisos.index') }}"
                   class="nav-link flex items-center px-4 py-3 rounded-lg transition-colors {{ (request()->routeIs('avisos.*') || request()->routeIs('admin.avisos.*') || request()->routeIs('admin.categorias-avisos.*')) ? 'nav-link-active' : '' }}">
                    <span class="w-5 h-5 mr-3" aria-hidden="true">📢</span>
                    Quadro de Avisos
                </a>

                <!-- 2) RESULTADOS! (colapsável) -->
                <div class="menu-group">
                    <button onclick="toggleSubmenu('resultados')" class="nav-link w-full flex items-center justify-between px-4 py-3 rounded-lg transition-colors {{ request()->is('visao-gerencial*') ? 'nav-link-active' : '' }}">
                        <div class="flex items-center">
                            <span class="w-5 h-5 mr-3" aria-hidden="true">🎯</span>
                            <span class="font-medium">RESULTADOS!</span>
                        </div>
                        <svg id="arrow-resultados" class="w-4 h-4 menu-arrow {{ request()->is('visao-gerencial*') ? 'rotated' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="submenu-resultados" class="submenu {{ request()->is('visao-gerencial*') ? 'open' : '' }} ml-4 mt-1 space-y-1">
                        <a href="{{ route('visao-gerencial') }}"
                           class="nav-sublink flex items-center px-4 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('visao-gerencial') ? 'nav-sublink-active' : '' }}">
                            <span class="w-4 h-4 mr-3" aria-hidden="true">💰</span>
                            Financeiro
                        </a>
                        <a href="#" class="nav-sublink nav-sublink-disabled flex items-center px-4 py-2 text-sm rounded-lg transition-colors">
                            <span class="w-4 h-4 mr-3" aria-hidden="true">⚖️</span>
                            Clientes &amp; Mercado
                        </a>
                        <a href="#" class="nav-sublink nav-sublink-disabled flex items-center px-4 py-2 text-sm rounded-lg transition-colors">
                            <span class="w-4 h-4 mr-3" aria-hidden="true">⚙️</span>
                            Processos Internos
                        </a>
                        <a href="#" class="nav-sublink nav-sublink-disabled flex items-center px-4 py-2 text-sm rounded-lg transition-colors">
                            <span class="w-4 h-4 mr-3" aria-hidden="true">👥</span>
                            Time &amp; Evolução
                        </a>
                    </div>
                </div>

                <!-- Divisor visual -->
                <hr class="my-4 border-gray-700/70">
                
                <div class="pt-4 pb-2">
                    <p class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Administração</p>
                </div>
                
                @if(auth()->user()->role === 'admin')
                <!-- Sincronização -->
                <a href="{{ route('sync.index') }}" 
                   class="nav-link flex items-center px-4 py-3 rounded-lg transition-colors {{ request()->routeIs('sync.*') ? 'nav-link-active' : '' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Sincronização
                </a>
                @endif
                
                <!-- Configurações -->
                <a href="{{ route('configuracoes.index') }}" 
                   class="nav-link flex items-center px-4 py-3 rounded-lg transition-colors {{ request()->routeIs('configuracoes.*') ? 'nav-link-active' : '' }}">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Configurações
                </a>
            </nav>
            
            <!-- Usuário -->
            <div class="p-4 border-t border-gray-700">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-semibold">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-white">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-gray-400">{{ ucfirst(auth()->user()->role) }}</p>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="mt-3">
                    @csrf
                    <button type="submit" class="nav-link w-full flex items-center justify-center px-4 py-2 text-sm rounded-lg transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Sair
                    </button>
                </form>
            </div>
        </aside>
        
        <!-- Overlay para menu mobile -->
        <div class="sidebar-overlay"></div>
        
        <!-- Main Content -->
        <main class="flex-1 overflow-auto w-full">
            <!-- Header Mobile -->
            <header class="mobile-header">
                <div class="flex items-center gap-2">
                    <button id="hamburger-btn" class="hamburger-btn" aria-label="Abrir menu" aria-expanded="false">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                    <h1 class="text-lg font-semibold text-white">Intranet</h1>
                </div>
                <!-- Botão de Tema (visível em mobile) -->
                <button id="theme-toggle-btn-mobile" class="theme-toggle md:hidden" aria-label="Alternar tema">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="w-5 h-5">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </button>
            </header>
            
            <div class="p-4 md:p-8">
                @yield('content')
            </div>
        </main>
    </div>
    
    <script>
        function toggleSubmenu(name) {
            const submenu = document.getElementById('submenu-' + name);
            const arrow = document.getElementById('arrow-' + name);
            
            if (submenu.classList.contains('open')) {
                submenu.classList.remove('open');
                arrow.classList.remove('rotated');
            } else {
                submenu.classList.add('open');
                arrow.classList.add('rotated');
            }
        }
    </script>
    
    <!-- Scripts de Tema e Menu Mobile -->
    <script src="{{ asset('theme-toggle.js') }}"></script>
    <script src="{{ asset('mobile-menu.js') }}"></script>
    
    @stack('scripts')
</body>
</html>
