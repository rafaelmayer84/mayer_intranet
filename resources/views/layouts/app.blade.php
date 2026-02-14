<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Intranet') - {{ config('app.name', 'Mayer Advogados') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = {}</script>
    <!-- CSS Band-aid removido — agora em mayer-brand.css -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <!-- Inter removida - Montserrat e a font oficial da marca -->
    <!-- CSS auxiliar (transições, tema claro/escuro, pequenos fixes) -->
    <link rel="stylesheet" href="{{ asset('css/intranet-ui.css') }}">
    <!-- Hotfix legado (mantido) -->
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    <!-- Identidade Visual Unificada Mayer Advogados -->
    <link rel="stylesheet" href="{{ asset('css/mayer-brand.css') }}">
    <style>
        body { font-family: 'Montserrat', 'Inter', system-ui, sans-serif; }
        .submenu { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .submenu.open { max-height: 500px; }
        .menu-arrow { transition: transform 0.3s ease; }
        .menu-arrow.rotated { transform: rotate(180deg); }

        /* ========== SIDEBAR TOGGLE - APENAS DESKTOP (>= 768px) ========== */
        
        @media (min-width: 768px) {
            /* Sidebar com transição suave */
            #sidebar {
                width: 16rem;
                transition: width 0.3s ease-in-out;
                position: relative;
            }

            /* Sidebar retraída */
            #sidebar.sidebar-collapsed {
                width: 5rem;
            }

            /* Botão de toggle (discreto, inline) */
            #sidebar-toggle-btn {
                cursor: pointer;
                transition: all 0.2s;
            }

            /* Textos dos menus */
            .menu-text {
                transition: opacity 0.2s ease-in-out;
                white-space: nowrap;
            }

            #sidebar.sidebar-collapsed .menu-text {
                opacity: 0;
                width: 0;
                overflow: hidden;
            }

            /* Setas dos submenus */
            #sidebar.sidebar-collapsed .menu-arrow {
                opacity: 0;
            }

            /* Links centralizados quando retraído */
            #sidebar.sidebar-collapsed .nav-link,
            #sidebar.sidebar-collapsed .nav-sublink {
                justify-content: center;
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }

            /* Esconde submenus quando retraído */
            #sidebar.sidebar-collapsed .submenu {
                display: none !important;
            }

            /* Tooltip hover */
            #sidebar.sidebar-collapsed .nav-link:hover::after,
            #sidebar.sidebar-collapsed .nav-sublink:hover::after {
                content: attr(data-tooltip);
                position: absolute;
                left: 100%;
                top: 50%;
                transform: translateY(-50%);
                background: #1B334A;
                color: white;
                padding: 0.5rem 1rem;
                border-radius: 0.375rem;
                white-space: nowrap;
                z-index: 1000;
                margin-left: 0.75rem;
                font-size: 0.875rem;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
                pointer-events: none;
            }
        }

        /* ========== FIM SIDEBAR TOGGLE DESKTOP ========== */

        /* ========== MOBILE - COMPORTAMENTO ORIGINAL MANTIDO ========== */
        @media (max-width: 767px) {
            /* Esconde botão de toggle em mobile */
            #sidebar-toggle-btn {
                display: none !important;
            }

            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                width: 16rem;
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

            <!-- Mayer Albanez: Montserrat Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet">
    </head>
<body class="min-h-screen">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar w-64 bg-gray-800 border-r border-gray-700 flex flex-col">
            <!-- Botão Toggle Sidebar (Desktop only) -->
            <button id="sidebar-toggle-btn" 
                    type="button"
                    class="hidden md:block"
                    aria-label="Retrair/Expandir menu lateral"
                    title="Retrair menu">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
                </svg>
            </button>

            <!-- Logo/Nome do Sistema -->
            <div class="sidebar-logo p-5 border-b border-white/10 flex items-center justify-center">
                <img src="/img/logo-icon-white.svg" alt="Mayer Albanez" class="h-10 w-auto menu-text" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));">
            </div>

            <!-- Menu Principal -->
            <nav class="flex-1 p-4 space-y-1">
                <!-- 1) Quadro de Avisos (topo) -->
                <a href="{{ route('avisos.index') }}"
                   class="nav-link flex items-center px-4 py-3 rounded-lg transition-colors {{ (request()->routeIs('avisos.*') || request()->routeIs('admin.avisos.*') || request()->routeIs('admin.categorias-avisos.*')) ? 'nav-link-active' : '' }}"
                   data-tooltip="Quadro de Avisos">
                    <span class="w-5 h-5 mr-3" aria-hidden="true">📢</span>
                    <span class="menu-text">Quadro de Avisos</span>
                </a>
                    <!-- Manuais Normativos -->
                    <a href="{{ route('manuais-normativos.index') }}"
                       class="nav-link flex items-center px-4 py-3 rounded-lg transition-colors {{ request()->is('manuais-normativos*') ? 'nav-link-active' : '' }}"
                       data-tooltip="Manuais Normativos">
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        <span class="menu-text">Manuais Normativos</span>
                    </a>

                <!-- 2) RESULTADOS! (colapsável) -->
                <div class="menu-group">
                    <button onclick="toggleSubmenu('resultados')" class="nav-link w-full flex items-center justify-between px-4 py-3 rounded-lg transition-colors {{ request()->is('visao-gerencial*') || request()->is('clientes-mercado*') || request()->is('processos-internos*') || request()->is('times-evolucao*') ? 'nav-link-active' : '' }}" data-tooltip="RESULTADOS!">
                        <div class="flex items-center">
                            <span class="w-5 h-5 mr-3" aria-hidden="true">🎯</span>
                            <span class="font-medium menu-text">RESULTADOS!</span>
                        </div>
                        <svg id="arrow-resultados" class="w-4 h-4 menu-arrow {{ request()->is('visao-gerencial*') || request()->is('clientes-mercado*') || request()->is('processos-internos*') || request()->is('times-evolucao*') ? 'rotated' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="submenu-resultados" class="submenu {{ request()->is('visao-gerencial*') || request()->is('clientes-mercado*') || request()->is('processos-internos*') || request()->is('times-evolucao*') ? 'open' : '' }} ml-4 mt-1 space-y-1">
                        <a href="{{ route('visao-gerencial') }}"
                           class="nav-sublink flex items-center px-4 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('visao-gerencial') ? 'nav-sublink-active' : '' }}"
                           data-tooltip="Finanças">
                            <span class="w-4 h-4 mr-3" aria-hidden="true">💰</span>
                            <span class="menu-text">Finanças</span>
                        </a>
                        <a href="{{ route('clientes-mercado') }}"
                           class="nav-sublink flex items-center px-4 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('clientes-mercado') ? 'nav-sublink-active' : '' }}"
                           data-tooltip="Clientes & Mercado">
                            <span class="w-4 h-4 mr-3" aria-hidden="true">⚖️</span>
                            <span class="menu-text">Clientes &amp; Mercado</span>
                        </a>
                        <a href="{{ route('resultados.bsc.processos-internos.index') }}" class="nav-sublink flex items-center px-4 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('resultados.bsc.processos-internos.*') ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                            <span class="w-4 h-4 mr-3" aria-hidden="true">⚙️</span>
                            <span class="menu-text">Processos Internos</span>
                        </a>
                        <a href="{{ route('times-evolucao.index') }}" class="nav-sublink flex items-center px-4 py-2 text-sm rounded-lg transition-colors {{ request()->routeIs('times-evolucao.*') ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                            <span class="w-4 h-4 mr-3" aria-hidden="true">👥</span>
                            <span class="menu-text">Times &amp; Evolução</span>
                        </a>
                    </div>
                </div>
                <!-- GPD -->
                <div class="menu-group">
                    <button class="nav-link w-full flex items-center justify-between px-4 py-3 rounded-lg transition-colors opacity-60 cursor-default" data-tooltip="GPD">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            <span class="font-medium menu-text">GPD</span>
                        </div>
                        <span class="text-xs ml-auto bg-yellow-600 px-2 py-0.5 rounded menu-text">Em breve</span>
                    </button>
                </div>

                <!-- NEXO -->
                <div class="menu-group">
                    <button onclick="toggleSubmenu('nexo')" class="nav-link w-full flex items-center justify-between px-4 py-3 rounded-lg transition-colors {{ request()->is("nexo/*") || request()->is("crm/*") || request()->routeIs("leads.*") ? "nav-link-active" : ""  }}" data-tooltip="NEXO">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-3 flex-shrink-0 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            <span class="font-medium menu-text">NEXO</span>
                        </div>
                        <svg id="arrow-nexo" class="w-4 h-4 menu-arrow {{ request()->is("nexo/*") || request()->is("crm/*") || request()->routeIs("leads.*") ? "rotated" : "" }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div id="submenu-nexo" class="submenu {{ request()->is("nexo/*") || request()->is("crm/*") || request()->routeIs("leads.*") ? "open" : "" }} ml-4 mt-1 space-y-1">
                        <a href="{{ route('leads.index') }}"
                           class="nav-sublink flex items-center px-4 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs('leads.*') ? 'nav-link-active' : '' }}"
                           data-tooltip="Central de Leads">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            <span class="menu-text">Central de Leads</span>
                        </a>
                        <a href="{{ route('nexo.atendimento') }}"
                           class="nav-sublink flex items-center px-4 py-2 rounded-lg text-sm transition-colors {{ request()->routeIs("nexo.atendimento*") ? "nav-link-active" : "" }}"
                           data-tooltip="Atendimento">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/></svg>
                            <span class="menu-text">Atendimento</span>
                        </a>
                    {{-- CRM (sub-itens) --}}
                    <div x-data="{ crmOpen: {{ request()->is('crm/*') ? 'true' : 'false' }} }" class="relative">
                        <button @click="crmOpen = !crmOpen" class="flex items-center justify-between w-full px-3 py-1.5 text-sm rounded-lg hover:bg-gray-100 transition
                            {{ request()->is('crm/*') ? 'text-[#385776] font-medium' : 'text-gray-500' }}">
                            <span class="flex items-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                CRM
                            </span>
                            <svg class="w-3 h-3 transition-transform" :class="crmOpen ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <div x-show="crmOpen" x-collapse class="ml-4 mt-1 space-y-0.5">
                            <a href="{{ route('crm.leads') }}" class="block px-3 py-1 text-xs rounded-lg hover:bg-gray-100 {{ request()->routeIs('crm.leads') ? 'text-[#385776] font-medium' : 'text-gray-400' }}">Leads</a>
                            <a href="{{ route('crm.pipeline') }}" class="block px-3 py-1 text-xs rounded-lg hover:bg-gray-100 {{ request()->routeIs('crm.pipeline') ? 'text-[#385776] font-medium' : 'text-gray-400' }}">Oportunidades</a>
                            <a href="{{ route('crm.carteira') }}" class="block px-3 py-1 text-xs rounded-lg hover:bg-gray-100 {{ request()->routeIs('crm.carteira') ? 'text-[#385776] font-medium' : 'text-gray-400' }}">Carteira</a>
                            <a href="{{ route('crm.reports') }}" class="block px-3 py-1 text-xs rounded-lg hover:bg-gray-100 {{ request()->routeIs('crm.reports') ? 'text-[#385776] font-medium' : 'text-gray-400' }}">Relatórios</a>
                        </div>
                    </div>
                        <a href="{{ route('crm.carteira') }}"
                           class="nav-sublink flex items-center px-4 py-2 rounded-lg text-sm transition-colors {{ request()->is('crm/*') ? 'nav-link-active' : '' }}"
                           data-tooltip="CRM">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            <span class="menu-text">CRM</span>
                        </a>
                    </div>
                </div>

                
                <!-- SIPEX Honorarios -->

                <!-- SIPEX Honorarios -->
                <a href="{{ route('precificacao.index') }}"
                   class="nav-link flex items-center px-4 py-3 rounded-lg transition-colors {{ request()->routeIs('precificacao.*') ? 'nav-link-active' : '' }}"
                   data-tooltip="SIPEX">
                    <span class="mr-3">💰</span>
                    <span class="menu-text">SIPEX </span>
                </a>

                <!-- SIRIC - Análise de Crédito -->
                <a href="{{ route('siric.index') }}"
                   class="nav-link flex items-center px-4 py-3 rounded-lg transition-colors {{ request()->routeIs('siric.*') ? 'nav-link-active' : '' }}"
                   data-tooltip="SIRIC - Análise de Crédito">
                    <span class="w-5 h-5 mr-3" aria-hidden="true">🏦</span>
                    <span class="menu-text">SIRIC</span>
                </a>

                <!-- Divisor visual -->
                <hr class="my-4 border-gray-700/70">

                <div class="pt-4 pb-2">
                    <p class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider menu-text">Administração</p>
                </div>

                @if(auth()->user()->role === 'admin')
                <!-- Sincronização Unificada (DataJuri) -->
                <a href="{{ route('admin.sincronizacao-unificada.index') }}"
                   class="nav-link flex items-center px-4 py-3 rounded-lg transition-colors {{ request()->routeIs('admin.sincronizacao-unificada.*') ? 'nav-link-active' : '' }}"
                   data-tooltip="Sincronização">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span class="menu-text">Sincronização</span>
                </a>

                <!-- Usuários (apenas admin) -->
                <a href="{{ route('admin.usuarios.index') }}"
                   class="nav-link flex items-center px-4 py-3 rounded-lg transition-colors {{ request()->routeIs('admin.usuarios.*') ? 'nav-link-active' : '' }}"
                   data-tooltip="Config. Usuários">
                    <span class="w-5 h-5 mr-3" aria-hidden="true">👤</span>
                    <span class="menu-text">Config. Usuários</span>
                </a>

                @endif

                <!-- Configurações -->


                <a href="{{ route('configuracoes.index') }}"
                   class="nav-link flex items-center px-4 py-3 rounded-lg transition-colors {{ request()->routeIs('configuracoes.*') ? 'nav-link-active' : '' }}"
                   data-tooltip="Configurações">
                    <svg class="w-5 h-5 mr-3 flex-shrink-0 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span class="menu-text">Configurações</span>
                </a>
            </nav>

            <!-- Toggle Sidebar (discreto) -->
            <button id="sidebar-toggle-btn"
                    type="button"
                    class="hidden md:flex items-center justify-center w-full py-2 text-white/30 hover:text-white/70 transition-colors"
                    aria-label="Retrair/Expandir menu lateral"
                    title="Retrair menu">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7"></path>
                </svg>
            </button>

            <!-- Usuário -->
            <div class="p-4 border-t border-white/10">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center text-white font-semibold">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-white menu-text">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-gray-400 menu-text">{{ ucfirst(auth()->user()->role) }}</p>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="mt-3">
                    @csrf
                    <button type="submit" class="nav-link w-full flex items-center justify-center px-4 py-2 text-sm rounded-lg transition-colors" data-tooltip="Sair">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        <span class="menu-text">Sair</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Overlay para menu mobile -->
        <div class="sidebar-overlay"></div>

        <!-- Main Content -->
        <main id="main-content" class="flex-1 overflow-auto w-full">
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
                <!-- Theme toggle removido - modo unico light -->
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

        // ========== SIDEBAR TOGGLE - APENAS DESKTOP ==========
        (function() {
            // Só executa em desktop (>= 768px)
            if (window.innerWidth < 768) return;

            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('sidebar-toggle-btn');
            const STORAGE_KEY = 'sidebar_collapsed';

            if (!sidebar || !toggleBtn) return;

            function applyCollapseState(collapsed) {
                if (collapsed) {
                    sidebar.classList.add('sidebar-collapsed');
                    toggleBtn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>';
                } else {
                    sidebar.classList.remove('sidebar-collapsed');
                    toggleBtn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path></svg>';
                }
                localStorage.setItem(STORAGE_KEY, collapsed.toString());
            }

            function toggleSidebar() {
                const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
                applyCollapseState(!isCollapsed);
            }

            // Inicializar com estado salvo
            const savedState = localStorage.getItem(STORAGE_KEY);
            if (savedState === 'true') {
                applyCollapseState(true);
            }

            toggleBtn.addEventListener('click', toggleSidebar);
        })();
        // ========== FIM SIDEBAR TOGGLE ==========
    </script>

    <!-- Scripts de Tema e Menu Mobile -->
    <!-- theme-toggle.js removido - modo unico light -->
    <script src="{{ asset('mobile-menu.js') }}"></script>

    @stack('scripts')
</body>
</html>