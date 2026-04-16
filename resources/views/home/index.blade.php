@extends('layouts.app')

@section('title', 'Home')

@section('content')
<style>
    /* ── Shortcut slots (preserved from original) ── */
    .shortcut-slot {
        min-height: 90px; border: 2px dashed var(--ds-border, #D8DEE6); border-radius: var(--ds-radius, 14px);
        transition: all .25s cubic-bezier(.4,0,.2,1); cursor: pointer; position: relative;
        background: var(--ds-surface, #fff);
    }
    .shortcut-slot.filled { border: 1px solid var(--ds-border-l, #E8ECF1); background: var(--ds-surface, #fff); cursor: grab; }
    .shortcut-slot.filled:hover { border-color: var(--ds-primary, #385776); box-shadow: 0 0 0 3px rgba(56,87,118,0.08); transform: translateY(-2px); }
    .shortcut-slot.filled:active { cursor: grabbing; }
    .shortcut-slot.drag-over { border-color: var(--ds-primary, #385776) !important; background: rgba(56,87,118,0.04); box-shadow: 0 0 0 3px rgba(56,87,118,0.12); }
    .shortcut-slot .remove-btn {
        position: absolute; top: 6px; right: 6px; width: 22px; height: 22px; border-radius: 50%;
        background: rgba(220,38,38,0.08); color: #DC2626; display: none; align-items: center;
        justify-content: center; font-size: 12px; cursor: pointer; border: none; padding: 0; line-height: 1;
    }
    .shortcut-slot:hover .remove-btn { display: flex; }
    .shortcut-slot .remove-btn:hover { background: rgba(220,38,38,0.18); }

    /* ── Modal (preserved) ── */
    .modal-overlay {
        position: fixed; inset: 0; background: rgba(27,51,74,0.5); backdrop-filter: blur(4px);
        z-index: 9998; display: flex; align-items: center; justify-content: center;
        opacity: 0; pointer-events: none; transition: opacity 200ms;
    }
    .modal-overlay.active { opacity: 1; pointer-events: all; }
    .modal-box {
        background: white; border-radius: var(--ds-radius, 14px); width: 540px; max-width: 95vw;
        max-height: 80vh; overflow: hidden; box-shadow: var(--ds-shadow-lg, 0 8px 32px rgba(27,51,74,.12));
        transform: translateY(20px); transition: transform 200ms;
    }
    .modal-overlay.active .modal-box { transform: translateY(0); }
    .module-pick {
        padding: 10px 14px; border-radius: var(--ds-radius-sm, 10px); cursor: pointer;
        border: 1px solid var(--ds-border-l, #E8ECF1); transition: all 150ms;
        display: flex; align-items: center; gap: 10px;
    }
    .module-pick:hover { border-color: var(--ds-primary, #385776); background: rgba(56,87,118,0.03); }
    .module-pick.selected { border-color: var(--ds-primary, #385776); background: rgba(56,87,118,0.06); }

    /* ── Score ring (preserved) ── */
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

    /* ── Section label ── */
    .ds-section-label {
        font-size: .6rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: .12em; color: var(--ds-text-3, #8896A6); margin-bottom: .65rem;
    }

    /* ── Shortcut grid responsive ── */
    .shortcut-grid {
        display: grid; grid-template-columns: repeat(5, 1fr); gap: .75rem;
    }
    @media (max-width: 1100px) { .shortcut-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 640px) { .shortcut-grid { grid-template-columns: repeat(2, 1fr); } }

    /* ── Pipeline bar chart ── */
    .pipe-bar-chart { display: flex; align-items: flex-end; gap: .5rem; overflow-x: auto; padding-bottom: .25rem; }
    .pipe-bar-col { display: flex; flex-direction: column; align-items: center; min-width: 80px; flex: 1; }
</style>

<div class="ds">
<div class="ds-page" x-data="homeApp()">

    {{-- ══════ HERO ══════ --}}
    <div class="ds-hero ds-a ds-a1">
        <div class="ds-hero-top">
            <div>
                <div class="ds-hero-greeting">{{ $saudacao }}</div>
                <div class="ds-hero-name">{{ $primeiroNome }}</div>
                <div class="ds-hero-date">{{ now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</div>
            </div>
            <div class="ds-hero-actions">
                <a href="{{ url('/crm') }}" class="ds-hero-btn" style="text-decoration:none;">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    CRM
                </a>
                <a href="{{ url('/nexo/atendimento') }}" class="ds-hero-btn--accent ds-hero-btn" style="text-decoration:none;">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    Nexo
                </a>
            </div>
        </div>
        <div class="ds-hero-stats">
            <div class="ds-hero-stat">
                <div class="ds-hero-stat-value">{{ number_format($volumetria['clientes']) }}</div>
                <div class="ds-hero-stat-label">Clientes</div>
            </div>
            <div class="ds-hero-stat">
                <div class="ds-hero-stat-value">{{ $volumetria['processos'] }}</div>
                <div class="ds-hero-stat-label">Processos</div>
            </div>
            <div class="ds-hero-stat">
                <div class="ds-hero-stat-value">{{ $volumetria['oportunidades'] }}</div>
                <div class="ds-hero-stat-label">Oportunidades</div>
            </div>
            <div class="ds-hero-stat">
                <div class="ds-hero-stat-value">{{ $avisos['total'] }}</div>
                <div class="ds-hero-stat-label">Avisos</div>
                @if($avisos['total'] > 0)
                <div class="ds-hero-stat-delta ds-hero-stat-delta--down">{{ $avisos['total'] }} pendentes</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ══════ BUSCA GLOBAL ══════ --}}
    <div class="ds-a ds-a2" style="position:relative;" x-data="buscaGlobal()">
        <div style="position:relative;">
            <span style="position:absolute;inset-y:0;left:0;display:flex;align-items:center;padding-left:1rem;pointer-events:none;top:50%;transform:translateY(-50%);">
                <svg style="width:16px;height:16px;color:var(--ds-text-3,#8896A6);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </span>
            <input type="text" x-model="query" x-on:input.debounce.300ms="buscar()"
                x-on:focus="aberto = query.length >= 2" x-on:click.away="aberto = false" x-on:keydown.escape="aberto = false"
                placeholder="Buscar cliente, processo, lead ou conta CRM..."
                class="ds-input"
                style="padding-left:2.75rem;padding-right:2.5rem;padding-top:.7rem;padding-bottom:.7rem;border-radius:var(--ds-radius,14px);font-size:.82rem;"
                autocomplete="off">
            <span x-show="loading" style="position:absolute;inset-y:0;right:0;display:flex;align-items:center;padding-right:1rem;top:50%;transform:translateY(-50%);">
                <svg style="width:16px;height:16px;color:var(--ds-primary,#385776);" class="animate-spin" fill="none" viewBox="0 0 24 24"><circle style="opacity:.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path style="opacity:.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </span>
        </div>
        {{-- Results dropdown --}}
        <div x-show="aberto && resultados.length > 0" x-transition.opacity
            style="position:absolute;z-index:50;width:100%;margin-top:.5rem;background:var(--ds-surface,#fff);border-radius:var(--ds-radius,14px);box-shadow:var(--ds-shadow-lg);border:1px solid var(--ds-border-l,#E8ECF1);max-height:18rem;overflow-y:auto;">
            <template x-for="item in resultados" :key="item.tipo + item.titulo + item.subtitulo">
                <a :href="baseUrl + '/' + item.url.replace(/^\//, '')" style="display:flex;align-items:center;gap:.75rem;padding:.75rem 1.25rem;transition:background .15s;border-bottom:1px solid var(--ds-border-l,#E8ECF1);text-decoration:none;color:inherit;"
                   onmouseover="this.style.background='var(--ds-surface-2,#F6F8FA)'" onmouseout="this.style.background='transparent'">
                    <span class="ds-av ds-av--navy" x-text="item.badge.charAt(0)"></span>
                    <div style="flex:1;min-width:0;">
                        <span style="font-weight:600;font-size:.78rem;color:var(--ds-navy,#1B334A);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" x-text="item.titulo"></span>
                        <span style="font-size:.65rem;color:var(--ds-text-3,#8896A6);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" x-text="item.subtitulo"></span>
                    </div>
                    <span class="ds-badge" :class="item.badge_cor" x-text="item.badge" style="font-size:.55rem;"></span>
                </a>
            </template>
        </div>
        <div x-show="aberto && resultados.length === 0 && !loading && query.length >= 2" x-transition
            style="position:absolute;z-index:50;width:100%;margin-top:.5rem;background:var(--ds-surface,#fff);border-radius:var(--ds-radius,14px);box-shadow:var(--ds-shadow-lg);padding:1.5rem;text-align:center;">
            <p style="font-size:.78rem;color:var(--ds-text-3,#8896A6);">Nenhum resultado para "<span style="font-weight:600;color:var(--ds-text,#1B334A);" x-text="query"></span>"</p>
        </div>
    </div>

    {{-- ══════ ATALHOS PERSONALIZAVEIS ══════ --}}
    <div class="ds-a ds-a2">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;">
            <div class="ds-section-label" style="margin-bottom:0;">
                <svg style="width:14px;height:14px;display:inline;vertical-align:-2px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Meus Atalhos
            </div>
            <button @click="showModal = true" class="ds-btn ds-btn--ghost ds-btn--sm">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Configurar
            </button>
        </div>
        <div class="shortcut-grid">
            <template x-for="(slot, idx) in slots" :key="'s'+idx">
                <div class="shortcut-slot" :class="slot.slug ? 'filled' : ''"
                     :draggable="slot.slug ? 'true' : 'false'"
                     @dragstart="onDragStart($event, idx)" @dragover.prevent="onDragOver($event)" @dragleave="onDragLeave($event)" @drop.prevent="onDrop($event, idx)"
                     @click="slot.slug ? goTo(slot.rota) : (showModal = true)">
                    <template x-if="slot.slug">
                        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:1rem .75rem;text-align:center;">
                            <button class="remove-btn" @click.stop="removeSlot(idx)">&times;</button>
                            <div style="width:2.75rem;height:2.75rem;border-radius:var(--ds-radius-sm,10px);display:flex;align-items:center;justify-content:center;margin-bottom:.5rem;" :style="'background:linear-gradient(135deg,'+palette(slot.grupo,0)+','+palette(slot.grupo,1)+')'">
                                <span x-html="renderIcon(slot.icone,slot.nome)" style="color:#fff;display:flex;align-items:center;justify-content:center;width:100%;height:100%;"></span>
                            </div>
                            <span style="font-size:.72rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;width:100%;color:var(--ds-navy,#1B334A);" x-text="slot.nome"></span>
                            <span style="font-size:.58rem;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;width:100%;color:var(--ds-text-3,#8896A6);" x-text="grupoLabel(slot.grupo)"></span>
                        </div>
                    </template>
                    <template x-if="!slot.slug">
                        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:1rem .75rem;cursor:pointer;">
                            <div style="width:2.75rem;height:2.75rem;border-radius:var(--ds-radius-sm,10px);background:var(--ds-surface-3,#EDF1F5);display:flex;align-items:center;justify-content:center;margin-bottom:.5rem;">
                                <svg style="width:1.25rem;height:1.25rem;color:var(--ds-text-3,#8896A6);opacity:.5;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </div>
                            <span style="font-size:.68rem;color:var(--ds-text-3,#8896A6);font-weight:500;">Adicionar</span>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- ══════ KPI STAT CARDS ══════ --}}
    <div class="ds-g4 ds-a ds-a3">
        {{-- Alertas CRM --}}
        <div class="ds-stat ds-stat--orange">
            <div class="ds-stat-header">
                <div class="ds-stat-icon ds-stat-icon--warning">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                </div>
            </div>
            <div class="ds-stat-value">{{ count($alertasCrm) }}</div>
            <div class="ds-stat-label">Alertas CRM</div>
        </div>
        {{-- Tickets abertos --}}
        <div class="ds-stat ds-stat--blue">
            <div class="ds-stat-header">
                <div class="ds-stat-icon ds-stat-icon--primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                </div>
            </div>
            <div class="ds-stat-value">{{ count($ticketsAbertos) }}</div>
            <div class="ds-stat-label">Tickets Abertos</div>
        </div>
        {{-- Solicitacoes --}}
        <div class="ds-stat ds-stat--red">
            <div class="ds-stat-header">
                <div class="ds-stat-icon ds-stat-icon--danger">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                </div>
            </div>
            <div class="ds-stat-value">{{ $solicitacoes['total_abertas'] }}</div>
            <div class="ds-stat-label">Solicitacoes Abertas</div>
        </div>
        {{-- Financeiro resultado --}}
        <div class="ds-stat ds-stat--green">
            <div class="ds-stat-header">
                <div class="ds-stat-icon ds-stat-icon--success">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                @if($resumoFinanceiro['var_receita'] !== null)
                <span class="ds-stat-delta {{ $resumoFinanceiro['var_receita'] >= 0 ? 'ds-stat-delta--up' : 'ds-stat-delta--down' }}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $resumoFinanceiro['var_receita'] >= 0 ? 'M5 10l7-7m0 0l7 7m-7-7v18' : 'M19 14l-7 7m0 0l-7-7m7 7V3' }}"/></svg>
                    {{ $resumoFinanceiro['var_receita'] >= 0 ? '+' : '' }}{{ $resumoFinanceiro['var_receita'] }}%
                </span>
                @endif
            </div>
            <div class="ds-stat-value">R$ {{ number_format($resumoFinanceiro['receita'],0,',','.') }}</div>
            <div class="ds-stat-label">Receita do Mes</div>
            <svg class="ds-stat-spark" viewBox="0 0 90 36"><path d="M0,28 Q18,22 30,18 T55,10 T90,4" fill="none" stroke="var(--ds-success)" stroke-width="2"/><path d="M0,28 Q18,22 30,18 T55,10 T90,4 V36 H0Z" fill="var(--ds-success)" opacity=".4"/></svg>
        </div>
    </div>

    {{-- ══════ PAINEL COMERCIAL ══════ --}}
    @if(auth()->user()->role === 'admin' || auth()->user()->role === 'socio')
    <div class="ds-a ds-a4">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem;">
            <div class="ds-section-label" style="margin-bottom:0;">Painel Comercial — {{ now()->locale('pt_BR')->isoFormat('MMMM [de] YYYY') }}</div>
            <a href="{{ url('/crm') }}" class="ds-btn ds-btn--ghost ds-btn--sm" style="text-decoration:none;">Ver CRM &rarr;</a>
        </div>
        <div class="ds-g4" style="margin-bottom:1rem;">
            {{-- Leads no mes --}}
            <div class="ds-stat ds-stat--blue">
                <div class="ds-stat-header">
                    <div class="ds-stat-icon ds-stat-icon--primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                </div>
                <div class="ds-stat-value">{{ $painelComercial['leadsTotal'] }}</div>
                <div class="ds-stat-label">Leads no mes</div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:.5rem;">
                    <span class="ds-badge ds-badge--info">{{ $painelComercial['leadsNovos'] }} novos</span>
                    <span class="ds-badge ds-badge--warning">{{ $painelComercial['leadsContatados'] }} contato</span>
                    <span class="ds-badge ds-badge--danger">{{ $painelComercial['leadsDescart'] }} descart.</span>
                </div>
            </div>
            {{-- Conversao --}}
            <div class="ds-stat {{ $painelComercial['taxaConversao'] >= 10 ? 'ds-stat--green' : 'ds-stat--orange' }}">
                <div class="ds-stat-header">
                    <div class="ds-stat-icon {{ $painelComercial['taxaConversao'] >= 10 ? 'ds-stat-icon--success' : 'ds-stat-icon--warning' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    </div>
                </div>
                <div class="ds-stat-value">{{ $painelComercial['taxaConversao'] }}%</div>
                <div class="ds-stat-label">Taxa de conversao</div>
                <div style="font-size:.65rem;color:var(--ds-text-3);margin-top:.35rem;">{{ $painelComercial['leadsConvert'] }} convertidos de {{ $painelComercial['leadsTotal'] }}</div>
            </div>
            {{-- Ganhos no mes --}}
            <div class="ds-stat ds-stat--green">
                <div class="ds-stat-header">
                    <div class="ds-stat-icon ds-stat-icon--success">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <div class="ds-stat-value">{{ $painelComercial['ganhosMes']->total ?? 0 }}</div>
                <div class="ds-stat-label">Ganhos no mes</div>
                @if(($painelComercial['ganhosMes']->valor ?? 0) > 0)
                <div style="font-size:.68rem;font-weight:600;color:var(--ds-success);margin-top:.35rem;">R$ {{ number_format($painelComercial['ganhosMes']->valor, 0, ',', '.') }}</div>
                @else
                <div style="font-size:.65rem;color:var(--ds-text-3);margin-top:.35rem;">Nenhum registro</div>
                @endif
            </div>
            {{-- Clientes ativos --}}
            <div class="ds-stat ds-stat--blue">
                <div class="ds-stat-header">
                    <div class="ds-stat-icon ds-stat-icon--primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                </div>
                <div class="ds-stat-value">{{ $painelComercial['clientesAtivos'] }}</div>
                <div class="ds-stat-label">Base de clientes</div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:.5rem;">
                    <span class="ds-badge ds-badge--info">{{ $painelComercial['clientesOnboarding'] }} onboard</span>
                    @if($painelComercial['adormSemContato'] > 0)
                    <span class="ds-badge ds-badge--danger">{{ $painelComercial['adormSemContato'] }} adorm. 30d+</span>
                    @endif
                </div>
            </div>
        </div>
        {{-- Pipeline --}}
        <div class="ds-card">
            <div class="ds-card-head">
                <h3>Pipeline CRM — oportunidades em aberto</h3>
            </div>
            <div class="ds-card-body">
                <div class="pipe-bar-chart">
                    @php $maxVal = max(array_map(fn($s) => $s['total'], $painelComercial['pipeline']) ?: [1]); @endphp
                    @foreach($painelComercial['pipeline'] as $stage)
                    <div class="pipe-bar-col">
                        <span style="font-size:.72rem;font-weight:700;color:var(--ds-navy);margin-bottom:4px;">{{ $stage['total'] }}</span>
                        <div style="width:100%;border-radius:var(--ds-radius-xs,8px) var(--ds-radius-xs,8px) 0 0;transition:all .3s;height:{{ max(4, intval(($stage['total'] / $maxVal) * 60)) }}px;background:linear-gradient(180deg,var(--ds-primary),var(--ds-navy));"></div>
                        <span style="font-size:.58rem;color:var(--ds-text-3);margin-top:4px;text-align:center;line-height:1.2;">{{ $stage['nome'] }}</span>
                        @if($stage['valor'] > 0)
                        <span style="font-size:.52rem;color:var(--ds-success);font-weight:600;">R$ {{ number_format($stage['valor']/1000, 0) }}k</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ══════ GDP + FINANCEIRO + AVISOS ══════ --}}
    <div class="ds-g-main ds-a ds-a4">

        {{-- GDP Score --}}
        <a href="{{ url('/gdp') }}" class="ds-card" style="text-decoration:none;display:block;background:linear-gradient(135deg,var(--ds-navy-d,#0F2030) 0%,var(--ds-navy,#1B334A) 50%,var(--ds-primary,#385776) 100%);border-color:transparent;position:relative;overflow:hidden;">
            <div style="position:absolute;top:0;right:0;width:10rem;height:10rem;border-radius:50%;opacity:.05;margin-right:-2.5rem;margin-top:-2.5rem;background:#fff;"></div>
            <div class="ds-card-body" style="position:relative;z-index:1;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
                    <div style="display:flex;align-items:center;gap:.75rem;">
                        <div class="ds-stat-icon" style="background:rgba(255,255,255,.12);">
                            <svg style="color:#fff;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                        <div>
                            <h3 style="font-weight:700;color:#fff;font-size:.88rem;margin:0;">Meu Score GDP</h3>
                            @if($gdpScore)<p style="color:rgba(255,255,255,.4);font-size:.65rem;margin:0;">Ref: {{ $gdpScore['mes_ref'] }}</p>@endif
                        </div>
                    </div>
                    <svg style="width:1rem;height:1rem;color:rgba(255,255,255,.3);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7v10"/></svg>
                </div>
                @if($gdpScore)
                <div style="display:flex;align-items:center;gap:1.5rem;margin-bottom:1.25rem;">
                    <div class="score-ring" style="--pct:{{ min($gdpScore['score_total'],100) }};--clr:{{ $gdpScore['score_total'] >= 70 ? '#34D399' : ($gdpScore['score_total'] >= 40 ? '#FBBF24' : '#F87171') }}">
                        <div class="score-ring-inner">
                            <span style="font-size:1.5rem;font-weight:800;color:#fff;line-height:1;">{{ $gdpScore['score_total'] }}</span>
                            <span style="font-size:.55rem;color:rgba(255,255,255,.35);">/100</span>
                        </div>
                    </div>
                    <div style="flex:1;">
                        @if($gdpScore['variacao'] !== null)
                        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:var(--ds-radius-xs,8px);font-size:.65rem;font-weight:700;{{ $gdpScore['variacao'] >= 0 ? 'background:rgba(52,211,153,.2);color:#6EE7B7;' : 'background:rgba(248,113,113,.2);color:#FCA5A5;' }}">
                            {{ $gdpScore['variacao'] >= 0 ? '+' : '' }}{{ $gdpScore['variacao'] }} pts
                        </span>
                        @endif
                        @if($gdpScore['ranking'])
                        <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:var(--ds-radius-xs,8px);background:rgba(255,255,255,.1);margin-left:.35rem;">
                            <span style="color:#FCD34D;font-size:.68rem;">&#9733;</span>
                            <span style="color:#fff;font-weight:700;font-size:.78rem;">{{ $gdpScore['ranking'] }}&#186;</span>
                            <span style="color:rgba(255,255,255,.35);font-size:.62rem;">de {{ $gdpScore['total_participantes'] }}</span>
                        </span>
                        @endif
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:.75rem;">
                    @php $eixos = [
                        ['l'=>'JUR','s'=>$gdpScore['score_juridico']],
                        ['l'=>'FIN','s'=>$gdpScore['score_financeiro']],
                        ['l'=>'DEV','s'=>$gdpScore['score_desenvolvimento']],
                        ['l'=>'ATE','s'=>$gdpScore['score_atendimento']],
                    ]; @endphp
                    @foreach($eixos as $e)
                    <div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                            <span style="font-size:.55rem;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.08em;">{{ $e['l'] }}</span>
                            <span style="font-size:.62rem;font-weight:700;color:#fff;">{{ $e['s'] }}</span>
                        </div>
                        <div class="ds-progress" style="background:rgba(255,255,255,.1);">
                            <div class="ds-progress-fill" style="width:{{ min($e['s'],100) }}%;background:{{ $e['s'] >= 70 ? '#34D399' : ($e['s'] >= 40 ? '#FBBF24' : '#F87171') }};"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div style="text-align:center;padding:2rem 0;">
                    <p style="color:rgba(255,255,255,.35);font-size:.78rem;">Aguardando primeira apuracao</p>
                </div>
                @endif
            </div>
        </a>

        {{-- Coluna direita: Financeiro + Avisos --}}
        <div style="display:flex;flex-direction:column;gap:1rem;">
            {{-- Financeiro --}}
            <a href="{{ url('/visao-gerencial') }}" class="ds-card ds-card--success" style="text-decoration:none;display:block;">
                <div class="ds-card-head">
                    <div style="display:flex;align-items:center;gap:.5rem;">
                        <div class="ds-stat-icon ds-stat-icon--success">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <h3>Financeiro do Mes</h3>
                    </div>
                    <svg style="width:.85rem;height:.85rem;color:var(--ds-text-3);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7v10"/></svg>
                </div>
                <div class="ds-card-body">
                    <div style="display:flex;flex-direction:column;gap:.65rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:.72rem;color:var(--ds-text-3);">Receita</span>
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                <span style="font-weight:700;color:var(--ds-success);font-size:.82rem;">R$ {{ number_format($resumoFinanceiro['receita'],0,',','.') }}</span>
                                @if($resumoFinanceiro['var_receita'] !== null)
                                <span class="ds-badge {{ $resumoFinanceiro['var_receita'] >= 0 ? 'ds-badge--success' : 'ds-badge--danger' }}">{{ $resumoFinanceiro['var_receita'] >= 0 ? '+' : '' }}{{ $resumoFinanceiro['var_receita'] }}%</span>
                                @endif
                            </div>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:.72rem;color:var(--ds-text-3);">Despesas</span>
                            <span style="font-weight:700;color:var(--ds-danger);font-size:.82rem;">R$ {{ number_format($resumoFinanceiro['despesa'],0,',','.') }}</span>
                        </div>
                        <div style="height:1px;background:var(--ds-border-l);"></div>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="font-size:.75rem;font-weight:600;color:var(--ds-text);">Resultado</span>
                            <span style="font-weight:800;font-size:.95rem;color:{{ $resumoFinanceiro['resultado'] >= 0 ? 'var(--ds-success)' : 'var(--ds-danger)' }};">R$ {{ number_format($resumoFinanceiro['resultado'],0,',','.') }}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <div class="ds-progress" style="flex:1;">
                                <div class="ds-progress-fill {{ $resumoFinanceiro['margem'] >= 30 ? 'ds-progress-fill--success' : ($resumoFinanceiro['margem'] >= 10 ? 'ds-progress-fill--primary' : '') }}" style="width:{{ min(max($resumoFinanceiro['margem'],0),100) }}%;{{ $resumoFinanceiro['margem'] < 10 ? 'background:var(--ds-danger);' : '' }}"></div>
                            </div>
                            <span style="font-size:.68rem;font-weight:700;color:var(--ds-text-2);">{{ $resumoFinanceiro['margem'] }}%</span>
                        </div>
                    </div>
                </div>
            </a>

            {{-- Avisos --}}
            <a href="{{ url('/avisos') }}" class="ds-card" style="text-decoration:none;display:block;">
                <div class="ds-card-head">
                    <div style="display:flex;align-items:center;gap:.5rem;">
                        <div class="ds-stat-icon {{ $avisos['total'] > 0 ? 'ds-stat-icon--danger' : '' }}" style="{{ $avisos['total'] == 0 ? 'background:var(--ds-surface-3);color:var(--ds-text-3);' : '' }}">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </div>
                        <h3>Avisos</h3>
                        @if($avisos['total'] > 0)
                        <span class="ds-badge ds-badge--danger">{{ $avisos['total'] }}</span>
                        @endif
                    </div>
                    <svg style="width:.85rem;height:.85rem;color:var(--ds-text-3);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7M17 7H7M17 7v10"/></svg>
                </div>
                <div class="ds-card-body">
                    @if(count($avisos['avisos']) > 0)
                    <div style="display:flex;flex-direction:column;gap:.4rem;">
                        @foreach(array_slice($avisos['avisos'], 0, 3) as $av)
                        <div style="display:flex;align-items:center;gap:.5rem;font-size:.72rem;">
                            @if($av['destaque'])<span style="color:#F59E0B;font-size:.62rem;">&#9733;</span>@else<span style="width:6px;height:6px;border-radius:50%;background:var(--ds-border);flex-shrink:0;"></span>@endif
                            <span style="color:var(--ds-text);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $av['titulo'] }}</span>
                            <span style="color:var(--ds-text-3);flex-shrink:0;font-size:.62rem;">{{ $av['data'] }}</span>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p style="font-size:.72rem;color:var(--ds-text-3);text-align:center;padding:.5rem 0;">&#10003; Tudo lido</p>
                    @endif
                </div>
            </a>
        </div>
    </div>

    {{-- ══════ ALERTAS CRM + TICKETS + SOLICITACOES ══════ --}}
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;" class="ds-a ds-a5">

        {{-- Alertas CRM --}}
        <div class="ds-card ds-card--accent" style="border-top-color:var(--ds-warning);">
            <div class="ds-card-head">
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <div class="ds-stat-icon ds-stat-icon--warning">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    </div>
                    <h3>Alertas CRM</h3>
                    @if(count($alertasCrm) > 0)<span class="ds-badge ds-badge--warning">{{ count($alertasCrm) }}</span>@endif
                </div>
                <a href="{{ url('/crm') }}" class="ds-btn ds-btn--ghost ds-btn--sm" style="text-decoration:none;">CRM &rarr;</a>
            </div>
            <div class="ds-card-body--flush">
                @if(count($alertasCrm) > 0)
                <div style="max-height:14rem;overflow-y:auto;">
                    @foreach($alertasCrm as $al)
                    <a href="{{ url($al['url'] ?? '/crm') }}" class="ds-tx" style="text-decoration:none;">
                        <div class="ds-tx-icon" style="background:var(--ds-warning-bg);color:var(--ds-warning);">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="ds-tx-info">
                            <div class="ds-tx-name">{{ $al['titulo'] }}</div>
                            <div class="ds-tx-desc">{{ $al['descricao'] }}</div>
                        </div>
                    </a>
                    @endforeach
                </div>
                @else
                <div style="text-align:center;padding:2rem 1.35rem;">
                    <div class="ds-stat-icon ds-stat-icon--success" style="margin:0 auto .5rem;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <p style="font-size:.72rem;color:var(--ds-text-3);">Carteira em dia</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Tickets --}}
        <div class="ds-card ds-card--accent">
            <div class="ds-card-head">
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <div class="ds-stat-icon ds-stat-icon--primary">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    </div>
                    <h3>Tickets Abertos</h3>
                    @if(count($ticketsAbertos) > 0)<span class="ds-badge ds-badge--info">{{ count($ticketsAbertos) }}</span>@endif
                </div>
                <a href="{{ url('/nexo/tickets') }}" class="ds-btn ds-btn--ghost ds-btn--sm" style="text-decoration:none;">Tickets &rarr;</a>
            </div>
            <div class="ds-card-body--flush">
                @if(count($ticketsAbertos) > 0)
                <div style="max-height:14rem;overflow-y:auto;">
                    @foreach($ticketsAbertos as $tk)
                    <div class="ds-tx">
                        <div class="ds-av" style="@if(($tk['prioridade'] ?? '') === 'alta') background:var(--ds-danger-bg);color:var(--ds-danger); @elseif(($tk['prioridade'] ?? '') === 'media') background:var(--ds-warning-bg);color:var(--ds-warning); @else background:var(--ds-surface-3);color:var(--ds-text-3); @endif font-size:.58rem;">#{{ $tk['id'] }}</div>
                        <div class="ds-tx-info">
                            <div class="ds-tx-name">{{ $tk['titulo'] }}</div>
                            <div class="ds-tx-desc">{{ $tk['criado_em'] }}</div>
                        </div>
                        @if(($tk['prioridade'] ?? '') === 'alta')<span class="ds-badge ds-badge--danger">Urgente</span>@endif
                    </div>
                    @endforeach
                </div>
                @else
                <div style="text-align:center;padding:2rem 1.35rem;">
                    <div class="ds-stat-icon ds-stat-icon--success" style="margin:0 auto .5rem;">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <p style="font-size:.72rem;color:var(--ds-text-3);">Nenhum ticket aberto</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Solicitacoes --}}
        <div class="ds-card ds-card--accent" style="border-top-color:var(--ds-danger);">
            <div class="ds-card-head">
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <div class="ds-stat-icon ds-stat-icon--danger">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    </div>
                    <h3>Solicitacoes</h3>
                    @if($solicitacoes['total_abertas'] > 0)<span class="ds-badge ds-badge--danger">{{ $solicitacoes['total_abertas'] }}</span>@endif
                </div>
                <a href="{{ url('/crm') }}" class="ds-btn ds-btn--ghost ds-btn--sm" style="text-decoration:none;">CRM &rarr;</a>
            </div>
            <div class="ds-card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:1rem;">
                    <div style="text-align:center;padding:.6rem;border-radius:var(--ds-radius-xs);background:var(--ds-danger-bg);">
                        <span style="display:block;font-size:1.1rem;font-weight:800;color:var(--ds-danger);">{{ $solicitacoes['total_abertas'] }}</span>
                        <span style="font-size:.58rem;font-weight:600;color:var(--ds-danger);">Abertas</span>
                    </div>
                    <div style="text-align:center;padding:.6rem;border-radius:var(--ds-radius-xs);background:var(--ds-surface-3);">
                        <span style="display:block;font-size:1.1rem;font-weight:800;color:var(--ds-text-2);">{{ $solicitacoes['total_concluidas'] }}</span>
                        <span style="font-size:.58rem;font-weight:600;color:var(--ds-text-3);">Concluidas</span>
                    </div>
                </div>
                @if(count($solicitacoes['items']) > 0)
                <div style="display:flex;flex-direction:column;gap:.25rem;max-height:10rem;overflow-y:auto;">
                    @foreach($solicitacoes['items'] as $sr)
                    <a href="{{ url('/crm/solicitacoes/' . $sr['id']) }}" class="ds-pipe-item" style="text-decoration:none;padding:.5rem 0;">
                        <div style="display:flex;align-items:center;gap:.5rem;flex:1;min-width:0;">
                            <span class="ds-av" style="width:26px;height:26px;font-size:.52rem;@if($sr['priority']==='urgente') background:var(--ds-danger-bg);color:var(--ds-danger); @elseif($sr['priority']==='alta') background:var(--ds-warning-bg);color:var(--ds-warning); @else background:var(--ds-primary-bg);color:var(--ds-primary); @endif">#{{ $sr['id'] }}</span>
                            <span style="font-size:.72rem;font-weight:600;color:var(--ds-navy);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $sr['subject'] }}</span>
                        </div>
                        @php
                            $sB = match($sr['status']) { 'aberto'=>'ds-badge--info','em_andamento'=>'ds-badge--warning','aguardando_aprovacao'=>'ds-badge--info','aprovado'=>'ds-badge--success',default=>'ds-badge--neutral' };
                            $sL = match($sr['status']) { 'aberto'=>'Aberto','em_andamento'=>'Andamento','aguardando_aprovacao'=>'Aprovacao','aprovado'=>'Aprovado',default=>$sr['status'] };
                        @endphp
                        <span class="ds-badge {{ $sB }}">{{ $sL }}</span>
                    </a>
                    @endforeach
                </div>
                @else
                <div style="text-align:center;padding:.75rem 0;">
                    <p style="font-size:.72rem;color:var(--ds-text-3);">Nenhuma solicitacao aberta</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ══════ MODAL DE SELECAO DE ATALHOS ══════ --}}
    <div class="modal-overlay" :class="showModal ? 'active' : ''" @click.self="showModal = false">
        <div class="modal-box">
            <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--ds-border-l,#E8ECF1);display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <h3 style="font-weight:700;font-size:.95rem;color:var(--ds-navy,#1B334A);margin:0;">Configurar Atalhos</h3>
                    <p style="font-size:.68rem;color:var(--ds-text-3,#8896A6);margin:.25rem 0 0;">Escolha ate 5 modulos para acesso rapido. <span style="font-weight:600;" x-text="selectedCount + '/5'"></span></p>
                </div>
                <button @click="showModal = false" style="width:2rem;height:2rem;border-radius:var(--ds-radius-xs,8px);display:flex;align-items:center;justify-content:center;background:none;border:none;cursor:pointer;color:var(--ds-text-3,#8896A6);font-size:1.1rem;transition:background .15s;"
                    onmouseover="this.style.background='var(--ds-surface-3,#EDF1F5)'" onmouseout="this.style.background='none'">&times;</button>
            </div>
            <div style="padding:.75rem 1.5rem;border-bottom:1px solid var(--ds-border-l,#E8ECF1);">
                <input type="text" x-model="moduleSearch" placeholder="Filtrar modulos..." class="ds-input" style="font-size:.78rem;">
            </div>
            <div style="padding:1rem 1.5rem;max-height:50vh;overflow-y:auto;display:flex;flex-direction:column;gap:.5rem;">
                <template x-for="mod in filteredModules" :key="mod.slug">
                    <div class="module-pick" :class="isSelected(mod.slug) ? 'selected' : ''" @click="toggleModule(mod)">
                        <div style="width:2rem;height:2rem;border-radius:var(--ds-radius-xs,8px);display:flex;align-items:center;justify-content:center;flex-shrink:0;" :style="'background:linear-gradient(135deg,'+palette(mod.grupo,0)+','+palette(mod.grupo,1)+')'">
                            <span x-html="renderIcon(mod.icone,mod.nome)" style="color:#fff;display:flex;align-items:center;justify-content:center;width:100%;height:100%;"></span>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <span style="font-size:.78rem;font-weight:600;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--ds-text,#1B334A);" x-text="mod.nome"></span>
                            <span style="font-size:.58rem;color:var(--ds-text-3,#8896A6);" x-text="grupoLabel(mod.grupo)"></span>
                        </div>
                        <div style="width:1.25rem;height:1.25rem;border-radius:4px;border:2px solid;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all 150ms;"
                             :style="isSelected(mod.slug) ? 'border-color:var(--ds-primary,#385776);background:var(--ds-primary,#385776);' : 'border-color:var(--ds-border,#D8DEE6);background:transparent;'">
                            <svg x-show="isSelected(mod.slug)" style="width:.75rem;height:.75rem;color:#fff;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </div>
                    </div>
                </template>
            </div>
            <div style="padding:1rem 1.5rem;border-top:1px solid var(--ds-border-l,#E8ECF1);display:flex;justify-content:flex-end;gap:.75rem;">
                <button @click="showModal = false" class="ds-btn ds-btn--secondary">Cancelar</button>
                <button @click="saveShortcuts()" class="ds-btn ds-btn--primary" :disabled="saving">
                    <span x-show="!saving">Salvar</span>
                    <span x-show="saving">Salvando...</span>
                </button>
            </div>
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

        renderIcon(icone, nome) {
            const p = (d) => `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${d}"/>`;
            const svg = (inner) => `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:58%;height:58%;">${inner}</svg>`;
            const icons = {
                'chart-bar':  svg(p('M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z')),
                'users':      svg(p('M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z')),
                'user-group': svg(p('M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z')),
                'user':       svg(p('M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z')),
                'target':     svg(p('M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z')),
                'bell':       svg(p('M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9')),
                'bell-alert': svg(p('M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9')),
                'adjustments-horizontal': svg(p('M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4')),
                'arrow-path': svg(p('M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15')),
                'puzzle-piece': svg(p('M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z')),
                'cog-6-tooth': svg(p('M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z') + p('M15 12a3 3 0 11-6 0 3 3 0 016 0z')),
                'cog':        svg(p('M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z') + p('M15 12a3 3 0 11-6 0 3 3 0 016 0z')),
                'chat-bubble-left-right': svg(p('M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z')),
                'document-magnifying-glass': svg(p('M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z')),
                'banknotes':  svg(p('M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z')),
                'building-office': svg(p('M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4')),
                'sparkles':   svg(p('M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z')),
                'eye':        svg(p('M15 12a3 3 0 11-6 0 3 3 0 016 0z') + p('M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z')),
                'shield-check': svg(p('M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z')),
                'ticket':     svg(p('M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z')),
                'clipboard-document-list': svg(p('M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2')),
                'presentation-chart-line': svg(p('M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z')),
                'scale':      svg(p('M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3')),
            };
            if (icons[icone]) return icons[icone];
            return `<span style="font-weight:700;font-size:.68rem;line-height:1;">${(nome||'').substring(0,2).toUpperCase()}</span>`;
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

{{-- Responsive override for 3-col row --}}
<style>
@media (max-width:1100px) {
    .ds-page > div[style*="grid-template-columns:repeat(3"] {
        grid-template-columns: 1fr !important;
    }
}
</style>
@endsection
