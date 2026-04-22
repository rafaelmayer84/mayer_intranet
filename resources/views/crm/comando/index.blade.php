@extends('layouts.app')
@section('title', 'Painel do Dono — CRM')

@php
    function _brl($v) { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
    function _k($v) {
        $v = (float) $v;
        if ($v >= 1000000) return 'R$ ' . number_format($v/1000000, 1, ',', '.') . 'M';
        if ($v >= 1000) return 'R$ ' . number_format($v/1000, 1, ',', '.') . 'k';
        return 'R$ ' . number_format($v, 0, ',', '.');
    }
    $clientesAtivos = collect($bloco_c_carteira['lifecycle_dist'])->whereIn('lifecycle', ['ativo','onboarding'])->sum('qtd');
@endphp

@push('styles')
<style>
    /* ═══ Painel do Dono — extensões do editorial design ═══ */

    #page-content-wrapper { padding: 0 !important; }

    /* ── Dual column sections ── */
    .dono-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    @media (max-width: 1200px) { .dono-grid-2 { grid-template-columns: 1fr; } }

    /* ── Funnel (estágios) ── */
    .funnel-stage {
        display: flex; align-items: center; gap: 16px;
        padding: 11px 0;
        border-bottom: 1px solid var(--rule);
    }
    .funnel-stage:last-child { border-bottom: none; }
    .funnel-name {
        width: 140px; flex-shrink: 0;
        font-size: 12px; font-weight: 600; color: var(--ink);
        letter-spacing: .01em;
    }
    .funnel-bar-wrap { flex: 1; height: 22px; background: var(--paper-2); border-radius: var(--r-xs); overflow: hidden; position: relative; }
    .funnel-bar {
        height: 100%; border-radius: var(--r-xs);
        background: linear-gradient(90deg, var(--navy-700), var(--navy-500));
        min-width: 3px;
        transform-origin: left; transform: scaleX(0);
        animation: grow-x 1s var(--ease-out) forwards;
    }
    .funnel-label {
        position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
        font-size: 11px; font-weight: 600; color: var(--ink);
        letter-spacing: .01em;
        font-variant-numeric: tabular-nums;
    }
    .funnel-meta {
        width: 80px; flex-shrink: 0;
        text-align: right;
        font-size: 11px; color: var(--ink-3);
        font-variant-numeric: tabular-nums;
        letter-spacing: .04em;
    }

    /* ── Top fechar items ── */
    .top-item {
        display: flex; align-items: center; gap: 14px;
        padding: 12px 0;
        border-bottom: 1px solid var(--rule);
        cursor: pointer;
        text-decoration: none; color: inherit;
        transition: all .18s var(--ease);
    }
    .top-item:last-child { border-bottom: none; }
    .top-item:hover { margin: 0 -10px; padding-left: 10px; padding-right: 10px; background: rgba(201,163,91,.05); border-radius: 6px; }
    .top-rank {
        width: 28px; height: 28px; flex-shrink: 0;
        border-radius: var(--r-xs);
        background: var(--paper-2);
        display: grid; place-items: center;
        font-size: 11px; font-weight: 600;
        color: var(--ink-3);
        font-variant-numeric: tabular-nums;
    }
    .top-rank.gold   { background: var(--gold-500); color: white; }
    .top-rank.silver { background: var(--ink-3);    color: white; }
    .top-rank.bronze { background: var(--gold-600); color: white; }
    .top-info { flex: 1; min-width: 0; }
    .top-title {
        font-size: 13px; font-weight: 500; color: var(--ink);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        line-height: 1.3;
    }
    .top-meta {
        font-size: 11px; color: var(--ink-3); margin-top: 2px;
        letter-spacing: .02em;
    }
    .top-value {
        text-align: right; flex-shrink: 0;
        font-weight: 500; font-size: 15px; color: var(--ink);
        font-variant-numeric: tabular-nums;
        letter-spacing: -.01em;
    }
    .top-when {
        font-size: 11px; color: var(--gold-700); margin-top: 2px;
        letter-spacing: .02em;
        font-variant-numeric: tabular-nums;
    }

    /* ── Produtividade table ── */
    .produt-table {
        display: grid;
        grid-template-columns: 40px 1fr repeat(5, 54px);
        gap: 12px;
    }
    .produt-head-cell {
        font-size: 10px; letter-spacing: .14em; text-transform: uppercase;
        color: var(--ink-3); font-weight: 600;
        padding: 8px 0;
        border-bottom: 1px solid var(--rule);
        text-align: center;
    }
    .produt-head-cell:nth-child(2) { text-align: left; padding-left: 2px; }
    .produt-head-cell:first-child { text-align: left; }
    .produt-cell {
        padding: 14px 0;
        border-bottom: 1px solid var(--rule);
        display: flex; align-items: center; justify-content: center;
        font-variant-numeric: tabular-nums;
    }
    .produt-cell:nth-child(7n+2) { justify-content: flex-start; }
    .produt-row-last { border-bottom: none !important; }
    .produt-avatar {
        width: 36px; height: 36px; border-radius: 9px;
        background: linear-gradient(135deg, var(--navy-700), var(--navy-500));
        color: white; font-weight: 500; font-size: 13px;
        display: grid; place-items: center;
        letter-spacing: -.01em;
    }
    .produt-person { display: flex; flex-direction: column; gap: 2px; }
    .produt-name { font-size: 13px; font-weight: 500; color: var(--ink); }
    .produt-carteira { font-size: 10px; color: var(--ink-3); letter-spacing: .02em; }
    .produt-num { font-size: 17px; font-weight: 300; color: var(--ink); letter-spacing: -.01em; }
    .produt-num.zero { color: var(--ink-4); }
    .produt-num.strong { color: var(--ok); font-weight: 500; }
    .produt-sla {
        font-size: 13px; font-variant-numeric: tabular-nums;
    }
    .produt-sla .ok  { color: var(--ok); font-weight: 600; }
    .produt-sla .bad { color: var(--bad); font-weight: 600; }
    .produt-sla .sep { color: var(--ink-4); margin: 0 2px; }

    /* ── Lifecycle badges (usa .badge base do editorial) ── */
    .lc-grid { display: flex; flex-wrap: wrap; gap: 8px; }
    .lc-badge {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 6px 12px;
        font-size: 11px; font-weight: 600;
        text-transform: uppercase; letter-spacing: .08em;
        border: 1px solid var(--rule);
        border-radius: var(--r-sm);
        background: var(--surface);
        color: var(--ink-2);
    }
    .lc-badge .c { font-weight: 600; font-size: 14px; color: var(--ink); font-variant-numeric: tabular-nums; letter-spacing: -.01em; }
    .lc-badge .p { font-size: 10px; color: var(--ink-3); font-weight: 500; letter-spacing: 0; }
    .lc-badge.ativo             { background: #EDF5F0; border-color: #CEE2D5; color: var(--ok); }
    .lc-badge.ativo .c          { color: var(--ok); }
    .lc-badge.onboarding        { background: #EEF3F8; border-color: #CFE0EC; color: var(--navy-600); }
    .lc-badge.onboarding .c     { color: var(--navy-700); }
    .lc-badge.adormecido        { background: var(--gold-50); border-color: var(--gold-300); color: var(--gold-700); }
    .lc-badge.adormecido .c     { color: var(--gold-700); }
    .lc-badge.inadimplente      { background: #FAEBE4; border-color: #EDD1C2; color: var(--bad); }
    .lc-badge.inadimplente .c   { color: var(--bad); }
    .lc-badge.bloqueado_adversa { background: #F5E7E9; border-color: #E4C7CB; color: #8F2E3E; }
    .lc-badge.bloqueado_adversa .c { color: #8F2E3E; }
    .lc-badge.arquivado         { background: var(--paper-2); border-color: var(--rule); color: var(--ink-3); }
    .lc-badge.arquivado .c      { color: var(--ink-3); }
    .lc-badge.arquivado_orfao   { background: var(--paper-2); border-color: var(--rule); color: var(--ink-3); }
    .lc-badge.arquivado_orfao .c{ color: var(--ink-3); }
    .lc-badge.risco             { background: #FAEBE4; border-color: #EDD1C2; color: var(--warn); }
    .lc-badge.risco .c          { color: var(--warn); }

    /* ── Sem toque ── */
    .toque-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
    .toque-card {
        border: 1px solid var(--rule);
        border-radius: var(--r-md);
        padding: 18px 20px;
        background: var(--surface);
        position: relative; overflow: hidden;
        transition: all .3s var(--ease);
    }
    .toque-card::before {
        content: ""; position: absolute; top: 0; left: 0; right: 0; height: 2px;
        background: var(--gold-500);
        transform: scaleX(0); transform-origin: left;
        transition: transform .5s var(--ease);
    }
    .toque-card:hover::before { transform: scaleX(1); }
    .toque-card:hover { border-color: var(--gold-300); transform: translateY(-2px); }
    .toque-label {
        font-size: 10px; letter-spacing: .16em; text-transform: uppercase;
        color: var(--ink-3); font-weight: 600;
        margin-bottom: 10px;
    }
    .toque-num {
        font-size: 36px; font-weight: 300;
        letter-spacing: -.03em; line-height: 1;
        color: var(--ink);
        font-variant-numeric: tabular-nums;
    }
    .toque-card.warn   .toque-num { color: var(--warn); }
    .toque-card.bad    .toque-num { color: var(--bad); }

    /* ── Health distribution bar ── */
    .health-track {
        display: flex; height: 10px;
        border-radius: 999px; overflow: hidden;
        margin: 18px 0 10px;
        gap: 1px; background: var(--rule);
    }
    .health-track .seg { transition: width .8s var(--ease-out); min-width: 2px; }
    .health-track .seg.exc { background: var(--ok); }
    .health-track .seg.bom { background: #6F9E62; }
    .health-track .seg.med { background: var(--gold-500); }
    .health-track .seg.bax { background: var(--warn); }
    .health-track .seg.cri { background: var(--bad); }
    .health-legend { display: flex; flex-wrap: wrap; gap: 12px; font-size: 11px; }
    .health-legend span { display: inline-flex; align-items: center; gap: 6px; color: var(--ink-3); letter-spacing: .02em; }
    .health-legend span::before { content: ""; width: 7px; height: 7px; border-radius: 50%; }
    .health-legend .l-exc::before { background: var(--ok); }
    .health-legend .l-bom::before { background: #6F9E62; }
    .health-legend .l-med::before { background: var(--gold-500); }
    .health-legend .l-bax::before { background: var(--warn); }
    .health-legend .l-cri::before { background: var(--bad); }

    /* ── Conc rows ── */
    .conc-row { padding: 10px 0; border-bottom: 1px solid var(--rule); }
    .conc-row:last-child { border-bottom: none; }
    .conc-head-row { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px; }
    .conc-name { font-size: 13px; font-weight: 500; color: var(--ink); }
    .conc-qty { font-size: 15px; font-weight: 400; color: var(--ink); font-variant-numeric: tabular-nums; letter-spacing: -.01em; }
    .conc-bar { height: 4px; background: var(--rule); border-radius: 999px; overflow: hidden; }
    .conc-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--gold-600), var(--gold-400));
        border-radius: 999px;
        transform-origin: left; transform: scaleX(0);
        animation: grow-x .9s var(--ease-out) .2s forwards;
    }
    .conc-bar-fill.leads { background: linear-gradient(90deg, var(--navy-700), var(--navy-500)); }

    /* ── Gates tiles ── */
    .gates-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .gate-tile {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 16px;
        background: #FAEBE4;
        border: 1px solid #EDD1C2;
        border-radius: var(--r-sm);
    }
    .gate-tile-label { font-size: 11px; font-weight: 600; color: var(--bad); text-transform: uppercase; letter-spacing: .06em; }
    .gate-tile-qty { font-size: 18px; font-weight: 500; color: var(--bad); font-variant-numeric: tabular-nums; letter-spacing: -.01em; }
    .gate-zero {
        padding: 20px;
        background: #EDF5F0;
        border: 1px solid #CEE2D5;
        border-radius: var(--r-md);
        text-align: center;
        font-size: 13px;
        color: var(--ok);
        font-weight: 500;
        letter-spacing: .02em;
    }
    .gate-zero::before { content: "✓ "; font-weight: 600; }

    /* ── Origem table ── */
    .origem-table {
        display: grid;
        grid-template-columns: 1fr 48px 72px 48px 64px;
        gap: 12px;
        align-items: center;
    }
    .origem-head {
        font-size: 10px; letter-spacing: .14em; text-transform: uppercase;
        color: var(--ink-3); font-weight: 600;
        padding: 8px 0;
        border-bottom: 1px solid var(--rule);
    }
    .origem-head:not(:first-child) { text-align: right; }
    .origem-cell {
        padding: 10px 0;
        border-bottom: 1px solid var(--rule);
        font-size: 12px;
        font-variant-numeric: tabular-nums;
    }
    .origem-cell:not(:first-child) { text-align: right; }
    .origem-row-last { border-bottom: none !important; }
    .origem-canal { color: var(--ink); font-weight: 500; }
    .origem-taxa {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 999px;
        font-size: 11px; font-weight: 600;
        letter-spacing: .02em;
    }
    .origem-taxa.hi  { background: #EDF5F0; color: var(--ok); border: 1px solid #CEE2D5; }
    .origem-taxa.mid { background: var(--gold-50); color: var(--gold-700); border: 1px solid var(--gold-300); }
    .origem-taxa.lo  { background: var(--paper-2); color: var(--ink-3); border: 1px solid var(--rule); }

    /* ── Ciclo tiles ── */
    .ciclo-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .ciclo-tile {
        padding: 14px 16px;
        background: var(--surface);
        border: 1px solid var(--rule);
        border-radius: var(--r-md);
        transition: border-color .3s var(--ease);
    }
    .ciclo-tile:hover { border-color: var(--gold-300); }
    .ciclo-canal {
        font-size: 10px; letter-spacing: .14em; text-transform: uppercase;
        color: var(--ink-3); font-weight: 600;
        margin-bottom: 8px;
    }
    .ciclo-dias {
        font-size: 28px; font-weight: 300;
        letter-spacing: -.03em; line-height: 1;
        color: var(--ink);
        font-variant-numeric: tabular-nums;
    }
    .ciclo-dias small { font-size: 11px; font-weight: 500; color: var(--ink-3); margin-left: 6px; letter-spacing: .02em; text-transform: none; }

    /* ── Sub-section divider ── */
    .sub-head {
        font-size: 10px; letter-spacing: .18em; text-transform: uppercase;
        color: var(--gold-700); font-weight: 600;
        margin: 24px 0 14px;
        display: flex; align-items: center; gap: 12px;
    }
    .sub-head::before { content: ""; width: 18px; height: 1px; background: var(--gold-500); }
    .sub-head::after { content: ""; flex: 1; height: 1px; background: linear-gradient(to right, var(--rule), transparent); }
    .sub-head:first-child { margin-top: 0; }

    .empty-line { font-size: 13px; color: var(--ink-3); font-style: italic; padding: 14px 0; text-align: center; }

    /* ── Footer ── */
    .dono-footer {
        margin-top: 48px;
        padding: 18px 0;
        border-top: 1px solid var(--rule);
        font-size: 11px;
        color: var(--ink-3);
        letter-spacing: .1em;
        text-transform: uppercase;
        text-align: center;
        font-weight: 500;
    }
    .dono-footer strong { color: var(--ink-2); font-weight: 600; letter-spacing: .04em; text-transform: none; }
</style>
@endpush

@section('content')
<div class="editorial-page">

    {{-- ══════════════════ HERO ══════════════════ --}}
    <section class="hero" style="margin-bottom: 56px;">
        <div>
            <div class="hero-eyebrow">Dashboard do Gestor · {{ now()->locale('pt_BR')->isoFormat('dddd D [de] MMMM') }}</div>
            <h1 class="hero-greeting">
                <em>Painel</em><br>
                <span class="name">do Dono</span>.
            </h1>
            <div class="hero-sub">
                Visão unificada de receita, produtividade, saúde da carteira e origem de clientes.
            </div>

            <div class="hero-stats stagger">
                <div class="hero-stat" onclick="window.location='/crm/pipeline'">
                    <div class="hero-stat-label">Ganhas no mês</div>
                    <div class="hero-stat-value">{{ $bloco_a_pipeline['ganhas_mes']->qtd ?? 0 }}</div>
                    <div class="hero-stat-delta">{{ _k($bloco_a_pipeline['ganhas_mes']->valor ?? 0) }}</div>
                </div>
                <div class="hero-stat" onclick="window.location='/crm/pipeline'">
                    <div class="hero-stat-label">Ticket médio 90d</div>
                    <div class="hero-stat-value">{{ _k($bloco_a_pipeline['ticket_medio']) }}</div>
                    <div class="hero-stat-delta">Forecast 60d: {{ _k($bloco_a_pipeline['forecast_60d']) }}</div>
                </div>
                <div class="hero-stat" onclick="window.location='/crm/carteira'">
                    <div class="hero-stat-label">Carteira ativa</div>
                    <div class="hero-stat-value">{{ $clientesAtivos }}</div>
                    <div class="hero-stat-delta">Clientes + onboarding</div>
                </div>
                <div class="hero-stat" onclick="window.location='/crm/carteira'">
                    <div class="hero-stat-label">Health médio</div>
                    <div class="hero-stat-value">{{ (int) $bloco_c_carteira['health_medio'] }}</div>
                    <div class="hero-stat-delta {{ $bloco_c_carteira['health_medio'] >= 60 ? '' : 'down' }}">Escala 0–100</div>
                </div>
            </div>
        </div>

        <div class="hero-time">
            <div class="hero-clock">{{ now()->format('H') }}<span class="colon">:</span>{{ now()->format('i') }}</div>
            Florianópolis · SC
        </div>
    </section>

    {{-- ══════════════════ BLOCO A: PIPELINE & RECEITA ══════════════════ --}}
    <section style="margin-bottom: 56px;">
        <div class="section-head">
            <div>
                <div style="font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--gold-700);font-weight:600;margin-bottom:6px;">Bloco I</div>
                <h2>Pipeline <em>&amp; receita</em>.</h2>
            </div>
            <div class="section-line"></div>
            <a href="{{ route('crm.pipeline') }}" class="section-action">Ver pipeline completo →</a>
        </div>

        <div class="dono-grid-2 stagger">
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title">Oportunidades abertas por estágio</div>
                        <div class="card-subtitle">Valor estimado · quantidade</div>
                    </div>
                </div>
                @if($bloco_a_pipeline['por_estagio']->isEmpty())
                    <div class="empty-line">Nenhuma oportunidade aberta.</div>
                @else
                    @php $maxVal = $bloco_a_pipeline['por_estagio']->max('valor') ?: 1; @endphp
                    @foreach($bloco_a_pipeline['por_estagio'] as $i => $st)
                        <div class="funnel-stage">
                            <div class="funnel-name">{{ $st->stage_name ?? 'Sem estágio' }}</div>
                            <div class="funnel-bar-wrap">
                                <div class="funnel-bar" style="width: {{ max(3, min(100, ($st->valor / $maxVal) * 100)) }}%; animation-delay: {{ 0.1 + $i * 0.08 }}s;"></div>
                                <div class="funnel-label">{{ _k($st->valor) }}</div>
                            </div>
                            <div class="funnel-meta">{{ $st->qtd }} opp.</div>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title">Top 10 a fechar</div>
                        <div class="card-subtitle">Priorizado por proximidade de ação</div>
                    </div>
                </div>
                @if($bloco_a_pipeline['top_fechar']->isEmpty())
                    <div class="empty-line">Nenhuma oportunidade com valor e prazo definido.</div>
                @else
                    @foreach($bloco_a_pipeline['top_fechar'] as $i => $t)
                        @php $rk = $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : '')); @endphp
                        <a href="{{ route('crm.accounts.show', $t->account_id) }}" class="top-item">
                            <div class="top-rank {{ $rk }}">{{ $i + 1 }}</div>
                            <div class="top-info">
                                <div class="top-title">{{ $t->title ?? $t->account_name }}</div>
                                <div class="top-meta">{{ $t->account_name }} · {{ $t->stage_name }} · {{ $t->owner_name ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="top-value">{{ _k($t->value_estimated) }}</div>
                                @if($t->next_action_at)
                                    <div class="top-when">{{ \Carbon\Carbon::parse($t->next_action_at)->format('d/m') }}</div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                @endif
            </div>
        </div>
    </section>

    {{-- ══════════════════ BLOCO B: PRODUTIVIDADE ══════════════════ --}}
    <section style="margin-bottom: 56px;">
        <div class="section-head">
            <div>
                <div style="font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--gold-700);font-weight:600;margin-bottom:6px;">Bloco II</div>
                <h2>Produtividade <em>da equipe</em>.</h2>
            </div>
            <div class="section-line"></div>
            <span class="section-action" style="cursor: default;">Semana de {{ now()->startOfWeek()->format('d/m') }} – {{ now()->endOfWeek()->format('d/m') }}</span>
        </div>

        <div class="card">
            @if(empty($bloco_b_produtividade))
                <div class="empty-line">Sem dados de advogadas esta semana.</div>
            @else
                <div class="produt-table">
                    <div class="produt-head-cell"></div>
                    <div class="produt-head-cell">Advogada</div>
                    <div class="produt-head-cell">Ativ.</div>
                    <div class="produt-head-cell">Opp.</div>
                    <div class="produt-head-cell">Gates</div>
                    <div class="produt-head-cell">Toc.</div>
                    <div class="produt-head-cell">SLA</div>

                    @php $totalRows = count($bloco_b_produtividade); @endphp
                    @foreach($bloco_b_produtividade as $idx => $p)
                        @php
                            $iniciais = collect(explode(' ', $p['nome']))->map(fn($n)=>mb_substr($n,0,1))->take(2)->implode('');
                            $lastRow = ($idx === $totalRows - 1) ? 'produt-row-last' : '';
                        @endphp
                        <div class="produt-cell {{ $lastRow }}" style="justify-content:flex-start;">
                            <div class="produt-avatar">{{ mb_strtoupper($iniciais) }}</div>
                        </div>
                        <div class="produt-cell {{ $lastRow }}" style="justify-content:flex-start;">
                            <div class="produt-person">
                                <div class="produt-name">{{ $p['nome'] }}</div>
                                <div class="produt-carteira">{{ $p['carteira_total'] }} na carteira</div>
                            </div>
                        </div>
                        <div class="produt-cell {{ $lastRow }}">
                            <div class="produt-num {{ $p['atividades_feitas'] === 0 ? 'zero' : 'strong' }}">{{ $p['atividades_feitas'] }}</div>
                        </div>
                        <div class="produt-cell {{ $lastRow }}">
                            <div class="produt-num {{ $p['oportunidades_movidas'] === 0 ? 'zero' : '' }}">{{ $p['oportunidades_movidas'] }}</div>
                        </div>
                        <div class="produt-cell {{ $lastRow }}">
                            <div class="produt-num {{ $p['gates_resolvidos'] === 0 ? 'zero' : '' }}">{{ $p['gates_resolvidos'] }}</div>
                        </div>
                        <div class="produt-cell {{ $lastRow }}">
                            <div class="produt-num {{ $p['clientes_tocados'] === 0 ? 'zero' : '' }}">{{ $p['clientes_tocados'] }}</div>
                        </div>
                        <div class="produt-cell {{ $lastRow }}">
                            <span class="produt-sla">
                                <span class="ok">{{ $p['sla_cumpridos'] }}</span>
                                <span class="sep">/</span>
                                <span class="bad">{{ $p['sla_violados'] }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- ══════════════════ BLOCO C: SAÚDE DA CARTEIRA ══════════════════ --}}
    <section style="margin-bottom: 56px;">
        <div class="section-head">
            <div>
                <div style="font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--gold-700);font-weight:600;margin-bottom:6px;">Bloco III</div>
                <h2>Saúde <em>da carteira</em>.</h2>
            </div>
            <div class="section-line"></div>
            <a href="{{ route('crm.carteira') }}" class="section-action">Abrir carteira →</a>
        </div>

        <div class="dono-grid-2 stagger">
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title">Distribuição por lifecycle</div>
                        <div class="card-subtitle">Clientes apenas</div>
                    </div>
                </div>
                @php $totalLC = $bloco_c_carteira['lifecycle_dist']->sum('qtd') ?: 1; @endphp
                <div class="lc-grid">
                    @foreach($bloco_c_carteira['lifecycle_dist'] as $lc)
                        @php $pct = round(($lc->qtd / $totalLC) * 100, 1); @endphp
                        <span class="lc-badge {{ $lc->lifecycle }}">
                            {{ str_replace('_', ' ', $lc->lifecycle) }}
                            <span class="c">{{ $lc->qtd }}</span>
                            <span class="p">{{ $pct }}%</span>
                        </span>
                    @endforeach
                </div>

                <div class="sub-head">Health score da carteira</div>
                @php
                    $hb = collect($bloco_c_carteira['health_buckets'])->keyBy('bucket');
                    $totalHB = $hb->sum('qtd') ?: 1;
                    $pctExc = round((($hb['excelente']->qtd ?? 0) / $totalHB) * 100, 1);
                    $pctBom = round((($hb['bom']->qtd ?? 0) / $totalHB) * 100, 1);
                    $pctMed = round((($hb['medio']->qtd ?? 0) / $totalHB) * 100, 1);
                    $pctBax = round((($hb['baixo']->qtd ?? 0) / $totalHB) * 100, 1);
                    $pctCri = round((($hb['critico']->qtd ?? 0) / $totalHB) * 100, 1);
                @endphp
                <div style="display:flex; justify-content:space-between; align-items:baseline;">
                    <div>
                        <span style="font-size:42px; font-weight:300; letter-spacing:-.03em; color:var(--ink); font-variant-numeric:tabular-nums;">{{ $bloco_c_carteira['health_medio'] }}</span>
                        <span style="font-size:12px; color:var(--ink-3); margin-left:8px;">média geral</span>
                    </div>
                    <span style="font-size:11px; color:var(--ink-3); letter-spacing:.08em; text-transform:uppercase; font-weight:500;">{{ $totalHB }} contas</span>
                </div>
                <div class="health-track">
                    <div class="seg exc" style="width: {{ $pctExc }}%"></div>
                    <div class="seg bom" style="width: {{ $pctBom }}%"></div>
                    <div class="seg med" style="width: {{ $pctMed }}%"></div>
                    <div class="seg bax" style="width: {{ $pctBax }}%"></div>
                    <div class="seg cri" style="width: {{ $pctCri }}%"></div>
                </div>
                <div class="health-legend">
                    <span class="l-exc">Excelente {{ $pctExc }}%</span>
                    <span class="l-bom">Bom {{ $pctBom }}%</span>
                    <span class="l-med">Médio {{ $pctMed }}%</span>
                    <span class="l-bax">Baixo {{ $pctBax }}%</span>
                    <span class="l-cri">Crítico {{ $pctCri }}%</span>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title">Clientes sem toque</div>
                        <div class="card-subtitle">Última interação registrada</div>
                    </div>
                </div>
                <div class="toque-row">
                    <div class="toque-card">
                        <div class="toque-label">30 dias</div>
                        <div class="toque-num">{{ $bloco_c_carteira['sem_toque']['30d'] }}</div>
                    </div>
                    <div class="toque-card warn">
                        <div class="toque-label">60 dias</div>
                        <div class="toque-num">{{ $bloco_c_carteira['sem_toque']['60d'] }}</div>
                    </div>
                    <div class="toque-card bad">
                        <div class="toque-label">90 dias+</div>
                        <div class="toque-num">{{ $bloco_c_carteira['sem_toque']['90d'] }}</div>
                    </div>
                </div>

                <div class="sub-head">Concentração por responsável</div>
                @if($bloco_c_carteira['concentracao']->isEmpty())
                    <div class="empty-line">Sem dados de concentração.</div>
                @else
                    @php $maxConc = $bloco_c_carteira['concentracao']->max('qtd') ?: 1; @endphp
                    @foreach($bloco_c_carteira['concentracao'] as $conc)
                        <div class="conc-row">
                            <div class="conc-head-row">
                                <span class="conc-name">{{ $conc->owner_name ?? 'Sem responsável' }}</span>
                                <span class="conc-qty">{{ $conc->qtd }}</span>
                            </div>
                            <div class="conc-bar">
                                <div class="conc-bar-fill" style="width: {{ ($conc->qtd / $maxConc) * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                @endif

                <div class="sub-head">Gates bloqueando acesso</div>
                @if($bloco_c_carteira['gates_abertos']->isEmpty())
                    <div class="gate-zero">Nenhum gate bloqueando hoje.</div>
                @else
                    <div class="gates-grid">
                        @foreach($bloco_c_carteira['gates_abertos'] as $g)
                            <div class="gate-tile">
                                <span class="gate-tile-label">{{ str_replace('_', ' ', $g->tipo) }}</span>
                                <span class="gate-tile-qty">{{ $g->qtd }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ══════════════════ BLOCO D: ORIGEM & ROI ══════════════════ --}}
    <section style="margin-bottom: 56px;">
        <div class="section-head">
            <div>
                <div style="font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--gold-700);font-weight:600;margin-bottom:6px;">Bloco IV</div>
                <h2>Origem, <em>conversão e ROI</em>.</h2>
            </div>
            <div class="section-line"></div>
            <a href="{{ route('crm.leads') }}" class="section-action">Ver leads →</a>
        </div>

        <div class="dono-grid-2 stagger">
            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title">Leads este mês</div>
                        <div class="card-subtitle">Por canal de origem · {{ now()->format('M/Y') }}</div>
                    </div>
                </div>
                @if($bloco_d_origem_roi['leads_por_origem']->isEmpty())
                    <div class="empty-line">Nenhum lead registrado este mês.</div>
                @else
                    @php $totalLM = $bloco_d_origem_roi['leads_por_origem']->sum('qtd') ?: 1; @endphp
                    @foreach($bloco_d_origem_roi['leads_por_origem'] as $l)
                        <div class="conc-row">
                            <div class="conc-head-row">
                                <span class="conc-name">{{ $l->origem_canal ?: '(sem origem)' }}</span>
                                <span class="conc-qty">{{ $l->qtd }} <span style="color:var(--ink-3); font-weight:400; font-size:12px;">· {{ round(($l->qtd/$totalLM)*100) }}%</span></span>
                            </div>
                            <div class="conc-bar">
                                <div class="conc-bar-fill leads" style="width: {{ ($l->qtd / $totalLM) * 100 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                @endif

                <div class="sub-head">Ciclo médio até fechar</div>
                @if($bloco_d_origem_roi['ciclo_medio']->isEmpty())
                    <div class="empty-line">Sem oportunidades fechadas rastreadas até a origem.</div>
                @else
                    <div class="ciclo-grid">
                        @foreach($bloco_d_origem_roi['ciclo_medio'] as $cm)
                            <div class="ciclo-tile">
                                <div class="ciclo-canal">{{ $cm->origem_canal ?: '(sem origem)' }}</div>
                                <div class="ciclo-dias">{{ round($cm->dias_medio) }} <small>dias · {{ $cm->fechadas }} opp.</small></div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title">Conversão por origem · histórico</div>
                        <div class="card-subtitle">Lead → prospect → cliente fechado</div>
                    </div>
                </div>
                @if($bloco_d_origem_roi['conversao_por_origem']->isEmpty())
                    <div class="empty-line">Sem dados históricos de conversão.</div>
                @else
                    <div class="origem-table">
                        <div class="origem-head">Origem</div>
                        <div class="origem-head">Leads</div>
                        <div class="origem-head">Prospect</div>
                        <div class="origem-head">Fechou</div>
                        <div class="origem-head">Taxa</div>

                        @php $totalConv = count($bloco_d_origem_roi['conversao_por_origem']); @endphp
                        @foreach($bloco_d_origem_roi['conversao_por_origem'] as $idx => $co)
                            @php
                                $taxa = $co->total_leads > 0 ? round(($co->fecharam / $co->total_leads) * 100, 1) : 0;
                                $tClass = $taxa >= 15 ? 'hi' : ($taxa >= 5 ? 'mid' : 'lo');
                                $last = ($idx === $totalConv - 1) ? 'origem-row-last' : '';
                            @endphp
                            <div class="origem-cell {{ $last }}"><span class="origem-canal">{{ $co->origem_canal ?: '(sem origem)' }}</span></div>
                            <div class="origem-cell {{ $last }}" style="color:var(--ink-3);">{{ $co->total_leads }}</div>
                            <div class="origem-cell {{ $last }}" style="color:var(--navy-600); font-weight:500;">{{ $co->viraram_prospect }}</div>
                            <div class="origem-cell {{ $last }}" style="color:var(--ok); font-weight:600;">{{ $co->fecharam }}</div>
                            <div class="origem-cell {{ $last }}"><span class="origem-taxa {{ $tClass }}">{{ $taxa }}%</span></div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>

    <div class="dono-footer">
        Cache 5 min · Gerado em <strong>{{ $gerado_em }}</strong> · Restrito a administradores
    </div>
</div>
@endsection
