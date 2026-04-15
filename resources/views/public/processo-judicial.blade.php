@extends('layouts.public')

@section('title', 'Processo ' . ($processo_pasta ?? '') . ' — Mayer Advogados')

@push('styles')
<style>
    /* Timeline */
    .tl-line { position: absolute; left: 7px; top: 24px; bottom: 0; width: 1px; background: linear-gradient(to bottom, #d4ad55, #e5e7eb); }
    .tl-dot  { width: 15px; height: 15px; border-radius: 50%; background: #fff; border: 2px solid var(--gold); flex-shrink: 0; margin-top: 3px; position: relative; z-index: 1; box-shadow: 0 0 0 3px rgba(184,150,46,.12); }
    .tl-dot-faded { border-color: #d1d5db; box-shadow: none; }

    /* Chip de meta */
    .meta-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #f0f4f9; border-radius: 8px; font-size: 12px; color: #3b6fa0; font-weight: 500; }
    .meta-chip svg { width: 13px; height: 13px; flex-shrink: 0; }

    /* Número do processo */
    .processo-number { font-family: 'Lato', monospace; letter-spacing: .06em; }

    /* Secao */
    .section-label { font-size: 10px; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; color: #9ca3af; margin-bottom: 14px; }
</style>
@endpush

@section('content')

@php
    // Limpar URL embutida no resumo
    $resumoLimpo = preg_replace('/\n\n?🔗\s*https?:\/\/\S+/', '', $resumo ?? '');
    $resumoLimpo = trim($resumoLimpo);

    // Badge de status
    $statusLower = strtolower($processo_status ?? '');
    $statusClass = str_contains($statusLower, 'ativo') || str_contains($statusLower, 'andamento')
        ? 'status-ativo'
        : (str_contains($statusLower, 'encerr') || str_contains($statusLower, 'arquiv')
            ? 'status-encerrado'
            : 'status-default');

    // Posição do cliente formatada
    $posicaoLabel = match(strtolower($posicao_cliente ?? '')) {
        'autor', 'requerente', 'reclamante' => 'Autor',
        'réu', 'reu', 'requerido', 'reclamado' => 'Réu',
        default => $posicao_cliente ?? '',
    };

    // Atividades / portfólio
    $atividadesConcluidas = $atividades_concluidas ?? [];
    $atividadesPendentes  = $atividades_pendentes ?? [];
    $totalMinutos         = $total_horas_minutos ?? 0;
    $portfolioTotal       = $portfolio_total ?? 0;
    $portfolioAtivos      = $portfolio_ativos ?? 0;
    $totalHorasFmt        = $totalMinutos >= 60
        ? round($totalMinutos / 60, 1) . 'h'
        : ($totalMinutos > 0 ? $totalMinutos . 'min' : null);
@endphp

{{-- ══════════════════════════════════════════
     HERO — fundo navy, número do processo
     ══════════════════════════════════════════ --}}
<section class="bg-navy-700 w-full anim-1">
    <div class="w-full px-4 sm:px-8 py-8 sm:py-12">

        {{-- Topo: tipo + status --}}
        <div class="flex flex-wrap items-center gap-3 mb-5">
            <div class="flex items-center gap-1.5 text-gold-300">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                </svg>
                <span class="text-[11px] font-bold tracking-[.18em] uppercase">{{ $tipo_processo ?: 'Processo Judicial' }}</span>
            </div>
            @if($processo_status)
            <span class="status-badge {{ $statusClass }}">
                <span class="w-1.5 h-1.5 rounded-full bg-current inline-block"></span>
                {{ $processo_status }}
            </span>
            @endif
        </div>

        {{-- Número do processo --}}
        <h1 class="processo-number text-white text-xl sm:text-3xl font-bold leading-tight mb-2">
            {{ $processo_pasta ?? '—' }}
        </h1>

        {{-- Partes --}}
        @if(!empty($processo_adverso))
        <div class="flex items-center gap-2 mt-3">
            <span class="text-white/60 text-sm font-light">{{ $nome_cliente ?? 'Você' }}</span>
            <span class="text-gold-400 text-sm font-bold">×</span>
            <span class="text-white/60 text-sm font-light">{{ $processo_adverso }}</span>
        </div>
        @endif

        {{-- Assunto --}}
        @if(!empty($assunto))
        <div class="mt-4 pt-4 border-t border-white/10">
            <p class="text-white/40 text-[10px] uppercase tracking-widest font-semibold mb-1">Assunto</p>
            <p class="text-white/70 text-sm leading-relaxed">{{ $assunto }}</p>
        </div>
        @endif

    </div>
    <div class="gold-rule opacity-30"></div>
</section>

{{-- ══════════════════════════════════════════
     METADADOS — chips de informação
     ══════════════════════════════════════════ --}}
<section class="w-full px-4 sm:px-8 py-6 bg-white border-b border-gray-100 anim-2">
    <div class="flex flex-wrap gap-2">

        @if(!empty($posicaoLabel))
        <span class="meta-chip">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            Posição: <strong>{{ $posicaoLabel }}</strong>
        </span>
        @endif

        @if(!empty($fase_vara))
        <span class="meta-chip">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
            </svg>
            {{ $fase_vara }}
        </span>
        @endif

        @if(!empty($fase_instancia))
        <span class="meta-chip">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            {{ $fase_instancia }}
        </span>
        @endif

        @if(!empty($natureza))
        <span class="meta-chip">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
            </svg>
            {{ $natureza }}
        </span>
        @endif

        @if(!empty($area_atuacao))
        <span class="meta-chip">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            {{ $area_atuacao }}
        </span>
        @endif

        @if(!empty($data_abertura))
        <span class="meta-chip">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Distribuído em {{ $data_abertura }}
        </span>
        @endif

    </div>
</section>

{{-- ══════════════════════════════════════════
     PORTFÓLIO — estatísticas do relacionamento
     ══════════════════════════════════════════ --}}
@if($portfolioTotal > 0)
<section class="w-full bg-navy-900 border-b border-white/5 anim-2" style="background: #0d1e32;">
    <div class="w-full px-4 sm:px-8 py-5 flex flex-wrap gap-6 sm:gap-12 items-center">

        @if($portfolioAtivos > 0)
        <div class="flex items-center gap-3">
            <div style="width:36px;height:36px;border-radius:10px;background:rgba(184,150,46,.15);display:flex;align-items:center;justify-content:center;">
                <svg style="width:16px;height:16px;color:#d4ad55;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                </svg>
            </div>
            <div>
                <p class="text-white font-bold text-lg leading-none">{{ $portfolioAtivos }}</p>
                <p class="text-white/40 text-[11px] mt-0.5 uppercase tracking-wider">processo{{ $portfolioAtivos > 1 ? 's' : '' }} ativo{{ $portfolioAtivos > 1 ? 's' : '' }}</p>
            </div>
        </div>
        @endif

        @if($portfolioTotal > 0)
        <div class="flex items-center gap-3">
            <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,.05);display:flex;align-items:center;justify-content:center;">
                <svg style="width:16px;height:16px;color:#9ca3af;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
            </div>
            <div>
                <p class="text-white font-bold text-lg leading-none">{{ $portfolioTotal }}</p>
                <p class="text-white/40 text-[11px] mt-0.5 uppercase tracking-wider">histórico total</p>
            </div>
        </div>
        @endif

        @if($totalHorasFmt)
        <div class="flex items-center gap-3">
            <div style="width:36px;height:36px;border-radius:10px;background:rgba(184,150,46,.15);display:flex;align-items:center;justify-content:center;">
                <svg style="width:16px;height:16px;color:#d4ad55;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-white font-bold text-lg leading-none">{{ $totalHorasFmt }}</p>
                <p class="text-white/40 text-[11px] mt-0.5 uppercase tracking-wider">dedicadas a este processo</p>
            </div>
        </div>
        @endif

        @if(count($atividadesConcluidas) > 0)
        <div class="flex items-center gap-3">
            <div style="width:36px;height:36px;border-radius:10px;background:rgba(16,185,129,.12);display:flex;align-items:center;justify-content:center;">
                <svg style="width:16px;height:16px;color:#059669;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-white font-bold text-lg leading-none">{{ count($atividadesConcluidas) }}</p>
                <p class="text-white/40 text-[11px] mt-0.5 uppercase tracking-wider">atividade{{ count($atividadesConcluidas) > 1 ? 's' : '' }} concluída{{ count($atividadesConcluidas) > 1 ? 's' : '' }}</p>
            </div>
        </div>
        @endif

    </div>
</section>
@endif

{{-- ══════════════════════════════════════════
     CORPO — resumo + tribunal + advogado + timeline
     ══════════════════════════════════════════ --}}
<div class="w-full px-4 sm:px-8 py-8 space-y-8">

    {{-- Grid superior: resumo + cards laterais --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 anim-3">

        {{-- RESUMO DA IA (col-span-2) --}}
        @if(!empty($resumoLimpo))
        <div class="lg:col-span-2">
            <p class="section-label">Atualização do escritório</p>
            <div class="card border-l-4 border-gold-500 rounded-l-none bg-gold-50 border-l-gold-500" style="border-left-color: var(--gold);">
                <div class="flex items-start gap-3 mb-4">
                    <div style="width:36px;height:36px;border-radius:50%;background:var(--navy);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:16px;height:16px;color:#d4ad55;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-navy-700 text-sm">Resumo em linguagem acessível</p>
                        <p class="text-xs text-gray-400 mt-px">Consultado em {{ $consultadoEm }}</p>
                    </div>
                </div>
                <p class="text-sm text-gray-700 leading-relaxed">{{ $resumoLimpo }}</p>
            </div>
        </div>
        @endif

        {{-- Cards laterais: advogado + tribunal --}}
        <div class="space-y-4">
            @if(!empty($advogado_responsavel))
            <div class="card">
                <p class="section-label">Advogado responsável</p>
                <div class="flex items-center gap-3">
                    <div style="width:40px;height:40px;border-radius:50%;background:#f0f4f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:18px;height:18px;color:#3b6fa0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-navy-700 text-sm leading-snug">{{ $advogado_responsavel }}</p>
                        <p class="text-xs text-gray-400">Mayer Advogados</p>
                    </div>
                </div>
            </div>
            @endif

            @if(!empty($fase_orgao))
            <div class="card">
                <p class="section-label">Tribunal</p>
                <p class="text-sm font-medium text-navy-700 leading-snug">{{ $fase_orgao }}</p>
                @if(!empty($fase_vara))
                <p class="text-xs text-gray-400 mt-1">{{ $fase_vara }}@if(!empty($fase_instancia)) — {{ $fase_instancia }}@endif</p>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- TIMELINE DE ANDAMENTOS --}}
    @if(!empty($andamentos) && count($andamentos) > 0)
    <div class="anim-4">
        <p class="section-label">Movimentações processuais</p>
        <div class="card p-0 overflow-hidden">
            {{-- Cabeçalho da tabela --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-navy-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <span class="text-sm font-semibold text-gray-800">Histórico de andamentos</span>
                </div>
                <span class="text-xs text-gray-400 bg-gray-50 px-2.5 py-1 rounded-full">
                    {{ count($andamentos) }} registro{{ count($andamentos) !== 1 ? 's' : '' }}
                </span>
            </div>

            {{-- Itens --}}
            <div class="divide-y divide-gray-50">
                @foreach($andamentos as $i => $and)
                <div class="flex gap-0" x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }">

                    {{-- Coluna de data (desktop) --}}
                    <div class="hidden sm:flex flex-col items-end justify-start pt-5 px-5 w-36 shrink-0 border-r border-gray-50">
                        <p class="text-xs font-bold text-navy-700 text-right leading-tight">{{ $and['data'] ?? '' }}</p>
                        @if(!empty($and['hora']))
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ $and['hora'] }}</p>
                        @endif
                    </div>

                    {{-- Linha de separação + dot (desktop) --}}
                    <div class="hidden sm:flex flex-col items-center pt-5 px-4 shrink-0">
                        <div class="tl-dot {{ $i > 0 ? 'tl-dot-faded' : '' }}"></div>
                        @if(!$loop->last)
                        <div class="w-px flex-1 mt-2" style="background: {{ $i === 0 ? 'linear-gradient(to bottom, var(--gold), #e5e7eb)' : '#e5e7eb' }}"></div>
                        @endif
                    </div>

                    {{-- Conteúdo --}}
                    <div class="flex-1 py-5 pr-5">
                        {{-- Data mobile --}}
                        <p class="text-[11px] text-gray-400 sm:hidden mb-1.5">{{ $and['data'] ?? '' }}{{ !empty($and['hora']) ? ' • '.$and['hora'] : '' }}</p>

                        {{-- Descrição técnica --}}
                        <p class="text-sm text-gray-800 leading-snug {{ $i === 0 ? 'font-semibold' : 'font-normal' }}">
                            {{ $and['descricao'] ?? '' }}
                        </p>

                        {{-- Explicação leiga (sempre visível) --}}
                        @if(!empty($and['explicacao']))
                        <div class="mt-2 flex items-start gap-1.5">
                            <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" style="color:var(--gold);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-xs leading-relaxed" style="color:#6b7280; font-style:italic;">
                                {{ $and['explicacao'] }}
                            </p>
                        </div>
                        @endif

                        {{-- Observação técnica (expansível) --}}
                        @if(!empty($and['observacao']))
                        <button @click="open = !open"
                                class="mt-2 flex items-center gap-1 text-[11px] font-medium transition-colors"
                                style="color: var(--gold);">
                            <svg class="w-3 h-3 transition-transform duration-200" :class="open ? 'rotate-180' : ''"
                                 fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                            <span x-text="open ? 'Ocultar detalhe' : 'Ver detalhe técnico'"></span>
                        </button>
                        <div x-show="open" x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="mt-2 pl-3 border-l-2 text-xs text-gray-500 leading-relaxed"
                             style="border-left-color: #e5e7eb;">
                            {{ $and['observacao'] }}
                        </div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- TRABALHO REALIZADO --}}
    @if(count($atividadesConcluidas) > 0)
    <div class="anim-5">
        <p class="section-label">Trabalho realizado neste processo</p>
        <div class="card p-0 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4" style="color:var(--gold);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-sm font-semibold text-gray-800">Atividades da equipe</span>
                </div>
                @if($totalHorasFmt)
                <span class="text-xs font-bold px-3 py-1 rounded-full" style="background:rgba(184,150,46,.1);color:var(--gold);">
                    {{ $totalHorasFmt }} registradas
                </span>
                @endif
            </div>

            <div class="divide-y divide-gray-50">
                @foreach($atividadesConcluidas as $atv)
                <div class="flex items-center gap-4 px-6 py-4">
                    {{-- Ícone check --}}
                    <div style="width:32px;height:32px;border-radius:50%;background:rgba(16,185,129,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:14px;height:14px;color:#059669;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800">{{ $atv['responsavel'] ?: 'Equipe Mayer' }}</p>
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ $atv['data'] }}</p>
                    </div>

                    {{-- Duração --}}
                    @if($atv['duracao_min'] > 0)
                    <span class="text-xs font-semibold text-navy-700 bg-navy-50 px-2.5 py-1 rounded-full shrink-0" style="background:#f0f4f9;color:#1a2e4a;">
                        {{ $atv['duracao_fmt'] }}
                    </span>
                    @endif
                </div>
                @endforeach
            </div>

            {{-- Rodapé com total --}}
            @if($totalHorasFmt && count($atividadesConcluidas) > 1)
            <div class="px-6 py-3 border-t border-gray-100 flex items-center justify-between" style="background:#fafafa;">
                <p class="text-xs text-gray-400">Total acumulado neste processo</p>
                <p class="text-xs font-bold" style="color:var(--navy);">{{ $totalHorasFmt }}</p>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ATIVIDADES EM ANDAMENTO --}}
    @if(count($atividadesPendentes) > 0)
    <div class="anim-5">
        <p class="section-label">Em andamento pela equipe</p>
        <div class="card p-0 overflow-hidden">
            <div class="divide-y divide-gray-50">
                @foreach($atividadesPendentes as $atv)
                <div class="flex items-center gap-4 px-6 py-4">
                    <div style="width:32px;height:32px;border-radius:50%;background:rgba(184,150,46,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg style="width:14px;height:14px;color:var(--gold);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800">{{ $atv['responsavel'] ?: 'Equipe Mayer' }}</p>
                        @if(!empty($atv['data']))
                        <p class="text-[11px] text-gray-400 mt-0.5">Previsto: {{ $atv['data'] }}</p>
                        @endif
                    </div>
                    <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded" style="background:rgba(184,150,46,.1);color:var(--gold);">
                        {{ $atv['status'] }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- CONTATO DO ESCRITÓRIO --}}
    <div class="card anim-5" style="background: #f0f4f9; border-color: #d6e2ef;">
        <div class="flex flex-col sm:flex-row sm:items-center gap-4">
            <div style="width:44px;height:44px;border-radius:50%;background:var(--navy);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg style="width:20px;height:20px;" fill="none" stroke="#d4ad55" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div class="flex-1">
                <p class="font-semibold text-navy-700 text-sm">Dúvidas sobre seu processo?</p>
                <p class="text-xs text-gray-500 mt-1">
                    Fale com o escritório:
                    <a href="tel:+554738421050" class="font-semibold text-navy-700 hover:text-gold-500 transition-colors">(47) 3842-1050</a>
                    &nbsp;·&nbsp;
                    <a href="mailto:contato@mayeradvogados.adv.br" class="font-semibold text-navy-700 hover:text-gold-500 transition-colors">contato@mayeradvogados.adv.br</a>
                </p>
            </div>
        </div>
    </div>

</div>

@endsection
