@extends('layouts.app')
@section('title', 'Design Preview')

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">

<style>
/* ============================================================
   MAYER PREMIUM — Design System v3.0 Preview
   Estética: Escritório de advocacia premium contemporâneo
   Fonte display: DM Serif Display (serifada, elegante)
   Fonte corpo: DM Sans (geométrica limpa)
   ============================================================ */

:root {
    --mp-navy:       #0F1E2E;
    --mp-navy-mid:   #1B334A;
    --mp-slate:      #2A3F55;
    --mp-blue:       #3D6B8E;
    --mp-blue-light: #5A8FB4;
    --mp-gold:       #C9A55C;
    --mp-gold-soft:  #D4B978;
    --mp-gold-dim:   rgba(201,165,92,0.12);
    --mp-bg:         #F5F3EF;
    --mp-surface:    #FFFFFF;
    --mp-surface-2:  #FAF9F7;
    --mp-surface-3:  #F0EDE8;
    --mp-text:       #1A1A1A;
    --mp-text-2:     #4A4A4A;
    --mp-text-3:     #8A8A8A;
    --mp-border:     #E8E4DD;
    --mp-border-2:   #D5CFC5;
    --mp-success:    #2D8B6F;
    --mp-warning:    #C4841D;
    --mp-danger:     #C0392B;
    --mp-radius:     14px;
    --mp-radius-sm:  8px;
    --mp-radius-lg:  20px;
    --mp-shadow:     0 1px 3px rgba(15,30,46,0.04), 0 4px 14px rgba(15,30,46,0.03);
    --mp-shadow-md:  0 2px 8px rgba(15,30,46,0.06), 0 8px 24px rgba(15,30,46,0.04);
    --mp-shadow-lg:  0 4px 12px rgba(15,30,46,0.08), 0 16px 40px rgba(15,30,46,0.06);
    --mp-font-display: 'DM Serif Display', Georgia, serif;
    --mp-font-body:    'DM Sans', system-ui, sans-serif;
}

/* ── Scope: tudo dentro do preview ── */
.mp-preview {
    font-family: var(--mp-font-body);
    color: var(--mp-text);
    background: var(--mp-bg);
    min-height: 100vh;
    padding: 0;
}

.mp-preview * {
    font-family: var(--mp-font-body) !important;
}

/* ── Page container ── */
.mp-page {
    max-width: 100%;
    padding: 0;
}

/* ── Hero Header ── */
.mp-hero {
    background: linear-gradient(160deg, var(--mp-navy) 0%, var(--mp-navy-mid) 45%, var(--mp-slate) 100%);
    border-radius: var(--mp-radius-lg);
    padding: 2.5rem 2.5rem 2rem;
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
}
.mp-hero::before {
    content: '';
    position: absolute;
    top: -60px; right: -40px;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(201,165,92,0.08) 0%, transparent 70%);
    pointer-events: none;
}
.mp-hero::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--mp-gold) 20%, var(--mp-gold-soft) 50%, var(--mp-gold) 80%, transparent);
    opacity: 0.6;
}
.mp-hero-title {
    font-family: var(--mp-font-display) !important;
    font-size: 2rem;
    color: #fff;
    letter-spacing: -0.01em;
    line-height: 1.2;
    margin-bottom: 0.35rem;
}
.mp-hero-sub {
    color: rgba(255,255,255,0.5);
    font-size: 0.85rem;
    font-weight: 400;
    letter-spacing: 0.03em;
}
.mp-hero-meta {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-top: 1.5rem;
}
.mp-hero-stat {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.mp-hero-stat-value {
    font-size: 1.6rem;
    font-weight: 700;
    color: #fff;
    line-height: 1;
}
.mp-hero-stat-label {
    font-size: 0.65rem;
    font-weight: 500;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
.mp-hero-stat-divider {
    width: 1px;
    height: 36px;
    background: rgba(255,255,255,0.1);
}

/* ── Section Titles ── */
.mp-section-title {
    font-family: var(--mp-font-display) !important;
    font-size: 1.25rem;
    color: var(--mp-navy-mid);
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.mp-section-title::before {
    content: '';
    width: 3px;
    height: 20px;
    background: var(--mp-gold);
    border-radius: 2px;
    flex-shrink: 0;
}
.mp-section-label {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--mp-text-3);
    margin-bottom: 0.75rem;
}

/* ── Cards ── */
.mp-card {
    background: var(--mp-surface);
    border: 1px solid var(--mp-border);
    border-radius: var(--mp-radius);
    box-shadow: var(--mp-shadow);
    transition: box-shadow 0.25s ease, transform 0.25s ease;
    overflow: hidden;
}
.mp-card:hover {
    box-shadow: var(--mp-shadow-md);
    transform: translateY(-2px);
}
.mp-card-body {
    padding: 1.5rem;
}
.mp-card-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--mp-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.mp-card-header h3 {
    font-family: var(--mp-font-display) !important;
    font-size: 1rem;
    color: var(--mp-navy-mid);
    margin: 0;
}
.mp-card-footer {
    padding: 0.75rem 1.5rem;
    background: var(--mp-surface-2);
    border-top: 1px solid var(--mp-border);
}

/* ── KPI Cards ── */
.mp-kpi {
    background: var(--mp-surface);
    border: 1px solid var(--mp-border);
    border-radius: var(--mp-radius);
    padding: 1.5rem;
    box-shadow: var(--mp-shadow);
    position: relative;
    transition: all 0.3s ease;
    overflow: hidden;
}
.mp-kpi:hover {
    box-shadow: var(--mp-shadow-md);
    transform: translateY(-2px);
    border-color: var(--mp-border-2);
}
.mp-kpi-accent {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 2px;
    background: var(--mp-gold);
}
.mp-kpi-icon {
    width: 36px; height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    font-size: 1rem;
}
.mp-kpi-icon--blue { background: rgba(61,107,142,0.1); color: var(--mp-blue); }
.mp-kpi-icon--gold { background: var(--mp-gold-dim); color: var(--mp-gold); }
.mp-kpi-icon--green { background: rgba(45,139,111,0.1); color: var(--mp-success); }
.mp-kpi-icon--red { background: rgba(192,57,43,0.1); color: var(--mp-danger); }

.mp-kpi-value {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--mp-navy);
    line-height: 1;
    margin-bottom: 0.35rem;
    letter-spacing: -0.02em;
}
.mp-kpi-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--mp-text-3);
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.mp-kpi-change {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 0.7rem;
    font-weight: 600;
    margin-top: 0.5rem;
    padding: 2px 8px;
    border-radius: 20px;
}
.mp-kpi-change--up { background: rgba(45,139,111,0.1); color: var(--mp-success); }
.mp-kpi-change--down { background: rgba(192,57,43,0.1); color: var(--mp-danger); }

/* ── Buttons ── */
.mp-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.6rem 1.25rem;
    font-family: var(--mp-font-body) !important;
    font-size: 0.8rem;
    font-weight: 600;
    border-radius: var(--mp-radius-sm);
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    line-height: 1.4;
}
.mp-btn:active { transform: scale(0.97); }
.mp-btn svg { width: 16px; height: 16px; flex-shrink: 0; }

.mp-btn--primary {
    background: var(--mp-navy-mid);
    color: #fff;
    border-color: var(--mp-navy-mid);
}
.mp-btn--primary:hover {
    background: var(--mp-navy);
    box-shadow: 0 4px 12px rgba(27,51,74,0.25);
}

.mp-btn--gold {
    background: var(--mp-gold);
    color: var(--mp-navy);
    border-color: var(--mp-gold);
}
.mp-btn--gold:hover {
    background: var(--mp-gold-soft);
    box-shadow: 0 4px 12px rgba(201,165,92,0.3);
}

.mp-btn--outline {
    background: transparent;
    color: var(--mp-navy-mid);
    border-color: var(--mp-border-2);
}
.mp-btn--outline:hover {
    background: var(--mp-surface-3);
    border-color: var(--mp-navy-mid);
}

.mp-btn--ghost {
    background: transparent;
    color: var(--mp-text-2);
    border-color: transparent;
}
.mp-btn--ghost:hover {
    background: var(--mp-surface-3);
    color: var(--mp-text);
}

.mp-btn--sm {
    padding: 0.4rem 0.85rem;
    font-size: 0.72rem;
}

/* ── Badges ── */
.mp-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    font-size: 0.65rem;
    font-weight: 600;
    border-radius: 20px;
    letter-spacing: 0.02em;
}
.mp-badge--success { background: rgba(45,139,111,0.1); color: var(--mp-success); border: 1px solid rgba(45,139,111,0.2); }
.mp-badge--warning { background: rgba(196,132,29,0.1); color: var(--mp-warning); border: 1px solid rgba(196,132,29,0.2); }
.mp-badge--danger { background: rgba(192,57,43,0.1); color: var(--mp-danger); border: 1px solid rgba(192,57,43,0.2); }
.mp-badge--info { background: rgba(61,107,142,0.1); color: var(--mp-blue); border: 1px solid rgba(61,107,142,0.2); }
.mp-badge--gold { background: var(--mp-gold-dim); color: #8A6F1F; border: 1px solid rgba(201,165,92,0.25); }
.mp-badge--neutral { background: var(--mp-surface-3); color: var(--mp-text-3); border: 1px solid var(--mp-border); }

/* ── Table ── */
.mp-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.8rem;
}
.mp-table thead th {
    background: var(--mp-surface-2);
    color: var(--mp-text-3);
    font-weight: 600;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--mp-border);
    text-align: left;
    white-space: nowrap;
}
.mp-table thead th:first-child { border-radius: var(--mp-radius-sm) 0 0 0; }
.mp-table thead th:last-child { border-radius: 0 var(--mp-radius-sm) 0 0; }
.mp-table tbody td {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--mp-border);
    color: var(--mp-text);
    vertical-align: middle;
}
.mp-table tbody tr {
    transition: background 0.15s ease;
}
.mp-table tbody tr:hover {
    background: var(--mp-surface-2);
}
.mp-table tbody tr:last-child td {
    border-bottom: none;
}
.mp-table .cell-name {
    font-weight: 600;
    color: var(--mp-navy-mid);
}
.mp-table .cell-meta {
    font-size: 0.72rem;
    color: var(--mp-text-3);
}

/* ── Tabs ── */
.mp-tabs {
    display: flex;
    gap: 0;
    border-bottom: 1px solid var(--mp-border);
    margin-bottom: 1.5rem;
}
.mp-tab {
    padding: 0.75rem 1.25rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--mp-text-3);
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: all 0.2s ease;
    background: none;
    border-top: none; border-left: none; border-right: none;
}
.mp-tab:hover { color: var(--mp-text); }
.mp-tab.active {
    color: var(--mp-navy-mid);
    border-bottom-color: var(--mp-gold);
}

/* ── Inputs ── */
.mp-input {
    width: 100%;
    padding: 0.65rem 1rem;
    font-family: var(--mp-font-body) !important;
    font-size: 0.8rem;
    border: 1px solid var(--mp-border);
    border-radius: var(--mp-radius-sm);
    background: var(--mp-surface);
    color: var(--mp-text);
    transition: border-color 0.2s, box-shadow 0.2s;
}
.mp-input:focus {
    border-color: var(--mp-blue);
    box-shadow: 0 0 0 3px rgba(61,107,142,0.1);
    outline: none;
}
.mp-input::placeholder { color: var(--mp-text-3); }
.mp-label {
    display: block;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--mp-text-2);
    margin-bottom: 0.35rem;
    letter-spacing: 0.02em;
}

/* ── Activity List ── */
.mp-activity-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 0;
    border-bottom: 1px solid var(--mp-border);
}
.mp-activity-item:last-child { border-bottom: none; }
.mp-activity-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    margin-top: 5px;
    flex-shrink: 0;
}
.mp-activity-dot--blue { background: var(--mp-blue); }
.mp-activity-dot--gold { background: var(--mp-gold); }
.mp-activity-dot--green { background: var(--mp-success); }
.mp-activity-dot--red { background: var(--mp-danger); }
.mp-activity-text {
    font-size: 0.82rem;
    color: var(--mp-text);
    line-height: 1.5;
}
.mp-activity-text strong { color: var(--mp-navy-mid); font-weight: 600; }
.mp-activity-time {
    font-size: 0.7rem;
    color: var(--mp-text-3);
    margin-top: 2px;
}

/* ── Progress Bar ── */
.mp-progress {
    height: 6px;
    background: var(--mp-surface-3);
    border-radius: 3px;
    overflow: hidden;
}
.mp-progress-bar {
    height: 100%;
    border-radius: 3px;
    transition: width 0.6s ease;
}
.mp-progress-bar--blue { background: var(--mp-blue); }
.mp-progress-bar--gold { background: var(--mp-gold); }
.mp-progress-bar--green { background: var(--mp-success); }

/* ── Stagger animations ── */
@keyframes mpFadeUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}
.mp-anim { animation: mpFadeUp 0.5s cubic-bezier(0.22, 1, 0.36, 1) both; }
.mp-anim-1 { animation-delay: 0.05s; }
.mp-anim-2 { animation-delay: 0.1s; }
.mp-anim-3 { animation-delay: 0.15s; }
.mp-anim-4 { animation-delay: 0.2s; }
.mp-anim-5 { animation-delay: 0.25s; }
.mp-anim-6 { animation-delay: 0.3s; }
.mp-anim-7 { animation-delay: 0.35s; }
.mp-anim-8 { animation-delay: 0.4s; }

/* ── Grid layout ── */
.mp-grid-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
.mp-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
}
.mp-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.25rem;
}

@media (max-width: 1024px) {
    .mp-grid-4 { grid-template-columns: repeat(2, 1fr); }
    .mp-grid-3 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .mp-grid-4, .mp-grid-3, .mp-grid-2 { grid-template-columns: 1fr; }
    .mp-hero { padding: 1.5rem; }
    .mp-hero-title { font-size: 1.5rem; }
    .mp-hero-meta { flex-wrap: wrap; gap: 1rem; }
}

/* ── Decorative ── */
.mp-gold-line {
    width: 40px;
    height: 2px;
    background: var(--mp-gold);
    border-radius: 1px;
}

/* ── Alert / Insight Box ── */
.mp-insight {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-radius: var(--mp-radius-sm);
    font-size: 0.82rem;
    line-height: 1.5;
    border-left: 3px solid;
}
.mp-insight--positive { background: rgba(45,139,111,0.06); border-color: var(--mp-success); color: #1A5C43; }
.mp-insight--attention { background: rgba(196,132,29,0.06); border-color: var(--mp-warning); color: #7A5200; }
.mp-insight--critical { background: rgba(192,57,43,0.06); border-color: var(--mp-danger); color: #7A1D1D; }

/* ── Avatar ── */
.mp-avatar {
    width: 32px; height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
    text-transform: uppercase;
}
.mp-avatar--navy { background: var(--mp-navy-mid); }
.mp-avatar--gold { background: var(--mp-gold); color: var(--mp-navy); }
.mp-avatar--blue { background: var(--mp-blue); }

/* ── Empty State ── */
.mp-empty {
    text-align: center;
    padding: 3rem 1.5rem;
    color: var(--mp-text-3);
}
.mp-empty-icon {
    width: 48px; height: 48px;
    margin: 0 auto 1rem;
    opacity: 0.3;
}
.mp-empty p { font-size: 0.85rem; }
</style>
@endpush

@section('content')
<div class="mp-preview">
<div class="mp-page">

    {{-- ====== HERO HEADER ====== --}}
    <div class="mp-hero mp-anim mp-anim-1">
        <p class="mp-hero-sub" style="margin-bottom:0.15rem;">{{ date('l, d \d\e F') }}</p>
        <h1 class="mp-hero-title">Bom dia, {{ auth()->user()->name ?? 'Rafael' }}</h1>
        <div class="mp-hero-meta">
            <div class="mp-hero-stat">
                <span class="mp-hero-stat-value">47</span>
                <span class="mp-hero-stat-label">Processos Ativos</span>
            </div>
            <div class="mp-hero-stat-divider"></div>
            <div class="mp-hero-stat">
                <span class="mp-hero-stat-value">12</span>
                <span class="mp-hero-stat-label">Prazos Semana</span>
            </div>
            <div class="mp-hero-stat-divider"></div>
            <div class="mp-hero-stat">
                <span class="mp-hero-stat-value">R$ 84k</span>
                <span class="mp-hero-stat-label">Receita Mês</span>
            </div>
            <div class="mp-hero-stat-divider"></div>
            <div class="mp-hero-stat">
                <span class="mp-hero-stat-value" style="color:var(--mp-gold-soft)">92</span>
                <span class="mp-hero-stat-label">Score GDP</span>
            </div>
        </div>
    </div>

    {{-- ====== KPI CARDS ====== --}}
    <div class="mp-section-label mp-anim mp-anim-2">Indicadores-chave</div>
    <div class="mp-grid-4 mp-anim mp-anim-2" style="margin-bottom:2rem;">
        <div class="mp-kpi">
            <div class="mp-kpi-accent"></div>
            <div class="mp-kpi-icon mp-kpi-icon--blue">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div class="mp-kpi-value">128</div>
            <div class="mp-kpi-label">Clientes Ativos</div>
            <div class="mp-kpi-change mp-kpi-change--up">+8 este mês</div>
        </div>
        <div class="mp-kpi">
            <div class="mp-kpi-accent" style="background:var(--mp-success)"></div>
            <div class="mp-kpi-icon mp-kpi-icon--green">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="mp-kpi-value">R$ 84.2k</div>
            <div class="mp-kpi-label">Receita Mensal</div>
            <div class="mp-kpi-change mp-kpi-change--up">+12.5%</div>
        </div>
        <div class="mp-kpi">
            <div class="mp-kpi-accent" style="background:var(--mp-warning)"></div>
            <div class="mp-kpi-icon mp-kpi-icon--gold">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="mp-kpi-value">7</div>
            <div class="mp-kpi-label">Prazos Urgentes</div>
            <div class="mp-kpi-change mp-kpi-change--down">+3 vs. semana passada</div>
        </div>
        <div class="mp-kpi">
            <div class="mp-kpi-accent" style="background:var(--mp-blue)"></div>
            <div class="mp-kpi-icon mp-kpi-icon--blue">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="mp-kpi-value">94%</div>
            <div class="mp-kpi-label">Taxa de Sucesso</div>
            <div class="mp-kpi-change mp-kpi-change--up">+2pp</div>
        </div>
    </div>

    {{-- ====== MAIN GRID: 2/3 + 1/3 ====== --}}
    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:1.25rem; margin-bottom:2rem;" class="mp-anim mp-anim-3">

        {{-- Left: Table --}}
        <div class="mp-card">
            <div class="mp-card-header">
                <h3>Processos Recentes</h3>
                <div style="display:flex;gap:0.5rem;">
                    <button class="mp-btn mp-btn--ghost mp-btn--sm">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filtrar
                    </button>
                    <button class="mp-btn mp-btn--outline mp-btn--sm">Ver todos</button>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="mp-table">
                    <thead>
                        <tr>
                            <th>Processo</th>
                            <th>Cliente</th>
                            <th>Status</th>
                            <th>Responsável</th>
                            <th>Atualização</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <span class="cell-name">5012345-67.2025.8.24.0033</span>
                                <div class="cell-meta">Reclamação Trabalhista</div>
                            </td>
                            <td>Silva & Associados Ltda</td>
                            <td><span class="mp-badge mp-badge--success">Em andamento</span></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:0.5rem;">
                                    <div class="mp-avatar mp-avatar--navy">RM</div>
                                    <span>Rafael M.</span>
                                </div>
                            </td>
                            <td class="cell-meta">Hoje, 14:30</td>
                        </tr>
                        <tr>
                            <td>
                                <span class="cell-name">5098765-43.2024.8.24.0033</span>
                                <div class="cell-meta">Indenização por Danos</div>
                            </td>
                            <td>João Pedro Oliveira</td>
                            <td><span class="mp-badge mp-badge--warning">Aguardando</span></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:0.5rem;">
                                    <div class="mp-avatar mp-avatar--blue">LC</div>
                                    <span>Letícia C.</span>
                                </div>
                            </td>
                            <td class="cell-meta">Ontem, 09:15</td>
                        </tr>
                        <tr>
                            <td>
                                <span class="cell-name">5034567-89.2025.8.24.0033</span>
                                <div class="cell-meta">Revisão Contratual</div>
                            </td>
                            <td>Construtora Beira Mar</td>
                            <td><span class="mp-badge mp-badge--info">Petição</span></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:0.5rem;">
                                    <div class="mp-avatar mp-avatar--gold">AP</div>
                                    <span>Ana P.</span>
                                </div>
                            </td>
                            <td class="cell-meta">12/04/2026</td>
                        </tr>
                        <tr>
                            <td>
                                <span class="cell-name">5011223-44.2025.8.24.0033</span>
                                <div class="cell-meta">Execução Fiscal</div>
                            </td>
                            <td>Marina Santos da Costa</td>
                            <td><span class="mp-badge mp-badge--danger">Prazo hoje</span></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:0.5rem;">
                                    <div class="mp-avatar mp-avatar--navy">RM</div>
                                    <span>Rafael M.</span>
                                </div>
                            </td>
                            <td class="cell-meta">Hoje, 08:00</td>
                        </tr>
                        <tr>
                            <td>
                                <span class="cell-name">5055667-88.2024.8.24.0033</span>
                                <div class="cell-meta">Aposentadoria Especial</div>
                            </td>
                            <td>Carlos Eduardo Ramos</td>
                            <td><span class="mp-badge mp-badge--gold">Acordo</span></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:0.5rem;">
                                    <div class="mp-avatar mp-avatar--blue">LC</div>
                                    <span>Letícia C.</span>
                                </div>
                            </td>
                            <td class="cell-meta">10/04/2026</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Right: Activity Feed --}}
        <div class="mp-card">
            <div class="mp-card-header">
                <h3>Atividade Recente</h3>
                <button class="mp-btn mp-btn--ghost mp-btn--sm">Ver tudo</button>
            </div>
            <div class="mp-card-body" style="padding:0.75rem 1.5rem;">
                <div class="mp-activity-item">
                    <div class="mp-activity-dot mp-activity-dot--green"></div>
                    <div>
                        <div class="mp-activity-text"><strong>Petição protocolada</strong> no processo de Silva & Associados</div>
                        <div class="mp-activity-time">Há 25 minutos</div>
                    </div>
                </div>
                <div class="mp-activity-item">
                    <div class="mp-activity-dot mp-activity-dot--gold"></div>
                    <div>
                        <div class="mp-activity-text"><strong>Novo lead</strong> captado via WhatsApp: Construtora ABC</div>
                        <div class="mp-activity-time">Há 1 hora</div>
                    </div>
                </div>
                <div class="mp-activity-item">
                    <div class="mp-activity-dot mp-activity-dot--blue"></div>
                    <div>
                        <div class="mp-activity-text"><strong>Audiência agendada</strong> para 22/04 — João Pedro Oliveira</div>
                        <div class="mp-activity-time">Há 2 horas</div>
                    </div>
                </div>
                <div class="mp-activity-item">
                    <div class="mp-activity-dot mp-activity-dot--red"></div>
                    <div>
                        <div class="mp-activity-text"><strong>Prazo vencendo hoje</strong> — Execução Fiscal de Marina Santos</div>
                        <div class="mp-activity-time">Há 3 horas</div>
                    </div>
                </div>
                <div class="mp-activity-item">
                    <div class="mp-activity-dot mp-activity-dot--green"></div>
                    <div>
                        <div class="mp-activity-text"><strong>Pagamento recebido</strong>: R$ 4.500 de Carlos Eduardo</div>
                        <div class="mp-activity-time">Há 5 horas</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ====== INSIGHTS ROW ====== --}}
    <div class="mp-anim mp-anim-4" style="margin-bottom:2rem;">
        <div class="mp-section-title">Insights</div>
        <div class="mp-grid-3">
            <div class="mp-insight mp-insight--positive">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                <div>Receita <strong>12,5% acima</strong> da meta mensal. Melhor resultado do trimestre.</div>
            </div>
            <div class="mp-insight mp-insight--attention">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                <div><strong>3 processos</strong> com prazos nos próximos 48h exigem atenção imediata.</div>
            </div>
            <div class="mp-insight mp-insight--critical">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div><strong>R$ 12.800</strong> em honorários vencidos há mais de 30 dias. 2 clientes.</div>
            </div>
        </div>
    </div>

    {{-- ====== BOTTOM GRID: Tabs + Form + Progress ====== --}}
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.25rem; margin-bottom:2rem;" class="mp-anim mp-anim-5">

        {{-- Card with Tabs --}}
        <div class="mp-card">
            <div class="mp-card-body">
                <div class="mp-tabs">
                    <button class="mp-tab active">Pipeline</button>
                    <button class="mp-tab">Leads</button>
                    <button class="mp-tab">Carteira</button>
                </div>
                <div style="display:flex;flex-direction:column;gap:1rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <div style="font-weight:600;color:var(--mp-navy-mid);font-size:0.85rem;">Construtora Horizonte</div>
                            <div style="font-size:0.72rem;color:var(--mp-text-3);">Consultoria Trabalhista</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:700;color:var(--mp-navy);font-size:0.9rem;">R$ 15.000</div>
                            <span class="mp-badge mp-badge--info">Proposta</span>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <div style="font-weight:600;color:var(--mp-navy-mid);font-size:0.85rem;">Ind. Metalúrgica Sul</div>
                            <div style="font-size:0.72rem;color:var(--mp-text-3);">Contencioso Cível</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:700;color:var(--mp-navy);font-size:0.9rem;">R$ 32.000</div>
                            <span class="mp-badge mp-badge--warning">Negociação</span>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <div style="font-weight:600;color:var(--mp-navy-mid);font-size:0.85rem;">Farmácia Vida Plena</div>
                            <div style="font-size:0.72rem;color:var(--mp-text-3);">Recuperação Tributária</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-weight:700;color:var(--mp-navy);font-size:0.9rem;">R$ 8.500</div>
                            <span class="mp-badge mp-badge--success">Fechado</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card: Metas --}}
        <div class="mp-card">
            <div class="mp-card-header">
                <h3>Metas do Mês</h3>
                <span class="mp-badge mp-badge--gold">Abril 2026</span>
            </div>
            <div class="mp-card-body" style="display:flex;flex-direction:column;gap:1.25rem;">
                <div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:0.4rem;">
                        <span style="font-size:0.8rem;font-weight:600;color:var(--mp-text);">Receita</span>
                        <span style="font-size:0.75rem;color:var(--mp-text-3);">R$ 84k / R$ 75k</span>
                    </div>
                    <div class="mp-progress"><div class="mp-progress-bar mp-progress-bar--green" style="width:100%"></div></div>
                </div>
                <div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:0.4rem;">
                        <span style="font-size:0.8rem;font-weight:600;color:var(--mp-text);">Novos Clientes</span>
                        <span style="font-size:0.75rem;color:var(--mp-text-3);">8 / 10</span>
                    </div>
                    <div class="mp-progress"><div class="mp-progress-bar mp-progress-bar--blue" style="width:80%"></div></div>
                </div>
                <div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:0.4rem;">
                        <span style="font-size:0.8rem;font-weight:600;color:var(--mp-text);">Leads Convertidos</span>
                        <span style="font-size:0.75rem;color:var(--mp-text-3);">62%</span>
                    </div>
                    <div class="mp-progress"><div class="mp-progress-bar mp-progress-bar--gold" style="width:62%"></div></div>
                </div>
                <div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:0.4rem;">
                        <span style="font-size:0.8rem;font-weight:600;color:var(--mp-text);">Horas Faturadas</span>
                        <span style="font-size:0.75rem;color:var(--mp-text-3);">142h / 180h</span>
                    </div>
                    <div class="mp-progress"><div class="mp-progress-bar mp-progress-bar--blue" style="width:79%"></div></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ====== COMPONENT SHOWCASE ====== --}}
    <div class="mp-anim mp-anim-6" style="margin-bottom:2rem;">
        <div class="mp-section-title">Componentes</div>
        <div class="mp-card">
            <div class="mp-card-body">

                {{-- Buttons --}}
                <div class="mp-section-label">Botões</div>
                <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:2rem;">
                    <button class="mp-btn mp-btn--primary">Primário</button>
                    <button class="mp-btn mp-btn--gold">Dourado</button>
                    <button class="mp-btn mp-btn--outline">Outline</button>
                    <button class="mp-btn mp-btn--ghost">Ghost</button>
                    <button class="mp-btn mp-btn--primary mp-btn--sm">Pequeno</button>
                    <button class="mp-btn mp-btn--outline mp-btn--sm">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="14" height="14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Com ícone
                    </button>
                </div>

                {{-- Badges --}}
                <div class="mp-section-label">Badges</div>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:2rem;">
                    <span class="mp-badge mp-badge--success">Ativo</span>
                    <span class="mp-badge mp-badge--warning">Pendente</span>
                    <span class="mp-badge mp-badge--danger">Urgente</span>
                    <span class="mp-badge mp-badge--info">Em análise</span>
                    <span class="mp-badge mp-badge--gold">Premium</span>
                    <span class="mp-badge mp-badge--neutral">Arquivado</span>
                </div>

                {{-- Form Elements --}}
                <div class="mp-section-label">Formulário</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:2rem;">
                    <div>
                        <label class="mp-label">Nome do cliente</label>
                        <input type="text" class="mp-input" placeholder="Digite o nome...">
                    </div>
                    <div>
                        <label class="mp-label">CPF / CNPJ</label>
                        <input type="text" class="mp-input" placeholder="000.000.000-00">
                    </div>
                    <div>
                        <label class="mp-label">Área jurídica</label>
                        <select class="mp-input">
                            <option>Trabalhista</option>
                            <option>Cível</option>
                            <option>Previdenciário</option>
                            <option>Criminal</option>
                        </select>
                    </div>
                </div>

                {{-- Avatars --}}
                <div class="mp-section-label">Avatares</div>
                <div style="display:flex;gap:0.5rem;margin-bottom:1rem;">
                    <div class="mp-avatar mp-avatar--navy">RM</div>
                    <div class="mp-avatar mp-avatar--gold">LC</div>
                    <div class="mp-avatar mp-avatar--blue">AP</div>
                    <div class="mp-avatar mp-avatar--navy">JS</div>
                    <div class="mp-avatar mp-avatar--gold">MK</div>
                </div>

            </div>
        </div>
    </div>

</div>
</div>
@endsection
