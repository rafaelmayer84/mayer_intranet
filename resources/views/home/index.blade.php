@extends('layouts.app')

@section('title', 'Home')

@push('styles')
<style>
/* ── Página editorial: remove padding externo ── */
#page-content-wrapper { padding: 0 !important; }

/* ── Modal de atalhos (preservado) ── */
.modal-overlay {
    position: fixed; inset: 0; background: rgba(14,31,51,0.55); backdrop-filter: blur(6px);
    z-index: 9998; display: flex; align-items: center; justify-content: center;
    opacity: 0; pointer-events: none; transition: opacity 220ms;
}
.modal-overlay.active { opacity: 1; pointer-events: all; }
.modal-box {
    background: var(--surface); border-radius: var(--r-lg); width: 540px; max-width: 95vw;
    max-height: 80vh; overflow: hidden; box-shadow: var(--shadow-lg);
    border: 1px solid var(--rule);
    transform: translateY(20px); transition: transform 240ms var(--ease);
}
.modal-overlay.active .modal-box { transform: translateY(0); }
.module-pick {
    padding: 10px 14px; border-radius: var(--r-sm); cursor: pointer;
    border: 1px solid var(--rule); transition: all 150ms;
    display: flex; align-items: center; gap: 10px;
}
.module-pick:hover { border-color: var(--navy-500); background: rgba(56,87,118,0.04); }
.module-pick.selected { border-color: var(--navy-500); background: rgba(56,87,118,0.06); }

/* ── Shortcut slots ── */
.shortcut-slot {
    min-height: 90px; border: 2px dashed var(--rule); border-radius: var(--r-md);
    transition: all .25s var(--ease); cursor: pointer; position: relative;
    background: var(--surface);
}
.shortcut-slot.filled { border: 1px solid var(--rule); cursor: grab; }
.shortcut-slot.filled:hover { border-color: var(--navy-500); box-shadow: var(--shadow-md); transform: translateY(-2px); }
.shortcut-slot.filled:active { cursor: grabbing; }
.shortcut-slot.drag-over { border-color: var(--gold-500) !important; background: rgba(181,144,79,.05); box-shadow: 0 0 0 3px rgba(181,144,79,.15); }
.shortcut-slot .remove-btn {
    position: absolute; top: 6px; right: 6px; width: 22px; height: 22px; border-radius: 50%;
    background: rgba(179,66,47,0.08); color: var(--bad); display: none; align-items: center;
    justify-content: center; font-size: 14px; cursor: pointer; border: none; padding: 0; line-height: 1;
}
.shortcut-slot:hover .remove-btn { display: flex; }
.shortcut-slot .remove-btn:hover { background: rgba(179,66,47,0.18); }

/* ── Shortcut grid ── */
.shortcut-slots-grid {
    display: grid; grid-template-columns: repeat(5, 1fr); gap: .75rem;
}
@media (max-width: 1100px) { .shortcut-slots-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 640px) { .shortcut-slots-grid { grid-template-columns: repeat(2, 1fr); } }

/* ── GDP progress bars ── */
.ds-progress { height: 6px; border-radius: 999px; background: var(--rule); overflow: hidden; }
.ds-progress-fill { height: 100%; border-radius: 999px; background: var(--gold-500); transition: width .8s var(--ease-out); }

/* ── Animated countUp ── */
.count-animate { font-variant-numeric: tabular-nums; }

/* ── Pipeline bar chart ── */
.pipe-bar-chart { display: flex; align-items: flex-end; gap: 8px; padding-bottom: 4px; }
.pipe-bar-col { display: flex; flex-direction: column; align-items: center; flex: 1; }

/* ── Search dropdown ── */
.search-dropdown {
    position: absolute; z-index: 50; width: 100%; margin-top: 6px;
    background: var(--surface); border-radius: var(--r-md);
    box-shadow: var(--shadow-lg); border: 1px solid var(--rule);
    max-height: 18rem; overflow-y: auto;
}

/* ── ⌘K Command Palette ── */
.cmdpal-overlay {
    position: fixed; inset: 0;
    background: rgba(10,24,38,.55); backdrop-filter: blur(8px);
    display: grid; place-items: start center;
    padding-top: 120px; z-index: 500;
    opacity: 0; pointer-events: none;
    transition: opacity .2s var(--ease);
}
.cmdpal-overlay.open { opacity: 1; pointer-events: all; }
.cmdpal-box {
    width: 560px; max-width: 92vw;
    background: var(--surface); border: 1px solid var(--rule);
    border-radius: var(--r-lg); box-shadow: var(--shadow-lg); overflow: hidden;
    transform: translateY(-10px); transition: transform .25s var(--ease-out);
}
.cmdpal-overlay.open .cmdpal-box { transform: translateY(0); }
.cmdpal-input {
    width: 100%; padding: 18px 20px; font-size: 16px;
    font-family: var(--sans); color: var(--ink);
    background: transparent; border: 0; border-bottom: 1px solid var(--rule); outline: none;
}
.cmdpal-input::placeholder { color: var(--ink-3); }
.cmdpal-list { padding: 8px 0 12px; max-height: 400px; overflow-y: auto; }
.cmdpal-group { font-size: 10px; letter-spacing: .18em; text-transform: uppercase; color: var(--gold-600); font-weight: 600; padding: 10px 18px 6px; }
.cmdpal-item {
    display: flex; align-items: center; gap: 12px;
    padding: 9px 18px; cursor: pointer; font-size: 14px; color: var(--ink);
    transition: background .15s var(--ease);
}
.cmdpal-item:hover { background: var(--paper-2); }
.cmdpal-item svg { color: var(--gold-600); flex-shrink: 0; }
.cmdpal-hint { margin-left: auto; font-size: 11px; color: var(--ink-3); font-variant-caps: all-small-caps; letter-spacing: .06em; }

</style>
@endpush

@section('content')

{{-- ══════════════════════════════════════════════════════
     PÁGINA EDITORIAL
     ══════════════════════════════════════════════════════ --}}
<div class="editorial-page" x-data="homeApp()" id="editorial-root">

    {{-- ── HERO ── --}}
    <section class="hero" style="margin-bottom: 52px;">
        <div>
            <div class="hero-eyebrow">
                Intranet Mayer · {{ now()->locale('pt_BR')->isoFormat('dddd D') }}
            </div>
            <h1 class="hero-greeting">
                <em>{{ $saudacao }},</em><br>
                <span class="name">{{ $primeiroNome }}</span>.
            </h1>
            <div class="hero-sub">
                {{ ucfirst(now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM [de] YYYY')) }}
            </div>

            <div class="hero-stats stagger">
                <div class="hero-stat" onclick="window.location='/crm'">
                    <div class="hero-stat-label">Clientes</div>
                    <div class="hero-stat-value count-animate" data-target="{{ $volumetria['clientes'] }}">0</div>
                    <div class="hero-stat-delta">&#8593; Base ativa</div>
                </div>
                <div class="hero-stat" onclick="window.location='/vigilia'">
                    <div class="hero-stat-label">Processos</div>
                    <div class="hero-stat-value count-animate" data-target="{{ $volumetria['processos'] }}">0</div>
                    <div class="hero-stat-delta">Em acompanhamento</div>
                </div>
                <div class="hero-stat" onclick="window.location='/crm'">
                    <div class="hero-stat-label">Oportunidades</div>
                    <div class="hero-stat-value count-animate" data-target="{{ $volumetria['oportunidades'] }}">0</div>
                    @if($volumetria['oportunidades'] > 0)
                    <div class="hero-stat-delta">No pipeline CRM</div>
                    @else
                    <div class="hero-stat-delta down">Nenhuma ativa</div>
                    @endif
                </div>
                <div class="hero-stat" onclick="window.location='/avisos'">
                    <div class="hero-stat-label">Avisos</div>
                    <div class="hero-stat-value count-animate" data-target="{{ $avisos['total'] }}">0</div>
                    @if($avisos['total'] > 0)
                    <div class="hero-stat-delta down">{{ $avisos['total'] }} pendente{{ $avisos['total'] > 1 ? 's' : '' }}</div>
                    @else
                    <div class="hero-stat-delta">Tudo lido ✓</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="hero-time" id="hero-clock-wrap">
            <div class="hero-clock" id="hero-clock">--<span class="colon">:</span>--</div>
            Florianópolis · SC
        </div>
    </section>

    {{-- ── ATALHOS ── --}}
    <section style="margin-bottom: 52px;">
        <div class="section-head">
            <div>
                <div style="font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--gold-700);font-weight:600;margin-bottom:6px;">Meus Atalhos</div>
                <h2>Acesso <em>rápido</em>.</h2>
            </div>
            <div class="section-line"></div>
            <button @click="showModal = true" class="section-action" style="border:0;background:none;font-family:var(--sans);cursor:pointer;">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Configurar
            </button>
        </div>

        <div class="shortcut-slots-grid stagger">
            <template x-for="(slot, idx) in slots" :key="'s'+idx">
                <div class="shortcut-slot" :class="slot.slug ? 'filled' : ''"
                     :draggable="slot.slug ? 'true' : 'false'"
                     @dragstart="onDragStart($event, idx)"
                     @dragover.prevent="onDragOver($event)"
                     @dragleave="onDragLeave($event)"
                     @drop.prevent="onDrop($event, idx)"
                     @click="slot.slug ? goTo(slot.rota) : (showModal = true)">
                    <template x-if="slot.slug">
                        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:1rem .75rem;text-align:center;">
                            <button class="remove-btn" @click.stop="removeSlot(idx)">&times;</button>
                            <div style="width:2.75rem;height:2.75rem;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:.6rem;" :style="'background:linear-gradient(135deg,'+palette(slot.grupo,0)+','+palette(slot.grupo,1)+')'">
                                <span x-html="renderIcon(slot.icone,slot.nome)" style="color:#fff;display:flex;align-items:center;justify-content:center;width:100%;height:100%;"></span>
                            </div>
                            <span style="font-size:.72rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;width:100%;color:var(--ink);" x-text="slot.nome"></span>
                            <span style="font-size:.58rem;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;width:100%;color:var(--ink-3);" x-text="grupoLabel(slot.grupo)"></span>
                        </div>
                    </template>
                    <template x-if="!slot.slug">
                        <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:1rem .75rem;cursor:pointer;">
                            <div style="width:2.75rem;height:2.75rem;border-radius:10px;background:var(--paper-2);display:flex;align-items:center;justify-content:center;margin-bottom:.6rem;">
                                <svg style="width:1.25rem;height:1.25rem;color:var(--ink-4);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            </div>
                            <span style="font-size:.68rem;color:var(--ink-3);font-weight:500;">Adicionar</span>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </section>

    {{-- ── KPI STATS RÁPIDOS ── --}}
    <section style="margin-bottom: 52px;">
        <div class="section-head">
            <div>
                <div style="font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--gold-700);font-weight:600;margin-bottom:6px;">Operacional</div>
                <h2>Situação <em>atual</em>.</h2>
            </div>
            <div class="section-line"></div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;" class="stagger">
            {{-- Alertas CRM --}}
            <div class="kpi" onclick="window.location='/crm'">
                <div class="kpi-label">
                    <span class="kpi-icon-lg" style="background:rgba(195,122,42,.1);color:var(--warn);">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    </span>
                    Alertas CRM
                </div>
                <div class="kpi-value count-animate" data-target="{{ count($alertasCrm) }}" data-delay="100">0</div>
                <div class="kpi-delta {{ count($alertasCrm) > 0 ? 'down' : '' }}">
                    {{ count($alertasCrm) > 0 ? count($alertasCrm).' em atenção' : 'Carteira em dia ✓' }}
                </div>
            </div>

            {{-- Tickets --}}
            <div class="kpi" onclick="window.location='/nexo/tickets'">
                <div class="kpi-label">
                    <span class="kpi-icon-lg">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    </span>
                    Tickets Abertos
                </div>
                <div class="kpi-value count-animate" data-target="{{ count($ticketsAbertos) }}" data-delay="150">0</div>
                <div class="kpi-delta {{ count($ticketsAbertos) > 0 ? '' : '' }}">
                    {{ count($ticketsAbertos) > 0 ? 'Aguardando resposta' : 'Nenhum pendente ✓' }}
                </div>
            </div>

            {{-- Solicitações --}}
            <div class="kpi" onclick="window.location='/crm'">
                <div class="kpi-label">
                    <span class="kpi-icon-lg" style="background:rgba(179,66,47,.1);color:var(--bad);">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    </span>
                    Solicitações
                </div>
                <div class="kpi-value count-animate" data-target="{{ $solicitacoes['total_abertas'] }}" data-delay="200">0</div>
                <div class="kpi-delta {{ $solicitacoes['total_abertas'] > 0 ? 'down' : '' }}">
                    {{ $solicitacoes['total_abertas'] > 0 ? $solicitacoes['total_abertas'].' abertas' : 'Tudo resolvido ✓' }}
                </div>
            </div>

            {{-- Receita --}}
            <div class="kpi" style="background:linear-gradient(135deg,var(--navy-900),var(--navy-700) 75%,var(--navy-600));color:var(--paper);border-color:var(--navy-800);" onclick="window.location='/visao-gerencial'">
                <div class="kpi-label" style="color:var(--gold-400);">
                    <span style="display:grid;place-items:center;width:32px;height:32px;border-radius:8px;background:rgba(201,163,91,.15);color:var(--gold-400);flex-shrink:0;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    Receita do Mês
                </div>
                <div class="kpi-value" style="color:var(--paper);font-size:26px;margin-top:4px;">
                    R$ {{ number_format($resumoFinanceiro['receita'],0,',','.') }}
                </div>
                @if($resumoFinanceiro['var_receita'] !== null)
                <div class="kpi-delta" style="color:var(--gold-300);">
                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $resumoFinanceiro['var_receita'] >= 0 ? 'M5 10l7-7m0 0l7 7m-7-7v18' : 'M19 14l-7 7m0 0l-7-7m7 7V3' }}"/></svg>
                    {{ $resumoFinanceiro['var_receita'] >= 0 ? '+' : '' }}{{ $resumoFinanceiro['var_receita'] }}% vs. mês anterior
                </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ── PAINEL COMERCIAL ── --}}
    @if(auth()->user()->role === 'admin' || auth()->user()->role === 'socio')
    <section style="margin-bottom: 52px;">
        <div class="section-head">
            <div>
                <div style="font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--gold-700);font-weight:600;margin-bottom:6px;">Painel Comercial</div>
                <h2>{{ ucfirst(now()->locale('pt_BR')->isoFormat('MMMM')) }} <em>de {{ now()->year }}.</em></h2>
            </div>
            <div class="section-line"></div>
            <a href="{{ url('/crm') }}" class="section-action">
                Ver CRM completo
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6l6 6-6 6"/></svg>
            </a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;" class="stagger">
            {{-- Leads --}}
            <div class="kpi kpi--viz">
                <div class="kpi-label">
                    <span class="kpi-icon-lg"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg></span>
                    Leads no mês
                </div>
                <div style="display:flex;align-items:baseline;gap:10px;margin-top:4px;">
                    <div class="kpi-value count-animate" data-target="{{ $painelComercial['leadsTotal'] }}" style="font-size:52px;" data-delay="100">0</div>
                    <div class="kpi-delta">
                        <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                        {{ $painelComercial['leadsNovos'] }} novos
                    </div>
                </div>
                <div style="margin-top:14px;">
                    @php
                        $totalLeads = max($painelComercial['leadsTotal'], 1);
                        $pNovos = round(($painelComercial['leadsNovos']/$totalLeads)*100);
                        $pContato = round(($painelComercial['leadsContatados']/$totalLeads)*100);
                        $pConvert = round(($painelComercial['leadsConvert']/$totalLeads)*100);
                        $pDescart = max(0, 100 - $pNovos - $pContato - $pConvert);
                    @endphp
                    <div style="display:flex;height:6px;border-radius:999px;overflow:hidden;background:var(--rule);gap:1px;">
                        <div style="width:{{ $pNovos }}%;background:var(--navy-600);border-radius:999px 0 0 999px;transition:width .8s var(--ease-out);"></div>
                        <div style="width:{{ $pContato }}%;background:var(--gold-500);"></div>
                        <div style="width:{{ $pConvert }}%;background:var(--ok);border-radius:0 999px 999px 0;"></div>
                    </div>
                </div>
                <div style="display:flex;gap:12px;margin-top:10px;font-size:11px;color:var(--ink-3);">
                    <span style="display:flex;align-items:center;gap:5px;"><span style="width:6px;height:6px;border-radius:50%;background:var(--navy-600);display:inline-block;"></span>{{ $painelComercial['leadsNovos'] }} novos</span>
                    <span style="display:flex;align-items:center;gap:5px;"><span style="width:6px;height:6px;border-radius:50%;background:var(--gold-500);display:inline-block;"></span>{{ $painelComercial['leadsContatados'] }} contato</span>
                    <span style="display:flex;align-items:center;gap:5px;"><span style="width:6px;height:6px;border-radius:50%;background:var(--ok);display:inline-block;"></span>{{ $painelComercial['leadsConvert'] }} conv.</span>
                </div>
            </div>

            {{-- Conversão --}}
            <div class="kpi kpi--viz">
                <div class="kpi-label">
                    <span class="kpi-icon-lg"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></span>
                    Taxa de conversão
                </div>
                <div style="display:flex;align-items:center;gap:16px;margin-top:6px;">
                    <div style="position:relative;width:88px;height:88px;flex-shrink:0;">
                        @php $convPct = min(floatval($painelComercial['taxaConversao']), 100); @endphp
                        <svg width="88" height="88" style="transform:rotate(-90deg);">
                            <circle cx="44" cy="44" r="38" fill="none" stroke="var(--rule)" stroke-width="8"/>
                            <circle cx="44" cy="44" r="38" fill="none" stroke="var(--navy-500)" stroke-width="8"
                                stroke-linecap="round"
                                stroke-dasharray="{{ round(2*M_PI*38, 2) }}"
                                stroke-dashoffset="{{ round(2*M_PI*38*(1-$convPct/100), 2) }}"
                                style="transition:stroke-dashoffset 1.3s var(--ease-out);"/>
                        </svg>
                        <div style="position:absolute;inset:0;display:grid;place-items:center;text-align:center;">
                            <div style="font-size:22px;font-weight:300;color:var(--ink);letter-spacing:-.02em;line-height:1;">{{ $painelComercial['taxaConversao'] }}%</div>
                        </div>
                    </div>
                    <div style="flex:1;font-size:12px;color:var(--ink-3);">
                        <div style="font-size:22px;font-weight:300;color:var(--ink);line-height:1;">{{ $painelComercial['leadsConvert'] }} <span style="color:var(--ink-3);font-size:14px;">/ {{ $painelComercial['leadsTotal'] }}</span></div>
                        <div style="margin-top:4px;">convertidos no mês</div>
                    </div>
                </div>
            </div>

            {{-- Ganhos --}}
            <div class="kpi kpi--viz">
                <div class="kpi-label">
                    <span class="kpi-icon-lg" style="background:rgba(61,122,94,.1);color:var(--ok);"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></span>
                    Ganhos no mês
                </div>
                <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-top:8px;">
                    <div>
                        <div class="kpi-value count-animate" data-target="{{ $painelComercial['ganhosMes']->total ?? 0 }}" style="font-size:52px;" data-delay="150">0</div>
                        @if(($painelComercial['ganhosMes']->valor ?? 0) > 0)
                        <div style="font-size:14px;color:var(--ok);font-weight:600;letter-spacing:.04em;margin-top:6px;">
                            R$ {{ number_format($painelComercial['ganhosMes']->valor, 0, ',', '.') }} · FECHADOS
                        </div>
                        @else
                        <div style="font-size:12px;color:var(--ink-3);margin-top:6px;">Nenhum registro</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;" class="stagger">
            {{-- Receita do mês (feature) --}}
            <div class="kpi kpi--feature">
                <div class="kpi-label">
                    <span style="display:grid;place-items:center;width:32px;height:32px;border-radius:8px;background:rgba(201,163,91,.15);color:var(--gold-400);flex-shrink:0;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7zm0 0V5a1 1 0 011-1h12M17 13h2"/></svg>
                    </span>
                    Receita do mês · BRL
                </div>
                <div class="kpi-value" style="font-size:36px;margin-top:8px;">
                    R$ <span style="color:var(--gold-400);">{{ number_format($resumoFinanceiro['receita'],0,',','.') }}</span>
                </div>
                <div style="display:flex;gap:10px;align-items:center;margin-top:8px;">
                    @if($resumoFinanceiro['var_receita'] !== null)
                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--gold-300);background:rgba(201,163,91,.15);padding:4px 10px;border-radius:999px;border:1px solid rgba(201,163,91,.3);">
                        <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $resumoFinanceiro['var_receita'] >= 0 ? 'M5 10l7-7m0 0l7 7m-7-7v18' : 'M19 14l-7 7m0 0l-7-7m7 7V3' }}"/></svg>
                        {{ $resumoFinanceiro['var_receita'] >= 0 ? '+' : '' }}{{ $resumoFinanceiro['var_receita'] }}% vs. mês anterior
                    </span>
                    @endif
                    <span style="font-size:12px;color:rgba(245,235,211,.5);">margem {{ $resumoFinanceiro['margem'] }}%</span>
                </div>
            </div>

            {{-- Base de clientes --}}
            <div class="kpi kpi--viz">
                <div class="kpi-label">
                    <span class="kpi-icon-lg"><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg></span>
                    Base de clientes
                </div>
                <div style="display:flex;align-items:baseline;gap:10px;margin-top:4px;">
                    <div class="kpi-value count-animate" data-target="{{ $painelComercial['clientesAtivos'] }}" style="font-size:52px;" data-delay="200">0</div>
                    <div style="font-size:10px;color:var(--ink-3);font-weight:600;letter-spacing:.18em;text-transform:uppercase;">ativos</div>
                </div>
                <div style="margin-top:14px;display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div style="padding:10px 12px;background:var(--gold-50);border:1px solid var(--gold-300);border-radius:8px;">
                        <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--gold-700);font-weight:600;">Onboard</div>
                        <div style="font-size:22px;font-weight:300;color:var(--gold-700);line-height:1.1;">{{ $painelComercial['clientesOnboarding'] }}</div>
                    </div>
                    <div style="padding:10px 12px;background:var(--paper-2);border:1px solid var(--rule);border-radius:8px;">
                        <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-3);font-weight:600;">Adorm. 30d+</div>
                        <div style="font-size:22px;font-weight:300;color:var(--ink-2);line-height:1.1;">{{ $painelComercial['adormSemContato'] }}</div>
                    </div>
                </div>
            </div>

            {{-- Avisos --}}
            <div class="kpi kpi--viz" onclick="window.location='/avisos'" style="cursor:pointer;">
                <div class="kpi-label">
                    <span class="kpi-icon-lg" style="{{ $avisos['total'] > 0 ? 'background:rgba(179,66,47,.1);color:var(--bad);' : '' }}">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </span>
                    Avisos
                </div>
                <div style="display:flex;align-items:baseline;gap:10px;margin-top:4px;">
                    <div class="kpi-value count-animate" data-target="{{ $avisos['total'] }}" style="font-size:52px;" data-delay="250">0</div>
                    <div class="kpi-delta {{ $avisos['total'] > 0 ? 'down' : '' }}">{{ $avisos['total'] > 0 ? 'pendentes' : 'tudo lido ✓' }}</div>
                </div>
                @if(count($avisos['avisos']) > 0)
                <div style="margin-top:12px;display:flex;flex-direction:column;gap:4px;">
                    @foreach(array_slice($avisos['avisos'],0,2) as $av)
                    <div style="font-size:11px;color:var(--ink-3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        @if($av['destaque'])<span style="color:var(--warn);">★</span>@else<span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--rule);margin-right:4px;"></span>@endif
                        {{ $av['titulo'] }}
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        {{-- Pipeline CRM --}}
        <div class="card">
            <div class="card-head">
                <div>
                    <div class="card-subtitle">CRM · pipeline em aberto</div>
                    <div class="card-title">Funil <span style="font-weight:300;color:var(--navy-500);">comercial</span></div>
                </div>
                @php $totalPipelineValor = array_sum(array_column($painelComercial['pipeline'], 'valor')); @endphp
                <div style="margin-left:auto;text-align:right;">
                    <div style="font-size:28px;font-weight:300;color:var(--ink);letter-spacing:-.02em;line-height:1;">R$ {{ number_format($totalPipelineValor/1000,0) }}k</div>
                    <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-3);margin-top:4px;font-weight:600;">em movimento</div>
                </div>
            </div>
            <div class="pipeline">
                @php $maxPipeline = max(array_column($painelComercial['pipeline'], 'total') ?: [1]); @endphp
                @foreach($painelComercial['pipeline'] as $i => $stage)
                @php
                    $isAccent = in_array($stage['nome'], ['Negociação', 'Proposta']);
                    $barH = max(6, intval(($stage['total'] / max($maxPipeline,1)) * 100));
                    $delay = round(0.3 + $i * 0.08, 2);
                @endphp
                <div class="pipe-col {{ $isAccent ? 'accent' : '' }}">
                    <div class="pipe-top">
                        <div class="pipe-count">{{ $stage['total'] }}</div>
                    </div>
                    <div class="pipe-bar-wrap">
                        <div class="pipe-bar" style="height:{{ $barH }}%;--d:{{ $delay }}s;"></div>
                    </div>
                    <div class="pipe-label">{{ $stage['nome'] }}</div>
                    @if($stage['valor'] > 0)
                    <div class="pipe-value">R$ {{ number_format($stage['valor']/1000,0) }}k</div>
                    @else
                    <div class="pipe-value">—</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    {{-- ── GDP + FINANCEIRO ── --}}
    <section style="margin-bottom: 52px;">
        <div class="section-head">
            <div>
                <div style="font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--gold-700);font-weight:600;margin-bottom:6px;">Performance</div>
                <h2>Meu <em>desempenho</em>.</h2>
            </div>
            <div class="section-line"></div>
        </div>

        <div style="display:grid;grid-template-columns:1.1fr 1fr;gap:20px;">
            {{-- GDP Score --}}
            <div class="card" style="padding:28px 30px;">
                <div class="card-head">
                    <div>
                        <div class="card-subtitle">Referência {{ now()->format('m/Y') }}</div>
                        <div class="card-title">Meu Score <span style="font-weight:300;color:var(--navy-500);">GDP</span></div>
                    </div>
                    @if($gdpScore)
                    <div style="margin-left:auto;display:flex;align-items:center;gap:10px;">
                        @if($gdpScore['variacao'] !== null)
                        <span class="badge {{ $gdpScore['variacao'] >= 0 ? 'ok' : 'bad' }}">{{ $gdpScore['variacao'] >= 0 ? '+' : '' }}{{ $gdpScore['variacao'] }} pts</span>
                        @endif
                        @if($gdpScore['ranking'])
                        <span class="badge gold">★ {{ $gdpScore['ranking'] }}º de {{ $gdpScore['total_participantes'] }}</span>
                        @endif
                    </div>
                    @endif
                </div>

                @if($gdpScore)
                <div class="score-wrap">
                    <div class="score-ring">
                        @php
                            $scoreTotal = min($gdpScore['score_total'], 100);
                            $C = round(2 * M_PI * 66, 2);
                            $off = round($C - ($scoreTotal / 100) * $C, 2);
                        @endphp
                        <svg viewBox="0 0 150 150">
                            <circle class="score-ring-track" cx="75" cy="75" r="66" fill="none" stroke-width="10"/>
                            <circle class="score-ring-fill" cx="75" cy="75" r="66" fill="none" stroke-width="10"
                                stroke-linecap="round"
                                style="--circ:{{ $C }};--off:{{ $off }};"/>
                        </svg>
                        <div class="score-ring-center">
                            <div>
                                <div class="score-value">{{ floor($gdpScore['score_total']) }}<small>,{{ substr(number_format($gdpScore['score_total'],1),strpos(number_format($gdpScore['score_total'],1),'.')+1) }}</small></div>
                                <div class="score-label">de 100</div>
                            </div>
                        </div>
                    </div>

                    <div class="score-breakdown">
                        @php $eixos = [
                            ['code'=>'JUR','label'=>'Jurídico','score'=>$gdpScore['score_juridico']],
                            ['code'=>'FIN','label'=>'Financeiro','score'=>$gdpScore['score_financeiro']],
                            ['code'=>'DEV','label'=>'Desenvolvimento','score'=>$gdpScore['score_desenvolvimento']],
                            ['code'=>'ATE','label'=>'Atendimento','score'=>$gdpScore['score_atendimento']],
                        ]; @endphp
                        @foreach($eixos as $i => $e)
                        <div class="score-row">
                            <span class="score-row-code">{{ $e['code'] }}</span>
                            <div class="score-row-track">
                                <div class="score-row-fill" style="--w:{{ min($e['score'],100)/100 }};--d:{{ 0.3+$i*0.1 }}s;"></div>
                            </div>
                            <span class="score-row-value">{{ number_format($e['score'],1,',','.') }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @else
                <div style="text-align:center;padding:40px 0;">
                    <p style="color:var(--ink-3);font-size:13px;">Aguardando primeira apuração GDP</p>
                </div>
                @endif
            </div>

            {{-- Financeiro --}}
            <div class="card fin-card">
                <div>
                    <div class="card-subtitle" style="margin-bottom:10px;">Financeiro · {{ now()->locale('pt_BR')->isoFormat('MMMM') }}</div>
                    <div class="fin-value">R$ {{ number_format($resumoFinanceiro['receita'],0,',','.') }}</div>
                    <div class="fin-sub">
                        <span style="color:var(--ok);font-weight:500;">
                            @if($resumoFinanceiro['var_receita'] !== null){{ $resumoFinanceiro['var_receita'] >= 0 ? '+' : '' }}{{ $resumoFinanceiro['var_receita'] }}% vs. mês anterior
                            @endif
                        </span>
                        · margem {{ $resumoFinanceiro['margem'] }}%
                    </div>
                    <div style="margin-top:18px;display:flex;gap:18px;font-size:12px;color:var(--ink-3);">
                        <div>
                            <div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;">Receita</div>
                            <div style="font-size:20px;font-weight:300;color:var(--ok);">R$ {{ number_format($resumoFinanceiro['receita'],0,',','.') }}</div>
                        </div>
                        <div>
                            <div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;">Despesa</div>
                            <div style="font-size:20px;font-weight:300;color:var(--bad);">R$ {{ number_format($resumoFinanceiro['despesa'],0,',','.') }}</div>
                        </div>
                        <div>
                            <div style="font-size:11px;letter-spacing:.1em;text-transform:uppercase;">Resultado</div>
                            <div style="font-size:20px;font-weight:300;color:{{ $resumoFinanceiro['resultado'] >= 0 ? 'var(--ok)' : 'var(--bad)' }};">R$ {{ number_format($resumoFinanceiro['resultado'],0,',','.') }}</div>
                        </div>
                    </div>
                </div>
                <div style="margin-top:auto;padding-top:16px;border-top:1px solid var(--rule);">
                    <svg id="fin-sparkline" viewBox="0 0 300 80" preserveAspectRatio="none" style="width:100%;height:80px;display:block;">
                        <defs>
                            <linearGradient id="finGrad" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0" stop-color="var(--gold-500)" stop-opacity=".25"/>
                                <stop offset="1" stop-color="var(--gold-500)" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <path d="M0,70 Q40,60 80,50 T160,30 T240,15 T300,5" fill="url(#finGrad)" style="animation:rise .8s var(--ease-out) .4s both;"/>
                        <path d="M0,70 Q40,60 80,50 T160,30 T240,15 T300,5" fill="none" stroke="var(--gold-600)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="900" stroke-dashoffset="900" style="animation:draw 1.4s var(--ease-out) .3s forwards;"/>
                    </svg>
                </div>
            </div>
        </div>
    </section>

    {{-- ── CENTRAL DE ATENÇÃO ── --}}
    <section style="margin-bottom: 52px;">
        <div class="section-head">
            <div>
                <div style="font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--gold-700);font-weight:600;margin-bottom:6px;">Central de atenção</div>
                <h2>O que precisa <em>de você</em>.</h2>
            </div>
            <div class="section-line"></div>
        </div>

        <div class="list-grid stagger">
            {{-- Alertas CRM --}}
            <div class="card list-card">
                <div class="card-head">
                    <div>
                        <div class="card-subtitle">Comercial · pipeline</div>
                        <div class="card-title">Alertas CRM</div>
                    </div>
                    <div class="list-count">{{ count($alertasCrm) }}</div>
                </div>
                @if(count($alertasCrm) > 0)
                <div>
                    @foreach($alertasCrm as $al)
                    <a href="{{ url($al['url'] ?? '/crm') }}" style="text-decoration:none;">
                        <div class="list-item">
                            <span class="list-dot" style="background:var(--warn);"></span>
                            <div class="list-item-title">{{ $al['titulo'] }}</div>
                            <div class="list-item-time">{{ $al['descricao'] ?? '' }}</div>
                        </div>
                    </a>
                    @endforeach
                </div>
                @else
                <div class="list-empty">Carteira em dia — nenhuma oportunidade exige atenção agora.</div>
                @endif
            </div>

            {{-- Tickets --}}
            <div class="card list-card">
                <div class="card-head">
                    <div>
                        <div class="card-subtitle">SIATE · administração</div>
                        <div class="card-title">Tickets abertos</div>
                    </div>
                    <div class="list-count">{{ count($ticketsAbertos) }}</div>
                </div>
                @if(count($ticketsAbertos) > 0)
                <div>
                    @foreach($ticketsAbertos as $tk)
                    <div class="list-item">
                        <span class="list-dot" style="background:{{ ($tk['prioridade'] ?? '') === 'alta' ? 'var(--bad)' : 'var(--gold-500)' }};"></span>
                        <div class="list-item-title">#{{ $tk['id'] }} · {{ $tk['titulo'] }}</div>
                        <div class="list-item-time">{{ $tk['criado_em'] ?? '' }}</div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="list-empty">Zero tickets pendentes. Respira.</div>
                @endif
            </div>

            {{-- Solicitações --}}
            <div class="card list-card">
                <div class="card-head">
                    <div>
                        <div class="card-subtitle">CRM · clientes</div>
                        <div class="card-title">Solicitações</div>
                    </div>
                    <div class="list-count">{{ $solicitacoes['total_abertas'] }}</div>
                </div>
                @if(count($solicitacoes['items']) > 0)
                <div>
                    @foreach(array_slice($solicitacoes['items'], 0, 5) as $sr)
                    <a href="{{ url('/crm/solicitacoes/'.$sr['id']) }}" style="text-decoration:none;">
                        <div class="list-item">
                            <span class="list-dot" style="background:{{ $sr['priority'] === 'urgente' ? 'var(--bad)' : 'var(--gold-500)' }};"></span>
                            <div class="list-item-title">#{{ $sr['id'] }} · {{ $sr['subject'] }}</div>
                            <div class="list-item-time">{{ $sr['status'] ?? '' }}</div>
                        </div>
                    </a>
                    @endforeach
                </div>
                @else
                <div class="list-empty">Nenhuma solicitação aberta</div>
                @endif
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer style="margin-top:60px;padding-top:32px;border-top:1px solid var(--rule);display:flex;align-items:baseline;justify-content:space-between;font-size:11px;color:var(--ink-3);letter-spacing:.12em;text-transform:uppercase;font-weight:600;">
        <div>Mayer Sociedade de Advogados · Intranet <em style="font-style:normal;font-weight:300;text-transform:none;letter-spacing:0;color:var(--gold-700);">edição editorial</em></div>
        <div>Florianópolis · SC</div>
    </footer>

    {{-- ══════ MODAL DE ATALHOS ══════ --}}
    <div class="modal-overlay" :class="showModal ? 'active' : ''" @click.self="showModal = false">
        <div class="modal-box">
            <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--rule);display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <h3 style="font-weight:700;font-size:.95rem;color:var(--ink);margin:0;">Configurar Atalhos</h3>
                    <p style="font-size:.68rem;color:var(--ink-3);margin:.25rem 0 0;">Escolha até 5 módulos. <span style="font-weight:600;" x-text="selectedCount + '/5'"></span></p>
                </div>
                <button @click="showModal = false" style="width:2rem;height:2rem;border-radius:8px;display:flex;align-items:center;justify-content:center;background:none;border:none;cursor:pointer;color:var(--ink-3);font-size:1.1rem;">&times;</button>
            </div>
            <div style="padding:.75rem 1.5rem;border-bottom:1px solid var(--rule);">
                <input type="text" x-model="moduleSearch" placeholder="Filtrar módulos..." style="width:100%;padding:8px 12px;border:1px solid var(--rule);border-radius:8px;font-size:13px;font-family:var(--sans);color:var(--ink);background:var(--surface);outline:none;">
            </div>
            <div style="padding:1rem 1.5rem;max-height:50vh;overflow-y:auto;display:flex;flex-direction:column;gap:.5rem;">
                <template x-for="mod in filteredModules" :key="mod.slug">
                    <div class="module-pick" :class="isSelected(mod.slug) ? 'selected' : ''" @click="toggleModule(mod)">
                        <div style="width:2rem;height:2rem;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;" :style="'background:linear-gradient(135deg,'+palette(mod.grupo,0)+','+palette(mod.grupo,1)+')'">
                            <span x-html="renderIcon(mod.icone,mod.nome)" style="color:#fff;display:flex;align-items:center;justify-content:center;width:100%;height:100%;"></span>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <span style="font-size:.78rem;font-weight:600;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--ink);" x-text="mod.nome"></span>
                            <span style="font-size:.58rem;color:var(--ink-3);" x-text="grupoLabel(mod.grupo)"></span>
                        </div>
                        <div style="width:1.25rem;height:1.25rem;border-radius:4px;border:2px solid;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all 150ms;"
                             :style="isSelected(mod.slug) ? 'border-color:var(--navy-500);background:var(--navy-500);' : 'border-color:var(--rule);background:transparent;'">
                            <svg x-show="isSelected(mod.slug)" style="width:.75rem;height:.75rem;color:#fff;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </div>
                    </div>
                </template>
            </div>
            <div style="padding:1rem 1.5rem;border-top:1px solid var(--rule);display:flex;justify-content:flex-end;gap:.75rem;">
                <button @click="showModal = false" style="padding:8px 16px;font-size:13px;border:1px solid var(--rule);border-radius:8px;background:var(--paper-2);color:var(--ink-2);cursor:pointer;font-family:var(--sans);">Cancelar</button>
                <button @click="saveShortcuts()" style="padding:8px 18px;font-size:13px;border:0;border-radius:8px;background:var(--navy-700);color:var(--paper);cursor:pointer;font-family:var(--sans);" :disabled="saving">
                    <span x-show="!saving">Salvar</span>
                    <span x-show="saving">Salvando...</span>
                </button>
            </div>
        </div>
    </div>

</div>

{{-- ══════ COMMAND PALETTE (⌘K) ══════ --}}
<div class="cmdpal-overlay" id="cmdpal-overlay" onclick="if(event.target===this)closeCmdPal()">
    <div class="cmdpal-box">
        <input class="cmdpal-input" id="cmdpal-input" placeholder="O que você quer fazer?" oninput="filterCmdPal(this.value)">
        <div class="cmdpal-list" id="cmdpal-list">
            <div class="cmdpal-group">Navegação</div>
            <div class="cmdpal-item" onclick="window.location='/home'"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg><span>Home</span><span class="cmdpal-hint">G H</span></div>
            <div class="cmdpal-item" onclick="window.location='/crm'"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg><span>CRM — Central de Leads</span><span class="cmdpal-hint">G C</span></div>
            <div class="cmdpal-item" onclick="window.location='/nexo/atendimento'"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21l1.5-4.5A8 8 0 1111.7 21H12a8 8 0 01-4.5-1.4L3 21z"/></svg><span>NEXO — Atendimento</span><span class="cmdpal-hint">G N</span></div>
            <div class="cmdpal-item" onclick="window.location='/vigilia'"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="1.5" fill="none"/><circle cx="12" cy="12" r="6" stroke-width="1.5" fill="none"/><circle cx="12" cy="12" r="2" fill="currentColor"/></svg><span>VIGÍLIA — Accountability</span></div>
            <div class="cmdpal-item" onclick="window.location='/precificacao'"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 3h7l4 4v13a1 1 0 01-1 1H7a1 1 0 01-1-1V4a1 1 0 011-1z"/></svg><span>SIPEX — Nova proposta</span></div>
            <div class="cmdpal-item" onclick="window.location='/gdp'"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/></svg><span>GDP — Minha Performance</span></div>
            <div class="cmdpal-group" style="margin-top:8px;">Ações rápidas</div>
            <div class="cmdpal-item" onclick="window.location='/crm/leads/create'"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg><span>Novo lead</span></div>
            <div class="cmdpal-item" onclick="window.location='/nexo/tickets/create'"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg><span>Abrir ticket SIATE</span></div>
            <div class="cmdpal-item" onclick="window.location='/admin/relatorios-ceo'"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 20h18M5 20V10m5 10V4m5 16v-8m5 8V7"/></svg><span>Relatórios CEO</span></div>
        </div>
    </div>
</div>


@push('scripts')
<script>
// ── Clock ──
function updateClock() {
    const now = new Date();
    const hh = String(now.getHours()).padStart(2,'0');
    const mm = String(now.getMinutes()).padStart(2,'0');
    const el = document.getElementById('hero-clock');
    if (el) el.innerHTML = hh + '<span class="colon">:</span>' + mm;
}
updateClock();
setInterval(updateClock, 1000);

// ── Count-up animation ──
function countUp(el, target, dur = 1300, delay = 80) {
    const t = parseInt(target) || 0;
    if (t === 0) { el.textContent = '0'; return; }
    let start;
    const d = parseInt(el.dataset.delay) || delay;
    setTimeout(() => {
        const step = (ts) => {
            if (!start) start = ts;
            const p = Math.min((ts - start) / dur, 1);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(t * eased).toLocaleString('pt-BR');
            if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }, d);
}
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.count-animate[data-target]').forEach(el => {
        countUp(el, el.dataset.target);
    });
});

// ── ⌘K Command Palette ──
function openCmdPal() {
    document.getElementById('cmdpal-overlay').classList.add('open');
    setTimeout(() => document.getElementById('cmdpal-input')?.focus(), 60);
}
function closeCmdPal() {
    document.getElementById('cmdpal-overlay').classList.remove('open');
    document.getElementById('cmdpal-input').value = '';
    filterCmdPal('');
}
function filterCmdPal(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#cmdpal-list .cmdpal-item').forEach(el => {
        el.style.display = !q || el.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
document.addEventListener('keydown', e => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        document.getElementById('cmdpal-overlay').classList.contains('open') ? closeCmdPal() : openCmdPal();
    }
    if (e.key === 'Escape') closeCmdPal();
});


</script>

<script>
// ── Alpine.js homeApp (shortcuts) ──
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
        showModal: false, saving: false, moduleSearch: '', dragIdx: null,
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
                if (empty >= 0) this.slots[empty] = { slug: mod.slug, nome: mod.nome, icone: mod.icone, rota: mod.rota, grupo: mod.grupo };
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
                await fetch('{{ url("/home/shortcuts") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ slugs })
                });
            } catch(e) {}
            this.saving = false; this.showModal = false;
        },

        goTo(rota) {
            if (!rota) return;
            const routeMap = {
                'nexo.atendimento': '/nexo/atendimento', 'nexo.gerencial': '/nexo/gerencial',
                'siric.index': '/siric', 'precificacao.index': '/precificacao',
                'leads.index': '/crm/leads', 'manuais-normativos.index': '/manuais',
                'avisos.index': '/avisos', 'admin.avisos.index': '/admin/avisos',
                'minha-performance': '/gdp', 'gdp.minha-performance': '/gdp',
                'equipe': '/gdp/equipe', 'configurar-metas': '/gdp/acordo',
                'visao-gerencial': '/visao-gerencial', 'clientes-mercado': '/clientes-mercado',
                'resultados.bsc.processos-internos.index': '/processos-internos',
                'admin.metas-kpi-mensais': '/admin/metas-kpi-mensais',
                'admin.usuarios.index': '/admin/usuarios',
                'admin.sincronizacao-unificada.index': '/admin/sincronizacao-unificada',
                '/vigilia': '/vigilia',
            };
            const url = routeMap[rota] || '/' + rota.replace(/^\//,'');
            window.location.href = '{{ url("/") }}' + url;
        },

        onDragStart(e, idx) { this.dragIdx = idx; e.dataTransfer.effectAllowed = 'move'; },
        onDragOver(e) { e.currentTarget.classList.add('drag-over'); },
        onDragLeave(e) { e.currentTarget.classList.remove('drag-over'); },
        onDrop(e, targetIdx) {
            e.currentTarget.classList.remove('drag-over');
            if (this.dragIdx === null || this.dragIdx === targetIdx) return;
            const tmp = { ...this.slots[targetIdx] };
            this.slots[targetIdx] = { ...this.slots[this.dragIdx] };
            this.slots[this.dragIdx] = tmp;
            this.dragIdx = null; this.saveShortcuts();
        },

        palette(grupo, idx) {
            const map = { 'resultados': ['#385776','#1B334A'], 'operacional': ['#0D9467','#065F46'], 'gdp': ['#B45309','#92400E'], 'admin': ['#6B21A8','#4C1D95'], 'avisos': ['#DC2626','#991B1B'], 'vigilia': ['#0369A1','#075985'] };
            const key = (grupo || '').toLowerCase().split('.')[0];
            return (map[key] || ['#385776','#1B334A'])[idx];
        },
        grupoLabel(grupo) {
            const map = { 'resultados':'Dashboards', 'operacional':'Operacional', 'gdp':'Performance', 'admin':'Admin', 'avisos':'Comunicação', 'vigilia':'Monitoramento' };
            const key = (grupo || '').toLowerCase().split('.')[0];
            return map[key] || grupo || '';
        },
        renderIcon(icone, nome) {
            const p = (d) => `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${d}"/>`;
            const svg = (inner) => `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:58%;height:58%;">${inner}</svg>`;
            const icons = {
                'chart-bar': svg(p('M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z')),
                'users': svg(p('M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z')),
                'user': svg(p('M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z')),
                'target': svg(p('M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z')),
                'bell': svg(p('M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9')),
                'arrow-path': svg(p('M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15')),
                'cog': svg(p('M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z') + p('M15 12a3 3 0 11-6 0 3 3 0 016 0z')),
                'eye': svg(p('M15 12a3 3 0 11-6 0 3 3 0 016 0z') + p('M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z')),
                'ticket': svg(p('M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z')),
                'chat-bubble-left-right': svg(p('M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z')),
                'sparkles': svg(p('M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z')),
                'banknotes': svg(p('M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z')),
                'scale': svg(p('M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3')),
                'puzzle-piece': svg(p('M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.39 48.39 0 01-4.163-.3c.186 1.613.293 3.25.315 4.907a.656.656 0 01-.658.663v0c-.355 0-.676-.186-.959-.401a1.647 1.647 0 00-1.003-.349c-1.036 0-1.875 1.007-1.875 2.25s.84 2.25 1.875 2.25c.369 0 .713-.128 1.003-.349.283-.215.604-.401.959-.401v0c.31 0 .555.26.532.57a48.039 48.039 0 01-.642 5.056c1.518.19 3.058.309 4.616.354a.64.64 0 00.657-.643v0c0-.355-.186-.676-.401-.959a1.647 1.647 0 01-.349-1.003c0-1.035 1.008-1.875 2.25-1.875 1.243 0 2.25.84 2.25 1.875 0 .369-.128.713-.349 1.003-.215.283-.4.604-.4.959v0c0 .333.277.599.61.58a48.1 48.1 0 005.427-.63 48.05 48.05 0 00.582-4.717.532.532 0 00-.533-.57v0c-.355 0-.676.186-.959.401-.29.221-.634.349-1.003.349-1.035 0-1.875-1.007-1.875-2.25s.84-2.25 1.875-2.25c.37 0 .713.128 1.003.349.283.215.604.401.96.401v0a.656.656 0 00.658-.663 48.422 48.422 0 00-.37-5.36c-1.886.342-3.81.574-5.766.689a.578.578 0 01-.61-.58v0z')),
                'cog-6-tooth': svg(p('M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z') + p('M15 12a3 3 0 11-6 0 3 3 0 016 0z')),
                'tag': svg(p('M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z') + p('M6 6h.008v.008H6V6z')),
                'bell-alert': svg(p('M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0M3.124 7.5A8.969 8.969 0 015.292 3m13.416 0a8.969 8.969 0 012.168 4.5')),
                'user-group': svg(p('M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z')),
                'adjustments-horizontal': svg(p('M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75')),
                'megaphone': svg(p('M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46')),
                'presentation-chart-bar': svg(p('M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5m.75-9l3-3 2.148 2.148A12.061 12.061 0 0116.5 7.605')),
                'shield-check': svg(p('M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z')),
                'currency-dollar': svg(p('M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4M21 12a9 9 0 11-18 0 9 9 0 0118 0z')),
                'book-open': svg(p('M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25')),
                'building-office-2': svg(p('M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21')),
                'squares-2x2': svg(p('M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z')),
                'bi-eye': svg(p('M15 12a3 3 0 11-6 0 3 3 0 016 0z') + p('M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z')),
            };
            if (icons[icone]) return icons[icone];
            return `<span style="font-weight:700;font-size:.68rem;">${(nome||'').substring(0,2).toUpperCase()}</span>`;
        },
    };
}
</script>
@endpush

@endsection
