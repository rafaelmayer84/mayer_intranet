@extends('layouts.public')

@section('title', 'Acompanhamento Processual — Mayer Advogados')

@section('content')

{{-- ── HERO: identificação do processo ── --}}
<div class="bg-navy-700 rounded-2xl overflow-hidden mb-6 shadow-md">
    {{-- Faixa dourada topo --}}
    <div class="h-1 bg-gradient-to-r from-gold-700 via-gold-300 to-gold-700"></div>

    <div class="px-6 py-6">
        {{-- Rótulo --}}
        <div class="flex items-center gap-2 mb-3">
            <svg class="w-4 h-4 text-gold-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
            </svg>
            <span class="text-gold-300 text-xs font-semibold tracking-widest uppercase">Processo Judicial</span>
        </div>

        {{-- Número do processo --}}
        <h1 class="font-serif text-white text-xl sm:text-2xl font-bold leading-tight mb-1">
            {{ $processo_pasta ?? '—' }}
        </h1>

        @if(!empty($processo_adverso))
        <p class="text-white/60 text-sm">
            {{ $nome_cliente ?? 'Você' }} <span class="text-gold-400 mx-1">×</span> {{ $processo_adverso }}
        </p>
        @endif

        {{-- Status + metadados --}}
        <div class="flex flex-wrap items-center gap-3 mt-4 pt-4 border-t border-white/10">
            @if(!empty($processo_status))
            <span class="badge
                @if(str_contains(strtolower($processo_status ?? ''), 'ativo') || str_contains(strtolower($processo_status ?? ''), 'andamento'))
                    bg-emerald-400/20 text-emerald-300 ring-1 ring-emerald-400/30
                @elseif(str_contains(strtolower($processo_status ?? ''), 'encerr') || str_contains(strtolower($processo_status ?? ''), 'arquiv'))
                    bg-white/10 text-white/50
                @else
                    bg-gold-500/20 text-gold-300 ring-1 ring-gold-500/30
                @endif">
                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                {{ $processo_status }}
            </span>
            @endif

            @if(!empty($area_atuacao))
            <span class="text-white/40 text-xs">{{ $area_atuacao }}</span>
            @endif

            @if(!empty($tipo_acao))
            <span class="text-white/40 text-xs hidden sm:inline">{{ $tipo_acao }}</span>
            @endif
        </div>
    </div>
</div>

{{-- ── GRID: info + resumo ── --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-6">

    {{-- Advogado responsável --}}
    @if(!empty($advogado_responsavel))
    <div class="card flex flex-col gap-1">
        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Advogado responsável</p>
        <div class="flex items-center gap-2 mt-1">
            <div class="w-8 h-8 rounded-full bg-navy-700 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-gold-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-navy-700 leading-tight">{{ $advogado_responsavel }}</p>
        </div>
    </div>
    @endif

    {{-- Área de atuação --}}
    @if(!empty($area_atuacao))
    <div class="card flex flex-col gap-1">
        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Área</p>
        <div class="flex items-center gap-2 mt-1">
            <div class="w-8 h-8 rounded-full bg-navy-50 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-navy-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-navy-700 leading-tight">{{ $area_atuacao }}</p>
        </div>
    </div>
    @endif

    {{-- Consultado em --}}
    <div class="card flex flex-col gap-1">
        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Última consulta</p>
        <div class="flex items-center gap-2 mt-1">
            <div class="w-8 h-8 rounded-full bg-navy-50 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-navy-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-navy-700 leading-tight">{{ $consultadoEm }}</p>
        </div>
    </div>
</div>

{{-- ── RESUMO DA IA ── --}}
@php
    // Remover a URL do resumo se vier embutida (exibimos separada)
    $resumoLimpo = preg_replace('/\n\n?🔗\s*https?:\/\/\S+/', '', $resumo ?? '');
    $resumoLimpo = trim($resumoLimpo);
@endphp
@if(!empty($resumoLimpo))
<div class="card mb-6 border-l-4 border-gold-500 rounded-l-none">
    <div class="flex items-center gap-2 mb-4">
        <div class="w-8 h-8 rounded-full bg-gold-50 border border-gold-200 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-gold-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
            </svg>
        </div>
        <div>
            <p class="text-sm font-semibold text-gray-800">Atualização do escritório</p>
            <p class="text-xs text-gray-400">Resumo em linguagem acessível</p>
        </div>
    </div>
    <p class="text-sm text-gray-700 leading-relaxed">{{ $resumoLimpo }}</p>
</div>
@endif

{{-- ── TIMELINE DE ANDAMENTOS ── --}}
@if(!empty($andamentos) && count($andamentos) > 0)
<div class="card p-0 overflow-hidden mb-6">

    {{-- Cabeçalho --}}
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-navy-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-800">Movimentações processuais</h2>
        </div>
        <span class="text-xs text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full">
            {{ count($andamentos) }} registro{{ count($andamentos) > 1 ? 's' : '' }}
        </span>
    </div>

    {{-- Itens --}}
    <div class="divide-y divide-gray-50">
        @foreach($andamentos as $i => $and)
        <div class="flex gap-5 px-6 py-5" x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }">
            {{-- Coluna data --}}
            <div class="shrink-0 w-20 text-right hidden sm:block">
                <p class="text-xs font-semibold text-navy-700">{{ $and['data'] ?? '' }}</p>
                @if(!empty($and['hora']))
                <p class="text-xs text-gray-400">{{ $and['hora'] }}</p>
                @endif
            </div>

            {{-- Separador vertical --}}
            <div class="hidden sm:flex flex-col items-center shrink-0">
                <div class="tl-dot mt-0.5"></div>
                @if(!$loop->last)
                <div class="w-px flex-1 bg-gray-100 mt-2"></div>
                @endif
            </div>

            {{-- Conteúdo --}}
            <div class="flex-1 min-w-0">
                {{-- Data mobile --}}
                <p class="text-xs text-gray-400 sm:hidden mb-1">{{ $and['data'] ?? '' }}</p>

                <button @click="open = !open"
                        class="w-full text-left flex items-start justify-between gap-2 group">
                    <p class="text-sm text-gray-800 leading-snug font-medium group-hover:text-navy-700 transition-colors">
                        {{ $and['descricao'] ?? '' }}
                    </p>
                    <svg class="w-4 h-4 text-gray-300 shrink-0 mt-0.5 transition-transform"
                         :class="open && 'rotate-180'"
                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                @if(!empty($and['observacao']))
                <div x-show="open" x-transition class="mt-2">
                    <p class="text-xs text-gray-500 italic leading-relaxed border-l-2 border-gold-200 pl-3">
                        {{ $and['observacao'] }}
                    </p>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── RODAPÉ INFORMATIVO ── --}}
<div class="card bg-navy-50 border-navy-100">
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
        <div class="w-10 h-10 rounded-full bg-navy-700 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-gold-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-navy-700">Tem dúvidas sobre seu processo?</p>
            <p class="text-xs text-gray-500 mt-0.5">
                Entre em contato com o escritório:
                <span class="font-medium text-navy-700">(47) 3842-1050</span> ou
                <span class="font-medium text-navy-700">contato@mayeradvogados.adv.br</span>
            </p>
        </div>
    </div>
</div>

@endsection
