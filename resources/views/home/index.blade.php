@extends('layouts.app')

@section('title', 'Home')

@section('content')
<style>
    .home-hero { background: linear-gradient(135deg, #1B334A 0%, #385776 40%, #4A7A9B 100%); }
    .home-card {
        background: var(--surface); border: 1px solid var(--border-light);
        border-radius: var(--radius-lg); transition: all var(--transition-normal);
    }
    .home-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-2px); }
    .shortcut-slot {
        min-height: 90px; border: 2px dashed var(--border); border-radius: var(--radius-lg);
        transition: all var(--transition-normal); cursor: pointer; position: relative;
    }
    .shortcut-slot.filled { border: 1px solid var(--border-light); background: var(--surface); cursor: grab; }
    .shortcut-slot.filled:hover { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(56,87,118,0.08); }
    .shortcut-slot.filled:active { cursor: grabbing; }
    .shortcut-slot.drag-over { border-color: var(--primary) !important; background: rgba(56,87,118,0.04); box-shadow: 0 0 0 3px rgba(56,87,118,0.12); }
    .shortcut-slot .remove-btn {
        position: absolute; top: 6px; right: 6px; width: 22px; height: 22px; border-radius: 50%;
        background: rgba(220,38,38,0.08); color: #DC2626; display: none; align-items: center;
        justify-content: center; font-size: 12px; cursor: pointer; border: none; padding: 0; line-height: 1;
    }
    .shortcut-slot:hover .remove-btn { display: flex; }
    .shortcut-slot .remove-btn:hover { background: rgba(220,38,38,0.18); }
    .modal-overlay {
        position: fixed; inset: 0; background: rgba(27,51,74,0.5); backdrop-filter: blur(4px);
        z-index: 9998; display: flex; align-items: center; justify-content: center;
        opacity: 0; pointer-events: none; transition: opacity 200ms;
    }
    .modal-overlay.active { opacity: 1; pointer-events: all; }
    .modal-box {
        background: white; border-radius: var(--radius-xl); width: 540px; max-width: 95vw;
        max-height: 80vh; overflow: hidden; box-shadow: var(--shadow-xl);
        transform: translateY(20px); transition: transform 200ms;
    }
    .modal-overlay.active .modal-box { transform: translateY(0); }
    .module-pick {
        padding: 10px 14px; border-radius: var(--radius-md); cursor: pointer;
        border: 1px solid var(--border-light); transition: all 150ms;
        display: flex; align-items: center; gap: 10px;
    }
    .module-pick:hover { border-color: var(--primary); background: rgba(56,87,118,0.03); }
    .module-pick.selected { border-color: var(--primary); background: rgba(56,87,118,0.06); }
    @keyframes fadeInUp { from { opacity:0; transform: translateY(12px); } to { opacity:1; transform: translateY(0); } }
    .anim-card { animation: fadeInUp 0.4s ease-out both; }
    .anim-card:nth-child(2) { animation-delay: 0.06s; }
    .anim-card:nth-child(3) { animation-delay: 0.12s; }
    .anim-card:nth-child(4) { animation-delay: 0.18s; }
    .anim-card:nth-child(5) { animation-delay: 0.24s; }
    .score-ring {
        width: 96px; height: 96px; border-radius: 50%;
        background: conic-gradient(var(--clr) calc(var(--pct) * 3.6deg), rgba(255,255,255,0.12) 0);
        display: flex; align-items: center; justify-content: center;
    }
    .score-ring-inner {
        width: 74px; height: 74px; border-radius: 50%;
        background: linear-gradient(135deg, #1B334A, #2d475f);
        display: flex; align-items: center; justify-content: center; flex-direction: column;
    }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 py-5" x-data="homeApp()">

    {{-- ====== HERO HEADER ====== --}}
    <div class="home-hero rounded-2xl p-6 sm:p-8 mb-5 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 rounded-full opacity-[0.04] -mr-16 -mt-16" style="background:white"></div>
        <div class="absolute bottom-0 left-0 w-32 h-32 rounded-full opacity-[0.04] -ml-8 -mb-8" style="background:white"></div>
        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">{{ $saudacao }}, {{ $primeiroNome }}</h1>
                <p class="text-white/50 text-sm mt-1">{{ now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</p>
            </div>
            <div class="flex items-center gap-5 text-xs text-white/40">
                <div class="text-center"><span class="block text-lg font-black text-white">{{ number_format($volumetria['clientes']) }}</span>clientes</div>
                <div class="w-px h-8 bg-white/10"></div>
                <div class="text-center"><span class="block text-lg font-black text-white">{{ $volumetria['processos'] }}</span>processos</div>
                <div class="w-px h-8 bg-white/10"></div>
                <div class="text-center"><span class="block text-lg font-black text-white">{{ $volumetria['oportunidades'] }}</span>oportunidades</div>
            </div>
        </div>

    </div>

    {{-- ====== BUSCA GLOBAL (fora do hero para z-index funcionar) ====== --}}
    <div class="relative mb-5 -mt-3" x-data="buscaGlobal()">
        <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </span>
            <input type="text" x-model="query" x-on:input.debounce.300ms="buscar()"
                x-on:focus="aberto = query.length >= 2" x-on:click.away="aberto = false" x-on:keydown.escape="aberto = false"
                placeholder="Buscar cliente, processo, lead ou conta CRM..."
                class="w-full pl-11 pr-10 py-3.5 rounded-2xl border-2 border-gray-100 bg-white text-sm shadow-sm focus:outline-none focus:border-blue-300 focus:shadow-lg focus:shadow-blue-50 transition-all"
                style="color: var(--text);"
                autocomplete="off">
            <span x-show="loading" class="absolute inset-y-0 right-0 flex items-center pr-4">
                <svg class="animate-spin h-4 w-4 text-blue-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </span>
        </div>
        <div x-show="aberto && resultados.length > 0" x-transition.opacity
            class="absolute z-50 w-full mt-2 bg-white rounded-xl shadow-2xl border border-gray-100 max-h-72 overflow-y-auto">
            <template x-for="item in resultados" :key="item.tipo + item.titulo + item.subtitulo">
                <a :href="baseUrl + '/' + item.url.replace(/^\//, '')" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition border-b border-gray-50 last:border-0">
                    <span class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-white text-[10px] font-bold" style="background:linear-gradient(135deg,#385776,#1B334A)" x-text="item.badge.charAt(0)"></span>
                    <div class="flex-1 min-w-0">
                        <span class="font-semibold text-sm text-gray-900 truncate block" x-text="item.titulo"></span>
                        <span class="text-xs text-gray-400 truncate block" x-text="item.subtitulo"></span>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md" :class="item.badge_cor" x-text="item.badge"></span>
                </a>
            </template>
        </div>
        <div x-show="aberto && resultados.length === 0 && !loading && query.length >= 2" x-transition
            class="absolute z-50 w-full mt-2 bg-white rounded-xl shadow-2xl p-6 text-center">
            <p class="text-sm text-gray-400">Nenhum resultado para "<span class="font-medium text-gray-600" x-text="query"></span>"</p>
        </div>
    </div>

    {{-- ====== ATALHOS PERSONALIZAVEIS ====== --}}
    <div class="mb-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-bold uppercase tracking-wider" style="color:var(--text-secondary)">
                <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Meus Atalhos
            </h2>
            <button @click="showModal = true" class="text-xs font-semibold px-3 py-1.5 rounded-lg" style="color:var(--primary);background:rgba(56,87,118,0.06)">
                <svg class="w-3.5 h-3.5 inline -mt-0.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Configurar
            </button>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <template x-for="(slot, idx) in slots" :key="'s'+idx">
                <div class="shortcut-slot anim-card" :class="slot.slug ? 'filled' : ''"
                     :draggable="slot.slug ? 'true' : 'false'"
                     @dragstart="onDragStart($event, idx)" @dragover.prevent="onDragOver($event)" @dragleave="onDragLeave($event)" @drop.prevent="onDrop($event, idx)"
                     @click="slot.slug ? goTo(slot.rota) : (showModal = true)">
                    <template x-if="slot.slug">
                        <div class="flex flex-col items-center justify-center h-full py-4 px-3 text-center">
                            <button class="remove-btn" @click.stop="removeSlot(idx)">&times;</button>
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-2" :style="'background:linear-gradient(135deg,'+palette(slot.grupo,0)+','+palette(slot.grupo,1)+')'">
                                <span class="text-white font-bold text-sm" x-text="emoji(slot.nome)"></span>
                            </div>
                            <span class="text-xs font-semibold truncate w-full" style="color:var(--text)" x-text="slot.nome"></span>
                            <span class="text-[10px] mt-0.5 truncate w-full" style="color:var(--text-muted)" x-text="grupoLabel(slot.grupo)"></span>
                        </div>
                    </template>
                    <template x-if="!slot.slug">
                        <div class="flex flex-col items-center justify-center h-full py-4 px-3 cursor-pointer">
                            <div class="w-11 h-11 rounded-xl bg-gray-50 flex items-center justify-center mb-2">
                                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </div>
                            <span class="text-xs text-gray-300 font-medium">Adicionar</span>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- ====== PAINEL COMERCIAL ====== --}}
    @if(auth()->user()->role === 'admin' || auth()->user()->role === 'socio')
    <div class="mb-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-widest">📊 Painel Comercial — {{ now()->locale('pt_BR')->isoFormat('MMMM [de] YYYY') }}</h2>
            <a href="{{ url('/crm') }}" class="text-xs text-blue-500 hover:underline font-medium">Ver CRM →</a>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            {{-- Leads novos no mês --}}
            <div class="home-card p-4 anim-card">
                <p class="text-xs text-gray-400 font-medium mb-1">Leads no mês</p>
                <p class="text-3xl font-black text-[#1B334A]">{{ $painelComercial['leadsTotal'] }}</p>
                <div class="flex flex-wrap gap-1 mt-2">
                    <span class="text-[10px] px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded font-medium">{{ $painelComercial['leadsNovos'] }} novos</span>
                    <span class="text-[10px] px-1.5 py-0.5 bg-yellow-50 text-yellow-600 rounded font-medium">{{ $painelComercial['leadsContatados'] }} contato</span>
                    <span class="text-[10px] px-1.5 py-0.5 bg-red-50 text-red-500 rounded font-medium">{{ $painelComercial['leadsDescart'] }} descart.</span>
                </div>
            </div>
            {{-- Conversão --}}
            <div class="home-card p-4 anim-card">
                <p class="text-xs text-gray-400 font-medium mb-1">Taxa de conversão</p>
                <p class="text-3xl font-black {{ $painelComercial['taxaConversao'] >= 10 ? 'text-emerald-600' : 'text-amber-500' }}">{{ $painelComercial['taxaConversao'] }}%</p>
                <p class="text-xs text-gray-400 mt-2">{{ $painelComercial['leadsConvert'] }} convertidos de {{ $painelComercial['leadsTotal'] }}</p>
            </div>
            {{-- Ganhos no mês --}}
            <div class="home-card p-4 anim-card">
                <p class="text-xs text-gray-400 font-medium mb-1">Ganhos no mês</p>
                <p class="text-3xl font-black text-emerald-600">{{ $painelComercial['ganhosMes']->total ?? 0 }}</p>
                @if(($painelComercial['ganhosMes']->valor ?? 0) > 0)
                <p class="text-xs text-emerald-500 mt-2 font-medium">R$ {{ number_format($painelComercial['ganhosMes']->valor, 0, ',', '.') }}</p>
                @else
                <p class="text-xs text-gray-300 mt-2">Nenhum registro</p>
                @endif
            </div>
            {{-- Clientes ativos --}}
            <div class="home-card p-4 anim-card">
                <p class="text-xs text-gray-400 font-medium mb-1">Base de clientes</p>
                <p class="text-3xl font-black text-[#1B334A]">{{ $painelComercial['clientesAtivos'] }}</p>
                <div class="flex flex-wrap gap-1 mt-2">
                    <span class="text-[10px] px-1.5 py-0.5 bg-purple-50 text-purple-600 rounded font-medium">{{ $painelComercial['clientesOnboarding'] }} onboard</span>
                    @if($painelComercial['adormSemContato'] > 0)
                    <span class="text-[10px] px-1.5 py-0.5 bg-red-50 text-red-500 rounded font-medium">⚠ {{ $painelComercial['adormSemContato'] }} adorm. 30d+</span>
                    @endif
                </div>
            </div>
        </div>
        {{-- Pipeline --}}
        <div class="home-card p-4 anim-card">
            <p class="text-xs text-gray-400 font-medium mb-3">Pipeline CRM — oportunidades em aberto</p>
            <div class="flex items-end gap-2 overflow-x-auto pb-1">
                @php $maxVal = max(array_map(fn($s) => $s['total'], $painelComercial['pipeline']) ?: [1]); @endphp
            @foreach($painelComercial['pipeline'] as $stage)
                <div class="flex flex-col items-center min-w-[80px] flex-1">
                    <span class="text-xs font-black text-[#1B334A] mb-1">{{ $stage['total'] }}</span>
                    <div class="w-full rounded-t-md transition-all" style="height: {{ max(4, intval(($stage['total'] / $maxVal) * 60)) }}px; background: linear-gradient(180deg,#385776,#1B334A)"></div>
                    <span class="text-[10px] text-gray-400 mt-1 text-center leading-tight">{{ $stage['nome'] }}</span>
                    @if($stage['valor'] > 0)
                    <span class="text-[9px] text-emerald-500 font-medium">R$ {{ number_format($stage['valor']/1000, 0) }}k</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ====== CARDS: GDP + FINANCEIRO + AVISOS ====== --}}
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-4">

        {{-- GDP Score --}}
        <a href="{{ url('/gdp') }}" class="lg:col-span-3 home-card block relative overflow-hidden p-6 group" style="background:linear-gradient(135deg,#1B334A 0%,#385776 50%,#4A7A9B 100%);border-color:transparent">
            <div class="absolute top-0 right-0 w-40 h-40 rounded-full opacity-5 -mr-10 -mt-10" style="background:white"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-white text-base">Meu Score GDP</h3>
                            @if($gdpScore)<p class="text-white/50 text-xs">Ref: {{ $gdpScore['mes_ref'] }}</p>@endif
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-white/30 group-hover:text-white/60 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7v10"/></svg>
                </div>
                @if($gdpScore)
                <div class="flex items-center gap-6 mb-5">
                    <div class="score-ring" style="--pct:{{ min($gdpScore['score_total'],100) }};--clr:{{ $gdpScore['score_total'] >= 70 ? '#34D399' : ($gdpScore['score_total'] >= 40 ? '#FBBF24' : '#F87171') }}">
                        <div class="score-ring-inner">
                            <span class="text-2xl font-black text-white leading-none">{{ $gdpScore['score_total'] }}</span>
                            <span class="text-[10px] text-white/40">/100</span>
                        </div>
                    </div>
                    <div class="flex-1 space-y-2">
                        @if($gdpScore['variacao'] !== null)
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-bold {{ $gdpScore['variacao'] >= 0 ? 'bg-emerald-400/20 text-emerald-300' : 'bg-red-400/20 text-red-300' }}">
                            {{ $gdpScore['variacao'] >= 0 ? '+' : '' }}{{ $gdpScore['variacao'] }} pts
                        </span>
                        @endif
                        @if($gdpScore['ranking'])
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white/10 ml-2">
                            <span class="text-amber-300 text-xs">&#9733;</span>
                            <span class="text-white font-bold text-sm">{{ $gdpScore['ranking'] }}&#186;</span>
                            <span class="text-white/40 text-xs">de {{ $gdpScore['total_participantes'] }}</span>
                        </div>
                        @endif
                    </div>
                </div>
                <div class="grid grid-cols-4 gap-3">
                    @php $eixos = [
                        ['l'=>'JUR','s'=>$gdpScore['score_juridico']],
                        ['l'=>'FIN','s'=>$gdpScore['score_financeiro']],
                        ['l'=>'DEV','s'=>$gdpScore['score_desenvolvimento']],
                        ['l'=>'ATE','s'=>$gdpScore['score_atendimento']],
                    ]; @endphp
                    @foreach($eixos as $e)
                    <div>
                        <div class="flex justify-between mb-1"><span class="text-[10px] font-bold text-white/50 uppercase tracking-wider">{{ $e['l'] }}</span><span class="text-[11px] font-bold text-white">{{ $e['s'] }}</span></div>
                        <div class="w-full bg-white/10 rounded-full h-1.5"><div class="h-1.5 rounded-full {{ $e['s'] >= 70 ? 'bg-emerald-400' : ($e['s'] >= 40 ? 'bg-amber-400' : 'bg-red-400') }}" style="width:{{ min($e['s'],100) }}%"></div></div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-8"><p class="text-white/40 text-sm">Aguardando primeira apuracao</p></div>
                @endif
            </div>
        </a>

        {{-- Coluna direita: Financeiro + Avisos --}}
        <div class="lg:col-span-2 grid grid-cols-1 gap-4">
            {{-- Financeiro --}}
            <a href="{{ url('/visao-gerencial') }}" class="home-card block p-5 group">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h3 class="font-bold text-sm" style="color:var(--navy)">Financeiro do Mes</h3>
                    </div>
                    <svg class="w-3.5 h-3.5 text-gray-200 group-hover:text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7v10"/></svg>
                </div>
                <div class="space-y-2.5">
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-400">Receita</span>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-emerald-600 text-sm">R$ {{ number_format($resumoFinanceiro['receita'],0,',','.') }}</span>
                            @if($resumoFinanceiro['var_receita'] !== null)
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $resumoFinanceiro['var_receita'] >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500' }}">{{ $resumoFinanceiro['var_receita'] >= 0 ? '+' : '' }}{{ $resumoFinanceiro['var_receita'] }}%</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-400">Despesas</span>
                        <span class="font-bold text-red-500 text-sm">R$ {{ number_format($resumoFinanceiro['despesa'],0,',','.') }}</span>
                    </div>
                    <div class="h-px bg-gray-100"></div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-semibold text-gray-600">Resultado</span>
                        <span class="font-black text-base {{ $resumoFinanceiro['resultado'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">R$ {{ number_format($resumoFinanceiro['resultado'],0,',','.') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-gray-100 rounded-full h-1.5"><div class="h-1.5 rounded-full {{ $resumoFinanceiro['margem'] >= 30 ? 'bg-emerald-500' : ($resumoFinanceiro['margem'] >= 10 ? 'bg-amber-400' : 'bg-red-400') }}" style="width:{{ min(max($resumoFinanceiro['margem'],0),100) }}%"></div></div>
                        <span class="text-xs font-bold text-gray-500">{{ $resumoFinanceiro['margem'] }}%</span>
                    </div>
                </div>
            </a>
            {{-- Avisos --}}
            <a href="{{ url('/avisos') }}" class="home-card block p-5 group">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl {{ $avisos['total'] > 0 ? 'bg-red-50' : 'bg-gray-50' }} flex items-center justify-center">
                            <svg class="w-4 h-4 {{ $avisos['total'] > 0 ? 'text-red-500' : 'text-gray-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </div>
                        <h3 class="font-bold text-sm" style="color:var(--navy)">Avisos</h3>
                        @if($avisos['total'] > 0)
                        <span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full bg-red-500 text-white text-[10px] font-black">{{ $avisos['total'] }}</span>
                        @endif
                    </div>
                    <svg class="w-3.5 h-3.5 text-gray-200 group-hover:text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7v10"/></svg>
                </div>
                @if(count($avisos['avisos']) > 0)
                <div class="space-y-1.5">
                    @foreach(array_slice($avisos['avisos'], 0, 3) as $av)
                    <div class="flex items-center gap-2 text-xs">
                        @if($av['destaque'])<span class="text-amber-400 text-[9px]">&#9733;</span>@else<span class="w-1.5 h-1.5 rounded-full bg-gray-200 flex-shrink-0"></span>@endif
                        <span class="text-gray-600 truncate flex-1">{{ $av['titulo'] }}</span>
                        <span class="text-gray-300 flex-shrink-0">{{ $av['data'] }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-xs text-gray-300 text-center py-2">&#10003; Tudo lido</p>
                @endif
            </a>
        </div>
    </div>

    {{-- ====== CARDS: CRM Alertas + Tickets + Solicitacoes ====== --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

        {{-- Alertas CRM --}}
        <div class="home-card p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    </div>
                    <h3 class="font-bold text-sm" style="color:var(--navy)">Alertas CRM</h3>
                    @if(count($alertasCrm) > 0)<span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-black">{{ count($alertasCrm) }}</span>@endif
                </div>
                <a href="{{ url('/crm') }}" class="text-[11px] font-bold uppercase tracking-wider hover:underline" style="color:var(--primary)">CRM &rarr;</a>
            </div>
            @if(count($alertasCrm) > 0)
            <div class="space-y-1.5 max-h-48 overflow-y-auto">
                @foreach($alertasCrm as $al)
                <a href="{{ url($al['url'] ?? '/crm') }}" class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-amber-50/50 transition">
                    <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-gray-800 truncate">{{ $al['titulo'] }}</p>
                        <p class="text-[10px] text-gray-400">{{ $al['descricao'] }}</p>
                    </div>
                </a>
                @endforeach
            </div>
            @else
            <div class="text-center py-5">
                <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-emerald-50 flex items-center justify-center"><svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>
                <p class="text-xs text-gray-300">Carteira em dia</p>
            </div>
            @endif
        </div>

        {{-- Tickets --}}
        <div class="home-card p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    </div>
                    <h3 class="font-bold text-sm" style="color:var(--navy)">Tickets Abertos</h3>
                    @if(count($ticketsAbertos) > 0)<span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-black">{{ count($ticketsAbertos) }}</span>@endif
                </div>
                <a href="{{ url('/nexo/tickets') }}" class="text-[11px] font-bold uppercase tracking-wider hover:underline" style="color:var(--primary)">Tickets &rarr;</a>
            </div>
            @if(count($ticketsAbertos) > 0)
            <div class="space-y-1.5 max-h-48 overflow-y-auto">
                @foreach($ticketsAbertos as $tk)
                <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-indigo-50/50 transition">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-[10px] font-black @if(($tk['prioridade'] ?? '') === 'alta') bg-red-100 text-red-600 @elseif(($tk['prioridade'] ?? '') === 'media') bg-amber-100 text-amber-600 @else bg-gray-100 text-gray-400 @endif">#{{ $tk['id'] }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-gray-800 truncate">{{ $tk['titulo'] }}</p>
                        <p class="text-[10px] text-gray-400">{{ $tk['criado_em'] }}</p>
                    </div>
                    @if(($tk['prioridade'] ?? '') === 'alta')<span class="text-[9px] font-black text-red-500 bg-red-50 px-1.5 py-0.5 rounded-md uppercase">Urgente</span>@endif
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-5">
                <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-emerald-50 flex items-center justify-center"><svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>
                <p class="text-xs text-gray-300">Nenhum ticket aberto</p>
            </div>
            @endif
        </div>

        {{-- Solicitacoes --}}
        <div class="home-card p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-violet-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    </div>
                    <h3 class="font-bold text-sm" style="color:var(--navy)">Solicitacoes</h3>
                    @if($solicitacoes['total_abertas'] > 0)<span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full bg-violet-100 text-violet-700 text-[10px] font-black">{{ $solicitacoes['total_abertas'] }}</span>@endif
                </div>
                <a href="{{ url('/crm') }}" class="text-[11px] font-bold uppercase tracking-wider hover:underline" style="color:var(--primary)">CRM &rarr;</a>
            </div>
            <div class="flex items-center gap-3 mb-4">
                <div class="flex-1 text-center py-2 rounded-xl bg-violet-50"><span class="block text-lg font-black text-violet-700">{{ $solicitacoes['total_abertas'] }}</span><span class="text-[10px] text-violet-500 font-medium">Abertas</span></div>
                <div class="flex-1 text-center py-2 rounded-xl bg-gray-50"><span class="block text-lg font-black text-gray-500">{{ $solicitacoes['total_concluidas'] }}</span><span class="text-[10px] text-gray-400 font-medium">Concluidas</span></div>
            </div>
            @if(count($solicitacoes['items']) > 0)
            <div class="space-y-1.5 max-h-36 overflow-y-auto">
                @foreach($solicitacoes['items'] as $sr)
                <a href="{{ url('/crm/solicitacoes/' . $sr['id']) }}" class="flex items-center gap-3 p-2 rounded-xl hover:bg-violet-50/50 transition">
                    <span class="w-7 h-7 rounded-lg flex items-center justify-center text-[9px] font-black @if($sr['priority']==='urgente') bg-red-100 text-red-600 @elseif($sr['priority']==='alta') bg-orange-100 text-orange-600 @else bg-violet-100 text-violet-500 @endif">#{{ $sr['id'] }}</span>
                    <div class="flex-1 min-w-0"><p class="text-xs font-semibold text-gray-800 truncate">{{ $sr['subject'] }}</p></div>
                    @php
                        $sB = match($sr['status']) { 'aberto'=>'bg-blue-50 text-blue-600','em_andamento'=>'bg-yellow-50 text-yellow-600','aguardando_aprovacao'=>'bg-purple-50 text-purple-600','aprovado'=>'bg-green-50 text-green-600',default=>'bg-gray-50 text-gray-500' };
                        $sL = match($sr['status']) { 'aberto'=>'Aberto','em_andamento'=>'Andamento','aguardando_aprovacao'=>'Aprovacao','aprovado'=>'Aprovado',default=>$sr['status'] };
                    @endphp
                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-md {{ $sB }}">{{ $sL }}</span>
                </a>
                @endforeach
            </div>
            @else
            <div class="text-center py-3">
                <p class="text-xs text-gray-300">Nenhuma solicitacao aberta</p>
            </div>
            @endif
        </div>
    </div>

    {{-- ====== MODAL DE SELECAO DE ATALHOS ====== --}}
    <div class="modal-overlay" :class="showModal ? 'active' : ''" @click.self="showModal = false">
        <div class="modal-box">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-base" style="color:var(--navy)">Configurar Atalhos</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Escolha ate 5 modulos para acesso rapido. <span class="font-semibold" x-text="selectedCount + '/5'"></span></p>
                </div>
                <button @click="showModal = false" class="w-8 h-8 rounded-lg flex items-center justify-center hover:bg-gray-100 transition text-gray-400">&times;</button>
            </div>
            <div class="px-6 py-3 border-b border-gray-50">
                <input type="text" x-model="moduleSearch" placeholder="Filtrar modulos..." class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm focus:outline-none focus:border-blue-300">
            </div>
            <div class="px-6 py-4 max-h-[50vh] overflow-y-auto space-y-2">
                <template x-for="mod in filteredModules" :key="mod.slug">
                    <div class="module-pick" :class="isSelected(mod.slug) ? 'selected' : ''" @click="toggleModule(mod)">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" :style="'background:linear-gradient(135deg,'+palette(mod.grupo,0)+','+palette(mod.grupo,1)+')'">
                            <span class="text-white font-bold text-xs" x-text="emoji(mod.nome)"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="text-sm font-semibold block truncate" style="color:var(--text)" x-text="mod.nome"></span>
                            <span class="text-[10px]" style="color:var(--text-muted)" x-text="grupoLabel(mod.grupo)"></span>
                        </div>
                        <div class="w-5 h-5 rounded border-2 flex items-center justify-center flex-shrink-0 transition-all"
                             :class="isSelected(mod.slug) ? 'border-[#385776] bg-[#385776]' : 'border-gray-300'">
                            <svg x-show="isSelected(mod.slug)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </div>
                    </div>
                </template>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                <button @click="showModal = false" class="px-4 py-2 rounded-lg text-sm font-semibold text-gray-500 hover:bg-gray-50 transition">Cancelar</button>
                <button @click="saveShortcuts()" class="px-5 py-2 rounded-lg text-sm font-bold text-white transition" style="background:var(--primary)" :disabled="saving">
                    <span x-show="!saving">Salvar</span>
                    <span x-show="saving">Salvando...</span>
                </button>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
function buscaGlobal() {
    return {
        query: '', resultados: [], aberto: false, loading: false,
        baseUrl: '{{ url("/") }}',
        async buscar() {
            if (this.query.length < 2) { this.resultados = []; this.aberto = false; return; }
            this.loading = true;
            try {
                const r = await fetch(this.baseUrl + '/home/buscar?q=' + encodeURIComponent(this.query));
                this.resultados = await r.json();
                this.aberto = true;
            } catch(e) { this.resultados = []; } finally { this.loading = false; }
        }
    };
}

function homeApp() {
    return {
        showModal: false,
        saving: false,
        moduleSearch: '',
        dragIdx: null,
        allModules: @json($availableModules),
        slots: [],

        init() {
            const saved = @json($shortcuts);
            this.slots = [];
            for (let i = 0; i < 5; i++) {
                const s = saved.find(x => x.posicao === i + 1);
                this.slots.push(s ? { slug: s.slug, nome: s.nome, icone: s.icone, rota: s.rota, grupo: s.grupo } : { slug: '', nome: '', icone: '', rota: '', grupo: '' });
            }
        },

        get selectedCount() { return this.slots.filter(s => s.slug).length; },

        get filteredModules() {
            let m = this.allModules;
            if (this.moduleSearch.trim()) {
                const q = this.moduleSearch.toLowerCase();
                m = m.filter(x => x.nome.toLowerCase().includes(q) || (x.grupo||'').toLowerCase().includes(q));
            }
            return m;
        },

        isSelected(slug) { return this.slots.some(s => s.slug === slug); },

        toggleModule(mod) {
            const idx = this.slots.findIndex(s => s.slug === mod.slug);
            if (idx >= 0) {
                this.slots[idx] = { slug: '', nome: '', icone: '', rota: '', grupo: '' };
            } else {
                if (this.selectedCount >= 5) return;
                const empty = this.slots.findIndex(s => !s.slug);
                if (empty >= 0) {
                    this.slots[empty] = { slug: mod.slug, nome: mod.nome, icone: mod.icone, rota: mod.rota, grupo: mod.grupo };
                }
            }
        },

        removeSlot(idx) {
            this.slots[idx] = { slug: '', nome: '', icone: '', rota: '', grupo: '' };
            this.saveShortcuts();
        },

        async saveShortcuts() {
            this.saving = true;
            const slugs = this.slots.filter(s => s.slug).map(s => s.slug);
            try {
                const r = await fetch('{{ url("/home/shortcuts") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ slugs })
                });
                await r.json();
            } catch(e) { console.error('Erro ao salvar atalhos:', e); }
            this.saving = false;
            this.showModal = false;
        },

        goTo(rota) {
            if (!rota) return;
            const routeMap = {
        'nexo.atendimento': '/nexo/atendimento',
        'nexo.gerencial': '/nexo/gerencial',
        'siric.index': '/siric',
        'precificacao.index': '/precificacao',
        'leads.index': '/crm/leads',
        'manuais-normativos.index': '/manuais',
        'avisos.index': '/avisos',
        'admin.avisos.index': '/admin/avisos',
        'minha-performance': '/gdp',
        'gdp.minha-performance': '/gdp',
        'equipe': '/gdp/equipe',
        'configurar-metas': '/gdp/acordo',
        'visao-gerencial': '/visao-gerencial',
        'clientes-mercado': '/clientes-mercado',
        'resultados.bsc.processos-internos.index': '/processos-internos',
        'admin.metas-kpi-mensais': '/admin/metas-kpi-mensais',
        'admin.usuarios.index': '/admin/usuarios',
        'admin.sincronizacao-unificada.index': '/admin/sincronizacao-unificada',
        'integration.index': '/admin/integracoes',
        'configuracoes': '/admin/configuracoes',
        'admin.classificacao.index': '/admin/classificacao',
        '/vigilia': '/vigilia',
            };
            const url = routeMap[rota] || '/' + rota.replace(/^\//,'');
            window.location.href = '{{ url("/") }}' + url;
        },

        // Drag and drop
        onDragStart(e, idx) { this.dragIdx = idx; e.dataTransfer.effectAllowed = 'move'; },
        onDragOver(e) { e.currentTarget.classList.add('drag-over'); },
        onDragLeave(e) { e.currentTarget.classList.remove('drag-over'); },
        onDrop(e, targetIdx) {
            e.currentTarget.classList.remove('drag-over');
            if (this.dragIdx === null || this.dragIdx === targetIdx) return;
            const tmp = { ...this.slots[targetIdx] };
            this.slots[targetIdx] = { ...this.slots[this.dragIdx] };
            this.slots[this.dragIdx] = tmp;
            this.dragIdx = null;
            this.saveShortcuts();
        },

        palette(grupo, idx) {
            const map = {
                'resultados': ['#385776','#1B334A'],
                'operacional': ['#0D9467','#065F46'],
                'gdp': ['#B45309','#92400E'],
                'admin': ['#6B21A8','#4C1D95'],
                'avisos': ['#DC2626','#991B1B'],
                'vigilia': ['#0369A1','#075985'],
            };
            const key = (grupo || '').toLowerCase().split('.')[0];
            return (map[key] || ['#385776','#1B334A'])[idx];
        },

        grupoLabel(grupo) {
            const map = { 'resultados':'Dashboards', 'operacional':'Operacional', 'gdp':'Performance', 'admin':'Admin', 'avisos':'Comunicacao', 'vigilia':'Monitoramento' };
            const key = (grupo || '').toLowerCase().split('.')[0];
            return map[key] || grupo || '';
        },

        emoji(nome) {
            const n = (nome || '').toLowerCase();
            if (n.includes('whatsapp') || n.includes('nexo aten')) return 'WA';
            if (n.includes('pipeline') || n.includes('oportunid')) return 'OP';
            if (n.includes('lead')) return 'LD';
            if (n.includes('financ') || n.includes('visao')) return 'FN';
            if (n.includes('crm') || n.includes('carteira')) return 'CR';
            if (n.includes('gdp') || n.includes('performance') || n.includes('equipe')) return 'GP';
            if (n.includes('sipex') || n.includes('precif')) return 'SP';
            if (n.includes('siric')) return 'SI';
            if (n.includes('justus')) return 'JU';
            if (n.includes('bsc') || n.includes('insight')) return 'BS';
            if (n.includes('ticket')) return 'TK';
            if (n.includes('notific')) return 'NT';
            if (n.includes('template')) return 'TM';
            if (n.includes('aviso')) return 'AV';
            if (n.includes('qualidade')) return 'QA';
            if (n.includes('vigil')) return 'VG';
            if (n.includes('manual')) return 'MN';
            if (n.includes('usuario')) return 'US';
            if (n.includes('sync') || n.includes('sincron')) return 'SY';
            if (n.includes('config')) return 'CF';
            if (n.includes('eval') || n.includes('180')) return 'EV';
            if (n.includes('sisrh') || n.includes('folha')) return 'RH';
            if (n.includes('relat')) return 'RL';
            if (n.includes('evidentia')) return 'EV';
            return nome ? nome.substring(0, 2).toUpperCase() : '??';
        }
    };
}
</script>
@endpush
@endsection
