@extends('layouts.app')
@section('title', 'VIGÍLIA — Comando')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/editorial-design.css') }}">
<style>
/* ── Página editorial: remove padding do wrapper ── */
#page-content-wrapper { padding: 0 !important; }

/* ── Hero VIGÍLIA (número-comando) ── */
.vg-hero-greeting {
    font-weight: 300;
    font-size: clamp(44px, 5.6vw, 74px);
    line-height: 1.0; letter-spacing: -.03em;
    color: var(--ink); margin: 0 0 6px;
}
.vg-hero-greeting .num {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-style: italic; font-weight: 600;
    color: var(--bad); font-size: 1.05em;
    letter-spacing: -.04em;
}
.vg-hero-greeting em { font-style: normal; font-weight: 600; color: var(--navy-700); }
.vg-hero-greeting .name {
    font-weight: 700; color: var(--navy-700);
    display: inline-block; position: relative;
}
.vg-hero-greeting .name::after {
    content: ""; position: absolute; left: 0; right: 0; bottom: .08em; height: .45em;
    background: linear-gradient(to top, rgba(201,163,91,.22), transparent);
    z-index: -1; border-radius: 2px;
}
.hero-stat .val-bad  { color: var(--bad); }
.hero-stat .val-warn { color: var(--warn); }
.hero-stat .val-ok   { color: var(--ok); }

/* ── Chips filtro ── */
.chip-row {
    display: flex; flex-wrap: wrap; gap: 8px;
    margin-bottom: 22px; padding-bottom: 18px;
    border-bottom: 1px dashed var(--rule);
}
.chip {
    font-family: var(--sans);
    font-size: 12px; font-weight: 600; letter-spacing: .04em;
    padding: 8px 14px; border-radius: 999px;
    border: 1px solid var(--rule); background: var(--surface);
    color: var(--ink-2); cursor: pointer;
    transition: all .25s var(--ease);
    display: inline-flex; align-items: center; gap: 8px;
}
.chip .ct {
    font-variant-numeric: tabular-nums; font-weight: 700; font-size: 11px;
    color: var(--ink-3); background: var(--paper-2);
    padding: 1px 8px; border-radius: 999px;
}
.chip:hover { border-color: var(--gold-400); color: var(--gold-700); }
.chip--active { background: var(--navy-700); color: var(--paper); border-color: var(--navy-700); }
.chip--active .ct { background: rgba(255,255,255,.12); color: var(--gold-300); }
.chip--bad  { border-color: rgba(179,66,47,.35); color: var(--bad); background: #FAEBE4; }
.chip--bad .ct { background: rgba(179,66,47,.12); color: var(--bad); }
.chip--warn { border-color: rgba(195,122,42,.35); color: var(--warn); background: #FAF1E4; }
.chip--warn .ct { background: rgba(195,122,42,.12); color: var(--warn); }

/* ── Inbox ── */
.inbox { display: flex; flex-direction: column; }
.inbox-item {
    display: grid;
    grid-template-columns: 140px 1fr auto;
    gap: 24px; align-items: center;
    padding: 22px 4px 22px 16px;
    border-bottom: 1px solid var(--rule);
    transition: all .25s var(--ease);
    cursor: pointer; position: relative;
}
.inbox-item:first-child { border-top: 1px solid var(--rule); }
.inbox-item:hover { background: rgba(201,163,91,.04); padding-left: 24px; }
.inbox-item::before {
    content: ""; position: absolute; left: 0; top: 0; bottom: 0;
    width: 3px; background: transparent;
    transition: background .25s var(--ease);
}
.inbox-item--critical::before { background: var(--bad); }
.inbox-item--high::before     { background: var(--warn); }
.inbox-item--medium::before   { background: var(--gold-400); }
.inbox-item--suspect::before  { background: var(--navy-500); }
.inbox-item--low::before      { background: var(--rule-2); }
.inbox-item--ok::before       { background: var(--ok); }
.inbox-tag { display: flex; flex-direction: column; gap: 4px; align-items: flex-start; }
.inbox-tag-status {
    font-size: 10px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
    padding: 4px 10px; border-radius: 4px;
}
.inbox-tag-status.crit { background: var(--bad); color: var(--paper); }
.inbox-tag-status.high { background: var(--warn); color: var(--paper); }
.inbox-tag-status.med  { background: var(--gold-100); color: var(--gold-700); }
.inbox-tag-status.susp { background: var(--paper-2); color: var(--navy-700); border: 1px solid var(--navy-500); }
.inbox-tag-status.low  { background: var(--paper-2); color: var(--ink-3); }
.inbox-tag-status.ok   { background: #E9F3EC; color: var(--ok); }
.inbox-tag-time {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-style: italic; font-size: 12px; color: var(--ink-3);
}
.inbox-body { min-width: 0; }
.inbox-process {
    font-variant-numeric: tabular-nums;
    font-size: 11px; letter-spacing: .08em;
    color: var(--ink-3); font-weight: 600; margin-bottom: 6px;
}
.inbox-event {
    font-size: 15px; line-height: 1.35;
    color: var(--ink); font-weight: 500; letter-spacing: -.005em;
    margin-bottom: 8px;
    overflow: hidden;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
}
.inbox-meta { display: flex; gap: 12px; align-items: center; font-size: 12px; color: var(--ink-3); flex-wrap: wrap; }
.inbox-meta .who { font-weight: 600; color: var(--ink-2); }
.inbox-meta .sep { color: var(--rule-2); }
.inbox-action { display: flex; gap: 6px; flex-shrink: 0; }

.vg-btn {
    font-family: var(--sans);
    font-size: 12px; font-weight: 600; letter-spacing: .02em;
    padding: 9px 16px; border-radius: 8px; border: 1px solid transparent;
    cursor: pointer; transition: all .2s var(--ease); white-space: nowrap;
}
.vg-btn--primary { background: var(--navy-700); color: var(--paper); border-color: var(--navy-700); }
.vg-btn--primary:hover { background: var(--navy-600); }
.vg-btn--ghost { background: transparent; color: var(--ink-2); border-color: var(--rule); }
.vg-btn--ghost:hover { border-color: var(--gold-500); color: var(--gold-700); }

.inbox-empty {
    padding: 56px 0; text-align: center;
    color: var(--ink-3); font-size: 14px;
}
.inbox-empty strong { color: var(--ok); font-weight: 600; font-size: 16px; display: block; margin-bottom: 6px; }

/* ── Pessoas ── */
.people-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
@media (max-width: 1100px) { .people-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 720px)  { .people-grid { grid-template-columns: 1fr; } }
.person {
    background: var(--surface); border: 1px solid var(--rule);
    border-radius: var(--r-md); padding: 22px 22px 20px;
    cursor: pointer; transition: all .3s var(--ease);
    position: relative; overflow: hidden;
}
.person::before {
    content: ""; position: absolute; top: 0; left: 0; right: 0; height: 2px;
    background: var(--gold-500); transform: scaleX(0); transform-origin: left;
    transition: transform .5s var(--ease);
}
.person:hover::before { transform: scaleX(1); }
.person:hover { border-color: var(--gold-300); transform: translateY(-2px); box-shadow: var(--shadow-md); }
.person--alert::before { background: var(--bad); transform: scaleX(1); }
.person-head { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
.person-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, var(--navy-700), var(--navy-500));
    color: var(--paper); display: grid; place-items: center;
    font-weight: 600; font-size: 13px; letter-spacing: .05em; flex-shrink: 0;
}
.person--alert .person-avatar { background: linear-gradient(135deg, var(--bad), #8a3324); }
.person-name { font-size: 15px; font-weight: 600; color: var(--ink); line-height: 1.2; }
.person-role { font-size: 10px; letter-spacing: .14em; text-transform: uppercase; color: var(--ink-3); font-weight: 600; margin-top: 3px; }
.person-stats {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 10px; padding: 14px 0;
    border-top: 1px solid var(--rule); border-bottom: 1px solid var(--rule);
    margin-bottom: 14px;
}
.person-stat-val {
    font-weight: 300; font-size: 26px;
    letter-spacing: -.02em; line-height: 1;
    color: var(--ink); font-variant-numeric: tabular-nums;
}
.person-stat-val.alert { color: var(--bad); }
.person-stat-val.warn  { color: var(--warn); }
.person-stat-val.ok    { color: var(--ok); }
.person-stat-lbl { font-size: 9px; letter-spacing: .14em; text-transform: uppercase; color: var(--ink-3); font-weight: 600; margin-top: 6px; }
.person-progress { height: 4px; background: var(--rule); border-radius: 999px; overflow: hidden; }
.person-progress-fill { height: 100%; border-radius: 999px; background: var(--gold-500); transition: width .8s var(--ease-out); }
.person-progress-fill.alert { background: var(--bad); }
.person-progress-fill.warn  { background: var(--warn); }
.person-progress-fill.ok    { background: var(--ok); }
.person-foot { margin-top: 12px; font-size: 11px; color: var(--ink-3); display: flex; justify-content: space-between; font-variant-numeric: tabular-nums; }

/* ── Auditoria ── */
.audit-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px; }
@media (max-width: 1100px) { .audit-grid { grid-template-columns: 1fr; } }
.audit-feature {
    background: linear-gradient(135deg, var(--navy-900), var(--navy-700) 75%, var(--navy-600));
    color: var(--paper); border: 1px solid var(--navy-800);
    border-radius: var(--r-md); padding: 28px 30px;
    position: relative; overflow: hidden;
}
.audit-feature::after {
    content: ""; position: absolute; right: -50px; bottom: -80px;
    width: 240px; height: 240px; border-radius: 50%;
    background: radial-gradient(circle, rgba(201,163,91,.25), transparent 60%);
    pointer-events: none;
}
.audit-feature::before {
    content: ""; position: absolute; top: 0; left: 0; right: 0; height: 2px;
    background: var(--gold-500);
}
.audit-eyebrow { font-size: 10px; letter-spacing: .2em; text-transform: uppercase; color: var(--gold-400); font-weight: 600; margin-bottom: 18px; }
.audit-value {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-style: italic; font-weight: 600;
    font-size: 88px; line-height: .9;
    letter-spacing: -.04em; color: var(--paper);
}
.audit-value .pct { font-family: var(--sans); font-style: normal; font-weight: 300; font-size: .42em; color: var(--gold-400); margin-left: 4px; }
.audit-breakdown {
    margin-top: 22px; display: flex; gap: 22px;
    font-size: 12px; color: var(--gold-300);
    border-top: 1px solid rgba(201,163,91,.18); padding-top: 16px;
}
.audit-breakdown b { color: var(--paper); font-size: 18px; font-weight: 300; display: block; margin-bottom: 2px; }
.audit-note { margin-top: 18px; font-size: 11px; color: var(--gold-300); letter-spacing: .06em; }

.export-card {
    background: var(--surface); border: 1px solid var(--rule);
    border-radius: var(--r-md); padding: 22px;
    transition: all .3s var(--ease);
    position: relative; overflow: hidden;
    display: flex; flex-direction: column;
}
.export-card::before {
    content: ""; position: absolute; top: 0; left: 0; right: 0; height: 2px;
    background: var(--gold-500); transform: scaleX(0); transform-origin: left;
    transition: transform .4s var(--ease);
}
.export-card:hover::before { transform: scaleX(1); }
.export-card:hover { border-color: var(--gold-300); transform: translateY(-2px); box-shadow: var(--shadow-md); }
.export-icon {
    width: 38px; height: 38px; border-radius: 9px;
    background: var(--gold-50); color: var(--gold-600);
    display: grid; place-items: center;
    border: 1px solid var(--gold-100); margin-bottom: 14px;
}
.export-title { font-size: 14px; font-weight: 600; color: var(--ink); margin-bottom: 6px; }
.export-desc { font-size: 12px; color: var(--ink-3); line-height: 1.5; flex: 1; margin-bottom: 14px; }
.export-actions { display: flex; gap: 6px; }

/* ── Stagger ── */
@keyframes vg-rise {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
.stagger > * { opacity: 0; animation: vg-rise .6s var(--ease-out) forwards; }
.stagger > *:nth-child(1) { animation-delay: .05s; }
.stagger > *:nth-child(2) { animation-delay: .12s; }
.stagger > *:nth-child(3) { animation-delay: .20s; }
.stagger > *:nth-child(4) { animation-delay: .28s; }
.stagger > *:nth-child(5) { animation-delay: .36s; }
.stagger > *:nth-child(6) { animation-delay: .44s; }
.stagger > *:nth-child(n+7) { animation-delay: .50s; }

/* ── Modal cumprir ── */
.vg-modal-overlay {
    position: fixed; inset: 0; background: rgba(14,31,51,0.55); backdrop-filter: blur(6px);
    z-index: 9998; display: none; align-items: center; justify-content: center;
}
.vg-modal-overlay.open { display: flex; }
.vg-modal-box {
    background: var(--surface); border-radius: var(--r-lg);
    width: 520px; max-width: 92vw; padding: 28px;
    border: 1px solid var(--rule); box-shadow: var(--shadow-lg);
}
.vg-modal-eyebrow { font-size: 10px; letter-spacing: .2em; text-transform: uppercase; color: var(--gold-600); font-weight: 600; margin-bottom: 8px; }
.vg-modal-title { font-weight: 300; font-size: 24px; letter-spacing: -.02em; color: var(--ink); margin-bottom: 14px; }
.vg-modal-title em { font-style: normal; font-weight: 600; color: var(--navy-700); }
.vg-modal-textarea {
    width: 100%; padding: 14px; font-family: var(--sans); font-size: 13px;
    border: 1px solid var(--rule); border-radius: var(--r-sm);
    resize: vertical; min-height: 110px; color: var(--ink);
    background: var(--paper);
}
.vg-modal-textarea:focus { outline: none; border-color: var(--gold-500); }
.vg-modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 18px; }

/* ── Inbox item: clicável + dica ── */
.inbox-item { cursor: pointer; }
.inbox-action button { cursor: pointer; }

/* ── Drawer de detalhe (inteiro teor) ── */
.vg-drawer-overlay {
    position: fixed; inset: 0; background: rgba(14,31,51,0.55); backdrop-filter: blur(4px);
    z-index: 9997; display: none; opacity: 0; transition: opacity .2s var(--ease-out);
}
.vg-drawer-overlay.open { display: block; opacity: 1; }
.vg-drawer {
    position: fixed; top: 0; right: 0; bottom: 0;
    width: 640px; max-width: 96vw;
    background: var(--surface); z-index: 9998;
    transform: translateX(100%); transition: transform .26s var(--ease-out);
    overflow-y: auto; box-shadow: -8px 0 28px rgba(14,31,51,.12);
    border-left: 1px solid var(--rule);
}
.vg-drawer.open { transform: translateX(0); }
.vg-drawer-head {
    padding: 24px 32px 18px;
    border-bottom: 1px solid var(--rule);
    background: var(--paper);
    position: sticky; top: 0; z-index: 1;
}
.vg-drawer-eyebrow { font-size: 10px; letter-spacing: .22em; text-transform: uppercase; color: var(--gold-600); font-weight: 600; margin-bottom: 8px; }
.vg-drawer-title { font-weight: 300; font-size: 22px; letter-spacing: -.015em; color: var(--ink); margin-bottom: 10px; line-height: 1.25; }
.vg-drawer-title em { font-style: normal; font-weight: 600; color: var(--navy-700); }
.vg-drawer-meta { display: flex; flex-wrap: wrap; gap: 14px; font-size: 11px; color: var(--ink-3); }
.vg-drawer-meta span strong { color: var(--ink); font-weight: 500; }
.vg-drawer-close {
    position: absolute; top: 18px; right: 18px;
    width: 32px; height: 32px; border-radius: 8px;
    border: 1px solid var(--rule); background: var(--surface);
    display: grid; place-items: center; cursor: pointer;
    color: var(--ink-3); font-size: 18px; line-height: 1;
}
.vg-drawer-close:hover { color: var(--ink); border-color: var(--gold-300); }

.vg-drawer-body { padding: 24px 32px 32px; }
.vg-drawer-section { margin-bottom: 26px; }
.vg-drawer-section:last-child { margin-bottom: 0; }
.vg-drawer-label {
    font-size: 10px; letter-spacing: .18em; text-transform: uppercase;
    color: var(--ink-3); font-weight: 600; margin-bottom: 10px;
    padding-bottom: 6px; border-bottom: 1px solid var(--rule-2);
}
.vg-drawer-text {
    font-size: 13.5px; line-height: 1.65; color: var(--ink);
    white-space: pre-line; word-break: break-word;
}
.vg-drawer-text--muted { color: var(--ink-3); font-style: italic; }
.vg-drawer-quote {
    background: var(--paper); border-left: 3px solid var(--gold-400);
    padding: 14px 16px; border-radius: 0 6px 6px 0;
    font-size: 13px; color: var(--ink); line-height: 1.6;
    white-space: pre-line;
}
.vg-drawer-note {
    background: rgba(201,163,91,0.06); border: 1px solid var(--gold-100);
    padding: 12px 14px; border-radius: 6px;
    font-size: 12px; color: var(--ink-2); line-height: 1.55;
}
.vg-drawer-cta-row {
    display: flex; gap: 8px; flex-wrap: wrap;
    padding-top: 6px;
}
.vg-drawer-cta-row a, .vg-drawer-cta-row button {
    text-decoration: none;
}
.vg-copy-btn {
    font-size: 11px; padding: 6px 10px;
    border: 1px solid var(--rule); border-radius: 6px;
    background: var(--surface); color: var(--ink-2); cursor: pointer;
}
.vg-copy-btn:hover { border-color: var(--gold-300); color: var(--ink); }
</style>
@endpush

@section('content')
<div class="editorial-page" id="vigilia-root">

    {{-- ─────────── HERO ─────────── --}}
    <section class="hero" style="margin-bottom: 56px;">
        <div>
            <div class="hero-eyebrow">Vigília · {{ ucfirst(now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM')) }}</div>
            <h1 class="vg-hero-greeting">
                <em>Hoje,</em> <span class="num" id="hero-total">—</span> <span id="hero-noun">processos</span><br>
                <span class="name">exigem</span> sua atenção.
            </h1>
            <div class="hero-sub" id="hero-sub">Carregando panorama…</div>
            <div class="hero-fineprint" style="font-size:11px;color:var(--ink-3);margin-top:6px;line-height:1.5;">
                Prazos VIGÍLIA = data do andamento <strong>+ 72h corridas</strong>. Métrica interna do escritório, não o prazo processual do tribunal.
            </div>

            <div class="hero-stats stagger">
                <div class="hero-stat" onclick="setFilter('vencidas')">
                    <div class="hero-stat-label">Vencidas</div>
                    <div class="hero-stat-value val-bad" id="kpi-vencidas">—</div>
                    <div class="hero-stat-delta down">requer ação imediata</div>
                </div>
                <div class="hero-stat" onclick="setFilter('cobrancas')">
                    <div class="hero-stat-label">Sem providência 48h+</div>
                    <div class="hero-stat-value val-warn" id="kpi-cobrancas">—</div>
                    <div class="hero-stat-delta down">advogados parados</div>
                </div>
                <div class="hero-stat" onclick="setFilter('suspeitas')">
                    <div class="hero-stat-label">Conclusões suspeitas</div>
                    <div class="hero-stat-value" id="kpi-suspeitas">—</div>
                    <div class="hero-stat-delta down">sem andamento posterior</div>
                </div>
                <div class="hero-stat" onclick="document.getElementById('auditoria').scrollIntoView({behavior:'smooth'})">
                    <div class="hero-stat-label">Confiabilidade 30d</div>
                    <div class="hero-stat-value val-ok" id="kpi-conf">—</div>
                    <div class="hero-stat-delta" id="kpi-conf-delta">cruzamento automático</div>
                </div>
            </div>
        </div>
        <div class="hero-time">
            <div class="hero-clock" id="hero-clock">--<span class="colon">:</span>--</div>
            Última sincronia DataJuri
        </div>
    </section>

    {{-- ─────────── INBOX CRÍTICO ─────────── --}}
    <section style="margin-bottom: 56px;">
        <div class="section-head">
            <div>
                <div class="hero-eyebrow" style="margin-bottom: 6px;">Comando</div>
                <h2>Inbox <em>crítico</em>.</h2>
            </div>
            <div class="section-line"></div>
            <a class="section-action" href="#" onclick="setFilter('cumpridas'); event.preventDefault();">
                Cumpridas hoje
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6l6 6-6 6"/></svg>
            </a>
        </div>

        <div class="chip-row" id="chip-row">
            <button class="chip chip--active" data-filter="tudo">Todos <span class="ct" id="ct-tudo">0</span></button>
            <button class="chip chip--bad" data-filter="vencidas">Vencidas <span class="ct" id="ct-vencidas">0</span></button>
            <button class="chip chip--warn" data-filter="cobrancas">Cobranças 48h+ <span class="ct" id="ct-cobrancas">0</span></button>
            <button class="chip" data-filter="obrigacoes">Obrigações 72h <span class="ct" id="ct-obrigacoes">0</span></button>
            <button class="chip" data-filter="suspeitas">Suspeitas <span class="ct" id="ct-suspeitas">0</span></button>
            <button class="chip" data-filter="cumpridas">Cumpridas hoje <span class="ct" id="ct-cumpridas">0</span></button>
        </div>

        <div class="inbox stagger" id="inbox-list">
            <div class="inbox-empty">Carregando…</div>
        </div>
    </section>

    {{-- ─────────── PESSOAS ─────────── --}}
    <section style="margin-bottom: 56px;">
        <div class="section-head">
            <div>
                <div class="hero-eyebrow" style="margin-bottom: 6px;">Time</div>
                <h2>Performance por <em>advogado</em>.</h2>
            </div>
            <div class="section-line"></div>
            <a class="section-action" href="{{ url('/vigilia/relatorio/consolidado') }}">
                Relatório consolidado
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6l6 6-6 6"/></svg>
            </a>
        </div>

        <div class="people-grid stagger" id="people-grid">
            <div class="inbox-empty" style="grid-column: 1/-1;">Carregando pessoas…</div>
        </div>
    </section>

    {{-- ─────────── AUDITORIA ─────────── --}}
    <section style="margin-bottom: 32px;" id="auditoria">
        <div class="section-head">
            <div>
                <div class="hero-eyebrow" style="margin-bottom: 6px;">Auditoria</div>
                <h2>Confiabilidade & <em>exports</em>.</h2>
            </div>
            <div class="section-line"></div>
        </div>

        <div class="audit-grid stagger">
            <div class="audit-feature">
                <div class="audit-eyebrow">Índice de Confiabilidade · 30d</div>
                <div class="audit-value"><span id="conf-pct">—</span><span class="pct">%</span></div>
                <div class="audit-breakdown">
                    <div><b id="conf-verificadas">—</b>verificadas</div>
                    <div><b id="conf-suspeitas">—</b>suspeitas</div>
                    <div><b id="conf-semacao">—</b>sem ação</div>
                </div>
                <div class="audit-note" id="conf-note">Cruzamento automático: conclusões VIGÍLIA × andamentos DataJuri.</div>
            </div>

            <div class="export-card" onclick="window.location='/vigilia/export/excel'">
                <div class="export-icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 014-4h6m0 0l-3-3m3 3l-3 3M6 21h2a4 4 0 004-4v-2m-6 6V7a4 4 0 014-4h6"/></svg>
                </div>
                <div class="export-title">Consolidado mensal</div>
                <div class="export-desc">Ranking, distribuição por tipo e resumo executivo.</div>
                <div class="export-actions">
                    <a class="vg-btn vg-btn--ghost" href="{{ url('/vigilia/export/excel') }}">Excel</a>
                    <a class="vg-btn vg-btn--ghost" href="{{ url('/vigilia/export/pdf?tipo=consolidado') }}">PDF</a>
                </div>
            </div>

            <div class="export-card">
                <div class="export-icon">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="export-title">Prazos críticos</div>
                <div class="export-desc">Vencidos e vencendo nos próximos 7 dias. Gerado diariamente às 07:00.</div>
                <div class="export-actions">
                    <a class="vg-btn vg-btn--ghost" href="{{ url('/vigilia/relatorio/prazos') }}">Tela</a>
                    <a class="vg-btn vg-btn--ghost" href="{{ url('/vigilia/export/pdf?tipo=prazos') }}">PDF</a>
                </div>
            </div>
        </div>
    </section>

</div>

{{-- ─────────── Drawer de detalhe (inteiro teor) ─────────── --}}
<div class="vg-drawer-overlay" id="drawer-overlay" onclick="closeDrawer()"></div>
<aside class="vg-drawer" id="drawer-detail" aria-hidden="true">
    <button class="vg-drawer-close" onclick="closeDrawer()" aria-label="Fechar">×</button>
    <div class="vg-drawer-head">
        <div class="vg-drawer-eyebrow" id="drw-eyebrow">Detalhe</div>
        <h2 class="vg-drawer-title" id="drw-title">Carregando…</h2>
        <div class="vg-drawer-meta" id="drw-meta"></div>
    </div>
    <div class="vg-drawer-body" id="drw-body">
        <div class="vg-drawer-text vg-drawer-text--muted">Carregando…</div>
    </div>
</aside>

{{-- ─────────── Modal cumprir ─────────── --}}
<div class="vg-modal-overlay" id="modal-cumprir">
    <div class="vg-modal-box">
        <div class="vg-modal-eyebrow">Obrigação VIGÍLIA</div>
        <div class="vg-modal-title">Registrar <em>parecer</em>.</div>
        <div style="font-size:12px;color:var(--ink-3);margin-bottom:12px;">
            Descreva a providência tomada (petição protocolada, recurso apresentado, justificativa etc.).
        </div>
        <input type="hidden" id="modal-obrig-id">
        <textarea id="modal-parecer" class="vg-modal-textarea" placeholder="Ex.: Recurso de apelação protocolado em 24/04/2026 às 15h42 — protocolo #..."></textarea>
        <div class="vg-modal-actions">
            <button class="vg-btn vg-btn--ghost" onclick="closeModal()">Cancelar</button>
            <button class="vg-btn vg-btn--primary" id="modal-confirmar">Confirmar cumprimento</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const VIGILIA = {
    filter: 'tudo',
    counters: {},
    items: [],
};

const CSRF = document.querySelector('meta[name="csrf-token"]').content;

function el(id) { return document.getElementById(id); }

function initials(name) {
    if (!name) return '—';
    return name.split(/\s+/).filter(Boolean).slice(0,2).map(s => s[0]).join('').toUpperCase();
}

function fmtRelative(dt) {
    if (!dt) return '—';
    const d = new Date(dt.replace(' ', 'T'));
    const min = Math.round((Date.now() - d.getTime()) / 60000);
    if (min < 1) return 'agora';
    if (min < 60) return min + 'min';
    if (min < 1440) return Math.round(min/60) + 'h';
    return Math.round(min/1440) + 'd';
}

function severityClass(sev) {
    return 'inbox-item--' + (sev || 'medium');
}
function statusClass(sev) {
    return ({critical:'crit', high:'high', medium:'med', suspect:'susp', low:'low', ok:'ok'})[sev] || 'med';
}

function setFilter(f) {
    VIGILIA.filter = f;
    document.querySelectorAll('.chip').forEach(c => c.classList.toggle('chip--active', c.dataset.filter === f));
    loadInbox();
    // Rola suave pra inbox
    document.querySelector('.chip-row')?.scrollIntoView({behavior:'smooth', block:'start'});
}

document.querySelectorAll('.chip').forEach(c => c.addEventListener('click', () => setFilter(c.dataset.filter)));

async function loadInbox(preserveScroll = false) {
    const savedY = preserveScroll ? window.scrollY : null;
    try {
        const r = await fetch('/vigilia/api/inbox?filter=' + VIGILIA.filter + '&limit=40');
        const d = await r.json();
        VIGILIA.counters = d.counters || {};
        VIGILIA.items = d.items || [];

        // Counters nos chips
        Object.entries(d.counters || {}).forEach(([k, v]) => {
            const ct = el('ct-' + k);
            if (ct) ct.textContent = v;
        });

        // KPIs do hero
        el('kpi-vencidas').textContent   = d.counters.vencidas ?? 0;
        el('kpi-cobrancas').textContent  = d.counters.sem_providencia_48h ?? d.counters.cobrancas ?? 0;
        el('kpi-suspeitas').textContent  = d.counters.suspeitas ?? 0;

        // Hero principal
        const total = d.counters.tudo ?? 0;
        el('hero-total').textContent = total;
        el('hero-noun').textContent = total === 1 ? 'processo' : 'processos';
        el('hero-sub').textContent = total === 0
            ? 'Nada pendente. Vigília está em dia ✓'
            : `${d.counters.vencidas ?? 0} vencidas · ${d.counters.cobrancas ?? 0} cobranças 48h+ · ${d.counters.suspeitas ?? 0} suspeitas`;

        renderInbox();
        if (savedY !== null) {
            requestAnimationFrame(() => window.scrollTo(0, savedY));
        }
    } catch (e) {
        el('inbox-list').innerHTML = '<div class="inbox-empty">Erro ao carregar inbox.</div>';
    }
}

function renderInbox() {
    const list = el('inbox-list');
    if (VIGILIA.items.length === 0) {
        const emptyMsg = VIGILIA.filter === 'tudo'
            ? '<strong>Tudo em ordem ✓</strong>Nenhuma pendência crítica no momento.'
            : 'Nenhum item nesse filtro.';
        list.innerHTML = `<div class="inbox-empty">${emptyMsg}</div>`;
        return;
    }

    list.innerHTML = VIGILIA.items.map(it => {
        const sev = it.severity || 'medium';
        const stCls = statusClass(sev);
        const meta = [
            `<span class="who">${escape(it.advogado || '—')}</span>`,
            it.cliente ? `<span class="sep">·</span><span>${escape(it.cliente)}</span>` : '',
            it.area   ? `<span class="sep">·</span><span>${escape(it.area)}</span>` : '',
        ].filter(Boolean).join('');

        const actions = (it.actions || []).map((a, idx) => {
            const cls = idx === 0 ? 'vg-btn vg-btn--primary' : 'vg-btn vg-btn--ghost';
            return `<button class="${cls}" data-action="${a.kind}" data-id="${it.id}" data-obrigacao-id="${it.obrigacao_id ?? ''}">${escape(a.label)}</button>`;
        }).join('');

        return `<div class="inbox-item ${severityClass(sev)}" data-item-id="${it.id}" role="button" tabindex="0">
            <div class="inbox-tag">
                <span class="inbox-tag-status ${stCls}">${escape(it.status_label || '—')}</span>
                <span class="inbox-tag-time">${escape(it.time_label || '')}</span>
            </div>
            <div class="inbox-body">
                <div class="inbox-process">${escape(it.processo || '—')} · ${escape(it.tribunal || '')}</div>
                <div class="inbox-event">${escape(it.event_text || '')}</div>
                <div class="inbox-meta">${meta}</div>
            </div>
            <div class="inbox-action">${actions}</div>
        </div>`;
    }).join('');

    // Bind: click no card abre drawer (exceto se for botão de ação)
    list.querySelectorAll('.inbox-item').forEach(card => {
        card.addEventListener('click', (e) => {
            if (e.target.closest('[data-action]')) return;
            openDrawer(card.dataset.itemId);
        });
        card.addEventListener('keydown', (e) => {
            if ((e.key === 'Enter' || e.key === ' ') && !e.target.closest('[data-action]')) {
                e.preventDefault();
                openDrawer(card.dataset.itemId);
            }
        });
    });

    // Bind action buttons
    list.querySelectorAll('[data-action]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const kind = btn.dataset.action;
            const obrId = btn.dataset.obrigacaoId;
            if (kind === 'parecer' && obrId) {
                openModal(obrId);
            } else if (kind === 'justificar' && obrId) {
                const motivo = prompt('Justifique por que esta obrigação não pode ser cumprida no prazo:');
                if (motivo && motivo.trim()) cumprirObrigacao(obrId, '[JUSTIFICATIVA] ' + motivo);
            } else if (kind === 'auditar' || kind === 'aceitar') {
                alert('Ação "' + btn.textContent + '" ainda será implementada no backend.');
            } else if (kind === 'providencia') {
                alert('Abra o DataJuri e registre parecer na atividade. A sincronia vai detectar automaticamente.');
            } else if (kind === 'conversa') {
                window.location = '/nexo';
            }
        });
    });
}

function escape(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// ── Modal cumprir ──
function openModal(obrigacaoId) {
    el('modal-obrig-id').value = obrigacaoId;
    el('modal-parecer').value = '';
    el('modal-cumprir').classList.add('open');
    setTimeout(() => el('modal-parecer').focus(), 60);
}
function closeModal() {
    el('modal-cumprir').classList.remove('open');
}
el('modal-confirmar').addEventListener('click', () => {
    const id = el('modal-obrig-id').value;
    const parecer = el('modal-parecer').value.trim();
    if (!parecer) { alert('Descreva a providência.'); return; }
    cumprirObrigacao(id, parecer);
});

async function cumprirObrigacao(id, parecer) {
    try {
        const r = await fetch(`/vigilia/api/obrigacoes/${id}/cumprir`, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN': CSRF},
            body: JSON.stringify({parecer})
        });
        const d = await r.json();
        if (d.success) {
            closeModal();
            closeDrawer();
            const cascata = d.cascata || 0;
            if (cascata > 0) {
                showToast(`Registrada · ${cascata} obrigação${cascata>1?'ões irmãs':' irmã'} do mesmo processo/dia também ${cascata>1?'foram':'foi'} marcada${cascata>1?'s':''}.`);
            } else {
                showToast('Registrada.');
            }
            loadInbox(true);
        } else {
            alert('Falha ao registrar cumprimento.');
        }
    } catch (e) {
        alert('Erro de rede.');
    }
}

function showToast(msg) {
    let t = document.getElementById('vg-toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'vg-toast';
        t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--navy-700);color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;z-index:10000;box-shadow:var(--shadow-lg);max-width:90vw;text-align:center;opacity:0;transition:opacity .2s';
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.opacity = '1';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => { t.style.opacity = '0'; }, 3500);
}

// ── Drawer de detalhe (inteiro teor + links) ──
async function openDrawer(itemId) {
    if (!itemId) return;
    el('drawer-overlay').classList.add('open');
    el('drawer-detail').classList.add('open');
    el('drawer-detail').setAttribute('aria-hidden', 'false');
    // Foca o drawer pra leitor de tela e pra rolagem por teclado
    setTimeout(() => el('drawer-detail').focus?.(), 0);
    el('drw-title').textContent = 'Carregando…';
    el('drw-eyebrow').textContent = 'Detalhe';
    el('drw-meta').innerHTML = '';
    el('drw-body').innerHTML = '<div class="vg-drawer-text vg-drawer-text--muted">Carregando…</div>';

    try {
        const r = await fetch('/vigilia/api/inbox/' + encodeURIComponent(itemId));
        if (!r.ok) throw new Error('http ' + r.status);
        const d = await r.json();
        renderDrawer(d);
    } catch (e) {
        el('drw-body').innerHTML = '<div class="vg-drawer-text vg-drawer-text--muted">Não foi possível carregar o detalhe.</div>';
    }
}

function closeDrawer() {
    el('drawer-overlay').classList.remove('open');
    el('drawer-detail').classList.remove('open');
    el('drawer-detail').setAttribute('aria-hidden', 'true');
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeDrawer();
});

function fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d.getTime())) return s;
    return d.toLocaleDateString('pt-BR') + (s.length > 10 ? ' ' + d.toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'}) : '');
}

function renderDrawer(d) {
    el('drw-eyebrow').textContent = ({
        obrigacao: 'Obrigação · 72h parecer',
        cobranca:  'Cobrança · 48h providência',
        suspeita:  'Conclusão suspeita',
    }[d.kind] || 'Detalhe');

    const titleParts = (d.title || '').split('·');
    el('drw-title').innerHTML = titleParts.length > 1
        ? `<em>${escape(titleParts[0].trim())}</em> · ${escape(titleParts.slice(1).join('·').trim())}`
        : `<em>${escape(d.title || 'Detalhe')}</em>`;

    const meta = [
        d.processo ? `<span><strong>${escape(d.processo)}</strong> · ${escape(d.tribunal || '')}</span>` : '',
        d.cliente ? `<span>Cliente: <strong>${escape(d.cliente)}</strong></span>` : '',
        d.advogado ? `<span>Advogado(a): <strong>${escape(d.advogado)}</strong></span>` : '',
        d.data_evento ? `<span>Evento: <strong>${escape(fmtDate(d.data_evento))}</strong></span>` : '',
        d.data_limite ? `<span title="Calculado: data do andamento + 72h corridas. É prazo INTERNO da VIGÍLIA, não prazo processual.">Prazo VIGÍLIA: <strong>${escape(fmtDate(d.data_limite))}</strong> <em style="color:var(--ink-3);font-style:normal;">(+72h corridas do andamento)</em></span>` : '',
    ].filter(Boolean).join('');
    el('drw-meta').innerHTML = meta;

    const sections = [];

    // Andamento (descricao + observacao)
    if (d.andamento) {
        const desc = (d.andamento.descricao || '').trim();
        const obs  = (d.andamento.observacao || '').trim();
        sections.push(`<div class="vg-drawer-section">
            <div class="vg-drawer-label">Andamento registrado no DataJuri</div>
            ${desc ? `<div class="vg-drawer-quote">${escape(desc)}</div>` : '<div class="vg-drawer-text vg-drawer-text--muted">Sem descrição registrada.</div>'}
            ${obs ? `<div class="vg-drawer-text" style="margin-top:12px"><strong style="color:var(--ink-2);font-size:11px;text-transform:uppercase;letter-spacing:.1em;">Observação:</strong><br>${escape(obs)}</div>` : ''}
        </div>`);
    }

    // Parecer existente
    const parecer = (d.parecer || (d.andamento && d.andamento.parecer) || '').trim();
    if (parecer) {
        sections.push(`<div class="vg-drawer-section">
            <div class="vg-drawer-label">Parecer registrado</div>
            <div class="vg-drawer-quote">${escape(parecer)}</div>
        </div>`);
    }

    // Suspeita meta
    if (d.suspeita_meta) {
        const sm = d.suspeita_meta;
        sections.push(`<div class="vg-drawer-section">
            <div class="vg-drawer-label">Análise do cruzamento</div>
            <div class="vg-drawer-text">
                Gap: <strong>${sm.dias_gap}d</strong> sem andamento posterior à conclusão.
                ${sm.observacao ? `<br><br>${escape(sm.observacao)}` : ''}
                ${sm.ai_verdict ? `<br><br><strong>IA:</strong> ${escape(sm.ai_verdict)}` : ''}
            </div>
        </div>`);
    }

    // Nota explicativa
    if (d.note) {
        sections.push(`<div class="vg-drawer-section">
            <div class="vg-drawer-note">${escape(d.note)}</div>
        </div>`);
    }

    // CTAs
    const links = d.links || {};
    const ctas = [];
    if (links.datajuri) {
        ctas.push(`<a class="vg-btn vg-btn--primary" href="${links.datajuri}" target="_blank" rel="noopener">Abrir no DataJuri ↗</a>`);
    }
    if (links.tribunal_url) {
        ctas.push(`<a class="vg-btn vg-btn--ghost" href="${links.tribunal_url}" target="_blank" rel="noopener">Consultar no ${escape(links.tribunal_label || 'tribunal')} ↗</a>`);
    }
    if (links.pasta) {
        ctas.push(`<button class="vg-copy-btn" onclick="copyToClipboard('${escape(links.pasta)}', this)">Copiar nº CNJ</button>`);
    }
    if (ctas.length) {
        sections.push(`<div class="vg-drawer-section">
            <div class="vg-drawer-label">Ações</div>
            <div class="vg-drawer-cta-row">${ctas.join('')}</div>
        </div>`);
    }

    el('drw-body').innerHTML = sections.join('') || '<div class="vg-drawer-text vg-drawer-text--muted">Sem dados adicionais.</div>';
}

function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.textContent;
        btn.textContent = 'Copiado ✓';
        setTimeout(() => { btn.textContent = orig; }, 1500);
    });
}

// ── Pessoas ──
async function loadPessoas() {
    try {
        const r = await fetch('/vigilia/api/resumo?periodo=mes-atual');
        const d = await r.json();
        const grid = el('people-grid');
        const ranking = (d.ranking || []).sort((a,b) => b.total - a.total).slice(0, 9);
        if (ranking.length === 0) {
            grid.innerHTML = '<div class="inbox-empty" style="grid-column: 1/-1;">Sem dados no período.</div>';
            return;
        }
        grid.innerHTML = ranking.map(r => {
            const taxa = r.taxa || 0;
            const alerta = (r.alertas || 0) > 0;
            const cor = taxa >= 90 ? 'ok' : (taxa >= 75 ? 'warn' : 'alert');
            const ultimo = r.ultimo_cumprimento ? fmtRelative(r.ultimo_cumprimento) : 'nunca';
            return `<div class="person ${alerta ? 'person--alert' : ''}">
                <div class="person-head">
                    <div class="person-avatar">${escape(initials(r.responsavel_nome))}</div>
                    <div>
                        <div class="person-name">${escape(r.responsavel_nome)}</div>
                        <div class="person-role">${r.total} atividades · mês atual</div>
                    </div>
                </div>
                <div class="person-stats">
                    <div>
                        <div class="person-stat-val ${cor}">${taxa}%</div>
                        <div class="person-stat-lbl">Taxa</div>
                    </div>
                    <div>
                        <div class="person-stat-val ${alerta ? 'alert' : ''}">${r.alertas || 0}</div>
                        <div class="person-stat-lbl">Alertas</div>
                    </div>
                    <div>
                        <div class="person-stat-val">${escape(ultimo)}</div>
                        <div class="person-stat-lbl">Último</div>
                    </div>
                </div>
                <div class="person-progress"><div class="person-progress-fill ${cor}" style="width:${taxa}%"></div></div>
                <div class="person-foot">
                    <span>${r.concluidos} concluídos · ${r.nao_iniciados} em curso</span>
                    <span>${r.cancelados || 0} cancelados</span>
                </div>
            </div>`;
        }).join('');
    } catch (e) {
        el('people-grid').innerHTML = '<div class="inbox-empty" style="grid-column: 1/-1;">Erro ao carregar.</div>';
    }
}

// ── Auditoria ──
async function loadConfiabilidade() {
    try {
        const r = await fetch('/vigilia/api/confiabilidade?dias=30');
        const d = await r.json();
        el('conf-pct').textContent = d.pct ?? 0;
        el('conf-verificadas').textContent = d.verificadas ?? 0;
        el('conf-suspeitas').textContent = d.suspeitas ?? 0;
        el('conf-semacao').textContent = d.sem_acao ?? 0;
        el('kpi-conf').textContent = (d.pct ?? 0) + '%';
        if (d.ultima_exec) {
            el('conf-note').textContent = `Cruzamento automático · última execução ${fmtRelative(d.ultima_exec)} atrás`;
            el('kpi-conf-delta').textContent = 'última sincronia ' + fmtRelative(d.ultima_exec) + ' atrás';
        }
    } catch (e) {
        // silent
    }
}

// ── Relógio ──
function tickClock() {
    const now = new Date();
    const hh = String(now.getHours()).padStart(2,'0');
    const mm = String(now.getMinutes()).padStart(2,'0');
    el('hero-clock').innerHTML = `${hh}<span class="colon">:</span>${mm}`;
}

// ── Init + deep link ──
(function init() {
    tickClock();
    setInterval(tickClock, 30000);
    const qs = new URLSearchParams(window.location.search);
    const tab = qs.get('tab');
    const filter = qs.get('filter');
    // Compat com links antigos (?tab=obrigacoes) → filtro obrigacoes
    if (filter) {
        VIGILIA.filter = filter;
        document.querySelectorAll('.chip').forEach(c => c.classList.toggle('chip--active', c.dataset.filter === filter));
    } else if (tab === 'obrigacoes') {
        VIGILIA.filter = 'obrigacoes';
        document.querySelectorAll('.chip').forEach(c => c.classList.toggle('chip--active', c.dataset.filter === 'obrigacoes'));
    }
    loadInbox();
    loadPessoas();
    loadConfiabilidade();
})();
</script>
@endpush
