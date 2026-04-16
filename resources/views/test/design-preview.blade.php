@extends('layouts.app')
@section('title', 'Design Preview')

@push('styles')
<style>
/* ============================================================
   MAYER DESIGN SYSTEM — v7 "Working Dashboard"
   Fonte: Montserrat | Cores: #1B334A / #385776
   Profundidade, contraste, amigavel pra trabalho diario
   ============================================================ */

.ds {
    --ds-navy: #1B334A;
    --ds-navy-d: #0F2030;
    --ds-primary: #385776;
    --ds-primary-l: #4A6D8C;
    --ds-primary-xl: #7BA3C4;
    --ds-primary-bg: rgba(56,87,118,.08);
    --ds-bg: #EAEef3;
    --ds-bg-grad: linear-gradient(180deg, #E0E5EC 0%, #EAEef3 40%, #F0F2F6 100%);
    --ds-surface: #FFFFFF;
    --ds-surface-2: #F6F8FA;
    --ds-surface-3: #EDF1F5;
    --ds-text: #1B334A;
    --ds-text-2: #546577;
    --ds-text-3: #8896A6;
    --ds-border: #D8DEE6;
    --ds-border-l: #E8ECF1;
    --ds-success: #0D9467;
    --ds-success-bg: #E8FAF2;
    --ds-warning: #D97706;
    --ds-warning-bg: #FFF8EB;
    --ds-danger: #DC2626;
    --ds-danger-bg: #FEF0F0;
    --ds-radius: 14px;
    --ds-radius-sm: 10px;
    --ds-radius-xs: 8px;
    --ds-radius-full: 9999px;
    --ds-shadow: 0 1px 3px rgba(27,51,74,.06), 0 1px 2px rgba(27,51,74,.04);
    --ds-shadow-md: 0 4px 16px rgba(27,51,74,.08), 0 2px 4px rgba(27,51,74,.04);
    --ds-shadow-lg: 0 8px 32px rgba(27,51,74,.12), 0 2px 8px rgba(27,51,74,.06);
    --ds-font: 'Montserrat', system-ui, -apple-system, sans-serif;
    --ds-ease: cubic-bezier(.4, 0, .2, 1);

    font-family: var(--ds-font) !important;
    color: var(--ds-text);
    background: var(--ds-bg-grad);
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
}
.ds *, .ds *::before, .ds *::after {
    font-family: var(--ds-font) !important;
    box-sizing: border-box;
}

/* ── Animations ── */
@keyframes dsUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes dsGrow { from { width: 0; } }
@keyframes dsChartDraw {
    from { stroke-dashoffset: 600; }
    to   { stroke-dashoffset: 0; }
}
@keyframes dsChartFade {
    from { opacity: 0; }
    to   { opacity: 1; }
}
@keyframes dsSlideIn {
    from { opacity: 0; transform: translateX(-12px); }
    to   { opacity: 1; transform: translateX(0); }
}
@keyframes dsNumberBlur {
    from { opacity: 0; transform: translateY(8px); filter: blur(3px); }
    to   { opacity: 1; transform: translateY(0); filter: blur(0); }
}
@keyframes dsPulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(2); opacity: 0; }
}

.ds-a { animation: dsUp .6s var(--ds-ease) both; }
.ds-a1 { animation-delay: .03s }
.ds-a2 { animation-delay: .08s }
.ds-a3 { animation-delay: .14s }
.ds-a4 { animation-delay: .20s }
.ds-a5 { animation-delay: .26s }
.ds-a6 { animation-delay: .32s }
.ds-a7 { animation-delay: .38s }

/* ══════════════════════════════════════
   PAGE
   ══════════════════════════════════════ */
.ds-page {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* ══════════════════════════════════════
   HERO — navy anchor, profundidade real
   ══════════════════════════════════════ */
.ds-hero {
    background: linear-gradient(160deg, var(--ds-navy-d) 0%, var(--ds-navy) 40%, var(--ds-primary) 100%);
    border-radius: var(--ds-radius);
    padding: 2rem 2rem 0;
    position: relative;
    overflow: hidden;
    box-shadow: var(--ds-shadow-lg), inset 0 1px 0 rgba(255,255,255,.04);
}
/* Mesh de luz */
.ds-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse 50% 70% at 75% 15%, rgba(74,109,140,.3) 0%, transparent 60%),
        radial-gradient(ellipse 35% 50% at 15% 85%, rgba(56,87,118,.2) 0%, transparent 50%);
    pointer-events: none;
}
/* Noise */
.ds-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.03'/%3E%3C/svg%3E");
    background-size: 100px;
    pointer-events: none;
    opacity: .6;
}
.ds-hero > * { position: relative; z-index: 1; }

.ds-hero-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}
.ds-hero-greeting {
    font-size: .6rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .14em;
    color: rgba(255,255,255,.35);
    margin-bottom: .25rem;
    animation: dsSlideIn .5s var(--ds-ease) .1s both;
}
.ds-hero-name {
    font-size: 1.65rem;
    font-weight: 700;
    color: #fff;
    letter-spacing: -.025em;
    line-height: 1.2;
    animation: dsUp .6s var(--ds-ease) .05s both;
}
.ds-hero-date {
    font-size: .7rem;
    color: rgba(255,255,255,.3);
    font-weight: 400;
    margin-top: .2rem;
    animation: dsSlideIn .5s var(--ds-ease) .15s both;
}

.ds-hero-actions {
    display: flex;
    gap: .35rem;
}
.ds-hero-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: .4rem .8rem;
    font-size: .68rem;
    font-weight: 600;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,.1);
    background: rgba(255,255,255,.05);
    color: rgba(255,255,255,.6);
    cursor: pointer;
    transition: all .2s;
    backdrop-filter: blur(8px);
}
.ds-hero-btn:hover {
    background: rgba(255,255,255,.12);
    color: #fff;
    border-color: rgba(255,255,255,.2);
}
.ds-hero-btn--accent {
    background: rgba(255,255,255,.1);
    color: #fff;
    border-color: rgba(255,255,255,.18);
}
.ds-hero-btn--accent:hover {
    background: rgba(255,255,255,.18);
}
.ds-hero-btn svg { width: 13px; height: 13px; }

/* Hero stat strip — integrado ao hero */
.ds-hero-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    margin: 0 -2rem;
    border-top: 1px solid rgba(255,255,255,.06);
    background: rgba(0,0,0,.12);
    backdrop-filter: blur(12px);
}
.ds-hero-stat {
    padding: 1rem 1.5rem;
    border-right: 1px solid rgba(255,255,255,.05);
    transition: background .2s;
}
.ds-hero-stat:last-child { border-right: none; }
.ds-hero-stat:hover { background: rgba(255,255,255,.03); }

.ds-hero-stat-value {
    font-size: 1.35rem;
    font-weight: 700;
    color: #fff;
    letter-spacing: -.02em;
    line-height: 1;
    animation: dsNumberBlur .5s var(--ds-ease) both;
}
.ds-hero-stat:nth-child(1) .ds-hero-stat-value { animation-delay: .2s; }
.ds-hero-stat:nth-child(2) .ds-hero-stat-value { animation-delay: .25s; }
.ds-hero-stat:nth-child(3) .ds-hero-stat-value { animation-delay: .3s; }
.ds-hero-stat:nth-child(4) .ds-hero-stat-value { animation-delay: .35s; }

.ds-hero-stat-label {
    font-size: .55rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: rgba(255,255,255,.3);
    margin-top: .3rem;
}
.ds-hero-stat-delta {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    font-size: .55rem;
    font-weight: 700;
    margin-top: .3rem;
    padding: 1px 6px;
    border-radius: 4px;
}
.ds-hero-stat-delta--up { background: rgba(16,185,129,.18); color: #6EE7B7; }
.ds-hero-stat-delta--down { background: rgba(239,68,68,.18); color: #FCA5A5; }

/* ══════════════════════════════════════
   CARDS — com sombra forte, destaque
   ══════════════════════════════════════ */
.ds-card {
    background: var(--ds-surface);
    border: 1px solid var(--ds-border-l);
    border-radius: var(--ds-radius);
    box-shadow: var(--ds-shadow);
    overflow: hidden;
    transition: box-shadow .3s var(--ds-ease), transform .3s var(--ds-ease);
}
.ds-card:hover {
    box-shadow: var(--ds-shadow-md);
    transform: translateY(-1px);
}
.ds-card-head {
    padding: 1rem 1.35rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--ds-border-l);
    background: var(--ds-surface-2);
}
.ds-card-head h3 {
    font-size: .82rem;
    font-weight: 700;
    color: var(--ds-navy);
    margin: 0;
}
.ds-card-body { padding: 1.35rem; }
.ds-card-body--flush { padding: 0; }
.ds-card-foot {
    padding: .7rem 1.35rem;
    border-top: 1px solid var(--ds-border-l);
    background: var(--ds-surface-2);
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Card com borda de destaque (accent top) */
.ds-card--accent {
    border-top: 3px solid var(--ds-primary);
}
.ds-card--success {
    border-top: 3px solid var(--ds-success);
}

/* ══════════════════════════════════════
   STAT CARDS — coloridos, com vida
   ══════════════════════════════════════ */
.ds-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: .85rem;
}
.ds-stat {
    background: var(--ds-surface);
    border: 1px solid var(--ds-border-l);
    border-radius: var(--ds-radius);
    padding: 1.25rem 1.35rem;
    box-shadow: var(--ds-shadow);
    transition: all .25s var(--ds-ease);
    position: relative;
    overflow: hidden;
}
.ds-stat:hover {
    box-shadow: var(--ds-shadow-md);
    transform: translateY(-2px);
}
/* Colored left bar */
.ds-stat::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    border-radius: var(--ds-radius) 0 0 var(--ds-radius);
}
.ds-stat--blue::before { background: var(--ds-primary); }
.ds-stat--green::before { background: var(--ds-success); }
.ds-stat--orange::before { background: var(--ds-warning); }
.ds-stat--red::before { background: var(--ds-danger); }

/* Subtle bg tint */
.ds-stat--blue { background: linear-gradient(135deg, rgba(56,87,118,.03) 0%, var(--ds-surface) 60%); }
.ds-stat--green { background: linear-gradient(135deg, rgba(13,148,103,.03) 0%, var(--ds-surface) 60%); }
.ds-stat--orange { background: linear-gradient(135deg, rgba(217,119,6,.03) 0%, var(--ds-surface) 60%); }
.ds-stat--red { background: linear-gradient(135deg, rgba(220,38,38,.03) 0%, var(--ds-surface) 60%); }

.ds-stat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: .85rem;
}
.ds-stat-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ds-stat-icon svg { width: 17px; height: 17px; }
.ds-stat-icon--primary { background: var(--ds-primary-bg); color: var(--ds-primary); }
.ds-stat-icon--success { background: var(--ds-success-bg); color: var(--ds-success); }
.ds-stat-icon--warning { background: var(--ds-warning-bg); color: var(--ds-warning); }
.ds-stat-icon--danger { background: var(--ds-danger-bg); color: var(--ds-danger); }

.ds-stat-delta {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 7px;
    border-radius: var(--ds-radius-full);
    font-size: .6rem;
    font-weight: 600;
}
.ds-stat-delta--up { background: var(--ds-success-bg); color: #059669; }
.ds-stat-delta--down { background: var(--ds-danger-bg); color: var(--ds-danger); }
.ds-stat-delta svg { width: 9px; height: 9px; }

.ds-stat-value {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--ds-navy);
    letter-spacing: -.02em;
    line-height: 1;
}
.ds-stat-label {
    font-size: .68rem;
    color: var(--ds-text-3);
    font-weight: 500;
    margin-top: .35rem;
}
/* Mini spark */
.ds-stat-spark {
    position: absolute;
    bottom: 0; right: 0;
    width: 90px; height: 36px;
    opacity: .12;
}

/* ══════════════════════════════════════
   CHART
   ══════════════════════════════════════ */
.ds-chart-wrap { padding: 1.25rem 1.35rem 1.35rem; }
.ds-chart-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 1.15rem;
}
.ds-chart-big {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--ds-navy);
    letter-spacing: -.025em;
    line-height: 1;
}
.ds-chart-sub {
    font-size: .72rem;
    color: var(--ds-text-3);
    font-weight: 400;
    margin-top: .25rem;
}
.ds-chart-tabs {
    display: flex;
    gap: 2px;
    background: var(--ds-surface-3);
    border-radius: var(--ds-radius-xs);
    padding: 3px;
}
.ds-chart-tab {
    padding: .35rem .7rem;
    font-size: .62rem;
    font-weight: 600;
    color: var(--ds-text-3);
    border: none;
    background: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all .15s;
}
.ds-chart-tab:hover { color: var(--ds-text-2); }
.ds-chart-tab.active {
    background: var(--ds-surface);
    color: var(--ds-navy);
    box-shadow: 0 1px 3px rgba(0,0,0,.06);
}

.ds-chart-svg { width: 100%; height: 170px; overflow: visible; }
.ds-chart-line {
    fill: none;
    stroke: var(--ds-primary);
    stroke-width: 2.5;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 600;
    animation: dsChartDraw 1.5s var(--ds-ease) .3s both;
}
.ds-chart-area {
    fill: url(#chartGrad);
    opacity: 0;
    animation: dsChartFade .8s var(--ds-ease) .8s both;
}
.ds-chart-dot {
    fill: var(--ds-surface);
    stroke: var(--ds-primary);
    stroke-width: 2.5;
    r: 4.5;
    opacity: 0;
    animation: dsChartFade .3s var(--ds-ease) 1.2s both;
    filter: drop-shadow(0 2px 4px rgba(56,87,118,.3));
}
.ds-chart-grid { stroke: var(--ds-border-l); stroke-width: .5; }
.ds-chart-label { fill: var(--ds-text-3); font-size: 10px; font-weight: 500; }

/* ══════════════════════════════════════
   TRANSACTIONS
   ══════════════════════════════════════ */
.ds-tx {
    display: flex;
    align-items: center;
    gap: .85rem;
    padding: .8rem 1.35rem;
    transition: background .15s;
}
.ds-tx:hover { background: var(--ds-surface-2); }
.ds-tx + .ds-tx { border-top: 1px solid var(--ds-border-l); }
.ds-tx-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.ds-tx-icon svg { width: 16px; height: 16px; }
.ds-tx-info { flex: 1; min-width: 0; }
.ds-tx-name { font-size: .78rem; font-weight: 600; color: var(--ds-navy); }
.ds-tx-desc { font-size: .65rem; color: var(--ds-text-3); margin-top: 1px; }
.ds-tx-right { text-align: right; }
.ds-tx-amount { font-size: .8rem; font-weight: 700; color: var(--ds-navy); font-variant-numeric: tabular-nums; }
.ds-tx-amount--in { color: var(--ds-success); }
.ds-tx-date { font-size: .6rem; color: var(--ds-text-3); margin-top: 2px; }

/* ══════════════════════════════════════
   TABLE
   ══════════════════════════════════════ */
.ds-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.ds-table thead th {
    font-size: .6rem;
    font-weight: 600;
    color: var(--ds-text-3);
    text-transform: uppercase;
    letter-spacing: .06em;
    padding: .7rem 1.15rem;
    text-align: left;
    border-bottom: 1px solid var(--ds-border);
    background: var(--ds-surface-2);
}
.ds-table tbody td {
    padding: .8rem 1.15rem;
    font-size: .78rem;
    border-bottom: 1px solid var(--ds-border-l);
    vertical-align: middle;
}
.ds-table tbody tr { transition: background .12s; }
.ds-table tbody tr:hover td { background: var(--ds-surface-2); }
.ds-table tbody tr:last-child td { border-bottom: none; }
.ds-table .td-main { font-weight: 600; color: var(--ds-navy); }
.ds-table .td-sub { font-size: .62rem; color: var(--ds-text-3); margin-top: 1px; }
.ds-table .td-muted { color: var(--ds-text-3); font-size: .72rem; }

/* ══════════════════════════════════════
   BADGES
   ══════════════════════════════════════ */
.ds-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    font-size: .62rem;
    font-weight: 600;
    border-radius: var(--ds-radius-full);
}
.ds-badge::before {
    content: '';
    width: 5px; height: 5px;
    border-radius: 50%;
    background: currentColor;
}
.ds-badge--success { background: var(--ds-success-bg); color: #059669; }
.ds-badge--warning { background: var(--ds-warning-bg); color: var(--ds-warning); }
.ds-badge--danger  { background: var(--ds-danger-bg); color: var(--ds-danger); }
.ds-badge--info    { background: var(--ds-primary-bg); color: var(--ds-primary); }
.ds-badge--neutral { background: var(--ds-surface-3); color: var(--ds-text-3); }
.ds-badge--neutral::before { display: none; }
.ds-badge--danger::before { position: relative; }
.ds-badge--danger::after {
    content: '';
    width: 5px; height: 5px;
    border-radius: 50%;
    background: var(--ds-danger);
    position: absolute;
    animation: dsPulse 2s ease infinite;
}

/* ══════════════════════════════════════
   AVATARS
   ══════════════════════════════════════ */
.ds-av {
    width: 30px; height: 30px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .6rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}
.ds-av--navy { background: var(--ds-navy); }
.ds-av--primary { background: var(--ds-primary); }
.ds-av--light { background: var(--ds-surface-3); color: var(--ds-text-2); }

/* ══════════════════════════════════════
   BUTTONS
   ══════════════════════════════════════ */
.ds-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: .5rem 1rem;
    font-size: .72rem;
    font-weight: 600;
    border-radius: var(--ds-radius-xs);
    border: 1px solid transparent;
    cursor: pointer;
    transition: all .2s var(--ds-ease);
    white-space: nowrap;
    text-decoration: none;
}
.ds-btn:active { transform: scale(.97); }
.ds-btn svg { width: 14px; height: 14px; flex-shrink: 0; }
.ds-btn--primary { background: var(--ds-primary); color: #fff; }
.ds-btn--primary:hover { background: #2D475F; box-shadow: 0 4px 14px rgba(56,87,118,.3); transform: translateY(-1px); }
.ds-btn--secondary { background: var(--ds-surface); color: var(--ds-text); border-color: var(--ds-border); box-shadow: var(--ds-shadow); }
.ds-btn--secondary:hover { border-color: var(--ds-primary); color: var(--ds-primary); }
.ds-btn--ghost { background: none; color: var(--ds-text-3); }
.ds-btn--ghost:hover { background: var(--ds-surface-3); color: var(--ds-text); }
.ds-btn--sm { padding: .35rem .7rem; font-size: .65rem; }

/* ══════════════════════════════════════
   PROGRESS
   ══════════════════════════════════════ */
.ds-progress { height: 6px; background: var(--ds-surface-3); border-radius: 3px; overflow: hidden; }
.ds-progress-fill { height: 100%; border-radius: 3px; animation: dsGrow .9s var(--ds-ease) both; }
.ds-progress-fill--primary { background: var(--ds-primary); }
.ds-progress-fill--success { background: var(--ds-success); }
.ds-progress-fill--navy { background: var(--ds-navy); }

/* ══════════════════════════════════════
   TABS
   ══════════════════════════════════════ */
.ds-tabs {
    display: inline-flex;
    gap: 2px;
    background: var(--ds-surface-3);
    border-radius: var(--ds-radius-xs);
    padding: 3px;
}
.ds-tab {
    padding: .38rem .8rem;
    font-size: .65rem;
    font-weight: 600;
    color: var(--ds-text-3);
    border: none;
    background: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all .15s;
}
.ds-tab:hover { color: var(--ds-text-2); }
.ds-tab.active { background: var(--ds-surface); color: var(--ds-navy); box-shadow: 0 1px 3px rgba(0,0,0,.06); }

/* ══════════════════════════════════════
   GOALS
   ══════════════════════════════════════ */
.ds-goal + .ds-goal { margin-top: 1rem; }
.ds-goal-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: .35rem; }
.ds-goal-name { font-size: .75rem; font-weight: 600; color: var(--ds-text); }
.ds-goal-nums { font-size: .65rem; color: var(--ds-text-3); font-weight: 500; font-variant-numeric: tabular-nums; }

/* ══════════════════════════════════════
   SHOWCASE
   ══════════════════════════════════════ */
.ds-showcase + .ds-showcase { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--ds-border-l); }
.ds-showcase-label { font-size: .55rem; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; color: var(--ds-text-3); margin-bottom: .65rem; }
.ds-label { display: block; font-size: .65rem; font-weight: 600; color: var(--ds-text-2); margin-bottom: .3rem; }
.ds-input {
    width: 100%; padding: .55rem .9rem; font-size: .78rem;
    border: 1.5px solid var(--ds-border); border-radius: var(--ds-radius-xs);
    background: var(--ds-surface); color: var(--ds-text); transition: all .2s; font-weight: 500;
}
.ds-input:focus { border-color: var(--ds-primary); box-shadow: 0 0 0 3px var(--ds-primary-bg); outline: none; }
.ds-input::placeholder { color: var(--ds-text-3); font-weight: 400; }

/* ══════════════════════════════════════
   PIPE
   ══════════════════════════════════════ */
.ds-pipe-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: .75rem 0; transition: padding-left .15s;
}
.ds-pipe-item:hover { padding-left: 4px; }
.ds-pipe-item + .ds-pipe-item { border-top: 1px solid var(--ds-border-l); }
.ds-pipe-name { font-weight: 600; font-size: .78rem; color: var(--ds-navy); }
.ds-pipe-meta { font-size: .62rem; color: var(--ds-text-3); }
.ds-pipe-val { font-weight: 700; font-size: .82rem; color: var(--ds-navy); font-variant-numeric: tabular-nums; }

/* ══════════════════════════════════════
   GRIDS + RESPONSIVE
   ══════════════════════════════════════ */
.ds-g4 { display: grid; grid-template-columns: repeat(4,1fr); gap: .85rem; }
.ds-g-main { display: grid; grid-template-columns: 1.6fr 1fr; gap: 1rem; }
.ds-g2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

@media (max-width:1100px) {
    .ds-g4 { grid-template-columns: repeat(2,1fr); }
    .ds-g-main { grid-template-columns: 1fr; }
}
@media (max-width:768px) {
    .ds-g4, .ds-g2 { grid-template-columns: 1fr; }
    .ds-hero-top { flex-direction: column; gap: .75rem; }
    .ds-hero-stats { grid-template-columns: repeat(2,1fr); }
    .ds-hero { padding: 1.5rem 1.5rem 0; }
    .ds-hero-stats { margin: 0 -1.5rem; }
    .ds-hero-name { font-size: 1.35rem; }
    .ds-chart-header { flex-direction: column; align-items: flex-start; gap: .75rem; }
}
@media (max-width:480px) {
    .ds-stat { padding: 1rem; }
    .ds-stat-value { font-size: 1.3rem; }
    .ds-hero-stats { grid-template-columns: 1fr 1fr; }
}
</style>
@endpush

@section('content')
<div class="ds">
<div class="ds-page">

    {{-- ══════ HERO ══════ --}}
    <div class="ds-hero ds-a ds-a1">
        <div class="ds-hero-top">
            <div>
                <div class="ds-hero-greeting">{{ date('G') < 12 ? 'Bom dia' : (date('G') < 18 ? 'Boa tarde' : 'Boa noite') }}</div>
                <div class="ds-hero-name">{{ auth()->user()->name ?? 'Rafael Mayer' }}</div>
                <div class="ds-hero-date">{{ \Carbon\Carbon::now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</div>
            </div>
            <div class="ds-hero-actions">
                <button class="ds-hero-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Buscar
                </button>
                <button class="ds-hero-btn--accent ds-hero-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Novo Processo
                </button>
            </div>
        </div>
        <div class="ds-hero-stats">
            <div class="ds-hero-stat">
                <div class="ds-hero-stat-value">47</div>
                <div class="ds-hero-stat-label">Processos Ativos</div>
                <div class="ds-hero-stat-delta ds-hero-stat-delta--up">+3</div>
            </div>
            <div class="ds-hero-stat">
                <div class="ds-hero-stat-value">R$ 84k</div>
                <div class="ds-hero-stat-label">Receita Mensal</div>
                <div class="ds-hero-stat-delta ds-hero-stat-delta--up">+12%</div>
            </div>
            <div class="ds-hero-stat">
                <div class="ds-hero-stat-value">12</div>
                <div class="ds-hero-stat-label">Prazos Semana</div>
                <div class="ds-hero-stat-delta ds-hero-stat-delta--down">+3</div>
            </div>
            <div class="ds-hero-stat">
                <div class="ds-hero-stat-value">94%</div>
                <div class="ds-hero-stat-label">Taxa Sucesso</div>
                <div class="ds-hero-stat-delta ds-hero-stat-delta--up">+2pp</div>
            </div>
        </div>
    </div>

    {{-- ══════ STAT CARDS ══════ --}}
    <div class="ds-g4 ds-a ds-a2">
        <div class="ds-stat ds-stat--blue">
            <div class="ds-stat-header">
                <div class="ds-stat-icon ds-stat-icon--primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <span class="ds-stat-delta ds-stat-delta--up">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                    +8
                </span>
            </div>
            <div class="ds-stat-value">128</div>
            <div class="ds-stat-label">Clientes Ativos</div>
            <svg class="ds-stat-spark" viewBox="0 0 90 36"><path d="M0,30 Q12,26 22,22 T45,14 T68,16 T90,6" fill="none" stroke="var(--ds-primary)" stroke-width="2"/><path d="M0,30 Q12,26 22,22 T45,14 T68,16 T90,6 V36 H0Z" fill="var(--ds-primary)" opacity=".4"/></svg>
        </div>
        <div class="ds-stat ds-stat--green">
            <div class="ds-stat-header">
                <div class="ds-stat-icon ds-stat-icon--success">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="ds-stat-delta ds-stat-delta--up">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                    +12.5%
                </span>
            </div>
            <div class="ds-stat-value">R$ 84.2k</div>
            <div class="ds-stat-label">Receita Mensal</div>
            <svg class="ds-stat-spark" viewBox="0 0 90 36"><path d="M0,28 Q18,22 30,18 T55,10 T90,4" fill="none" stroke="var(--ds-success)" stroke-width="2"/><path d="M0,28 Q18,22 30,18 T55,10 T90,4 V36 H0Z" fill="var(--ds-success)" opacity=".4"/></svg>
        </div>
        <div class="ds-stat ds-stat--orange">
            <div class="ds-stat-header">
                <div class="ds-stat-icon ds-stat-icon--warning">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="ds-stat-delta ds-stat-delta--down">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                    +3
                </span>
            </div>
            <div class="ds-stat-value">7</div>
            <div class="ds-stat-label">Prazos Urgentes</div>
        </div>
        <div class="ds-stat ds-stat--blue">
            <div class="ds-stat-header">
                <div class="ds-stat-icon ds-stat-icon--primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <span class="ds-stat-delta ds-stat-delta--up">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                    +2pp
                </span>
            </div>
            <div class="ds-stat-value">94%</div>
            <div class="ds-stat-label">Taxa de Sucesso</div>
            <svg class="ds-stat-spark" viewBox="0 0 90 36"><path d="M0,22 Q20,18 35,15 T65,8 T90,10" fill="none" stroke="var(--ds-primary)" stroke-width="2"/><path d="M0,22 Q20,18 35,15 T65,8 T90,10 V36 H0Z" fill="var(--ds-primary)" opacity=".4"/></svg>
        </div>
    </div>

    {{-- ══════ CHART + TRANSACTIONS ══════ --}}
    <div class="ds-g-main ds-a ds-a3">
        <div class="ds-card ds-card--accent">
            <div class="ds-chart-wrap">
                <div class="ds-chart-header">
                    <div>
                        <div class="ds-chart-big">R$ 284.500</div>
                        <div class="ds-chart-sub">Receita acumulada 2026</div>
                    </div>
                    <div class="ds-chart-tabs">
                        <button class="ds-chart-tab">7D</button>
                        <button class="ds-chart-tab active">1M</button>
                        <button class="ds-chart-tab">3M</button>
                        <button class="ds-chart-tab">1A</button>
                    </div>
                </div>
                <svg class="ds-chart-svg" viewBox="0 0 600 170" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--ds-primary)" stop-opacity=".2"/>
                            <stop offset="100%" stop-color="var(--ds-primary)" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <line class="ds-chart-grid" x1="0" y1="34" x2="600" y2="34"/>
                    <line class="ds-chart-grid" x1="0" y1="68" x2="600" y2="68"/>
                    <line class="ds-chart-grid" x1="0" y1="102" x2="600" y2="102"/>
                    <line class="ds-chart-grid" x1="0" y1="136" x2="600" y2="136"/>
                    <text class="ds-chart-label" x="5" y="165">Jan</text>
                    <text class="ds-chart-label" x="90" y="165">Fev</text>
                    <text class="ds-chart-label" x="175" y="165">Mar</text>
                    <text class="ds-chart-label" x="260" y="165">Abr</text>
                    <text class="ds-chart-label" x="345" y="165">Mai</text>
                    <text class="ds-chart-label" x="430" y="165">Jun</text>
                    <text class="ds-chart-label" x="515" y="165">Jul</text>
                    <path class="ds-chart-area" d="M0,120 C40,110 60,100 100,88 C140,76 160,80 200,65 C240,50 260,56 300,42 C340,28 360,36 400,32 C440,28 460,18 500,14 C540,10 560,16 600,10 L600,150 L0,150 Z"/>
                    <path class="ds-chart-line" d="M0,120 C40,110 60,100 100,88 C140,76 160,80 200,65 C240,50 260,56 300,42 C340,28 360,36 400,32 C440,28 460,18 500,14 C540,10 560,16 600,10"/>
                    <circle class="ds-chart-dot" cx="300" cy="42"/>
                </svg>
            </div>
        </div>

        <div class="ds-card">
            <div class="ds-card-head">
                <h3>Movimentacoes</h3>
                <button class="ds-btn ds-btn--ghost ds-btn--sm">Ver todas</button>
            </div>
            <div class="ds-card-body--flush">
                <div class="ds-tx">
                    <div class="ds-tx-icon" style="background:var(--ds-success-bg);color:var(--ds-success);">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                    </div>
                    <div class="ds-tx-info">
                        <div class="ds-tx-name">Honorarios — Silva & Assoc.</div>
                        <div class="ds-tx-desc">Parcela 3/6</div>
                    </div>
                    <div class="ds-tx-right">
                        <div class="ds-tx-amount ds-tx-amount--in">+ R$ 8.500</div>
                        <div class="ds-tx-date">Hoje</div>
                    </div>
                </div>
                <div class="ds-tx">
                    <div class="ds-tx-icon" style="background:var(--ds-success-bg);color:var(--ds-success);">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                    </div>
                    <div class="ds-tx-info">
                        <div class="ds-tx-name">Carlos Eduardo Ramos</div>
                        <div class="ds-tx-desc">Reembolso custas</div>
                    </div>
                    <div class="ds-tx-right">
                        <div class="ds-tx-amount ds-tx-amount--in">+ R$ 4.500</div>
                        <div class="ds-tx-date">Ontem</div>
                    </div>
                </div>
                <div class="ds-tx">
                    <div class="ds-tx-icon" style="background:var(--ds-danger-bg);color:var(--ds-danger);">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                    </div>
                    <div class="ds-tx-info">
                        <div class="ds-tx-name">Custas TJ-SC</div>
                        <div class="ds-tx-desc">GRU proc. 5034567</div>
                    </div>
                    <div class="ds-tx-right">
                        <div class="ds-tx-amount">- R$ 1.240</div>
                        <div class="ds-tx-date">12/04</div>
                    </div>
                </div>
                <div class="ds-tx">
                    <div class="ds-tx-icon" style="background:var(--ds-success-bg);color:var(--ds-success);">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                    </div>
                    <div class="ds-tx-info">
                        <div class="ds-tx-name">Construtora Beira Mar</div>
                        <div class="ds-tx-desc">Parecer avulso</div>
                    </div>
                    <div class="ds-tx-right">
                        <div class="ds-tx-amount ds-tx-amount--in">+ R$ 3.200</div>
                        <div class="ds-tx-date">10/04</div>
                    </div>
                </div>
                <div class="ds-tx">
                    <div class="ds-tx-icon" style="background:var(--ds-danger-bg);color:var(--ds-danger);">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                    </div>
                    <div class="ds-tx-info">
                        <div class="ds-tx-name">Alvara Perito</div>
                        <div class="ds-tx-desc">Pericia proc. 5098765</div>
                    </div>
                    <div class="ds-tx-right">
                        <div class="ds-tx-amount">- R$ 2.800</div>
                        <div class="ds-tx-date">08/04</div>
                    </div>
                </div>
            </div>
            <div class="ds-card-foot">
                <a href="#" style="font-size:.7rem;font-weight:600;color:var(--ds-primary);text-decoration:none;">Ver todas &rarr;</a>
            </div>
        </div>
    </div>

    {{-- ══════ TABLE + GOALS ══════ --}}
    <div class="ds-g-main ds-a ds-a5">
        <div class="ds-card">
            <div class="ds-card-head">
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <h3>Processos Ativos</h3>
                    <span style="font-size:.58rem;font-weight:700;color:var(--ds-text-3);background:var(--ds-surface-3);padding:2px 8px;border-radius:20px;">47</span>
                </div>
                <div style="display:flex;gap:.35rem;">
                    <button class="ds-btn ds-btn--ghost ds-btn--sm">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Filtrar
                    </button>
                    <button class="ds-btn ds-btn--secondary ds-btn--sm">Ver todos</button>
                </div>
            </div>
            <div class="ds-card-body--flush" style="overflow-x:auto;">
                <table class="ds-table">
                    <thead><tr><th>Processo</th><th>Cliente</th><th>Status</th><th>Responsavel</th><th>Atualizado</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><div class="td-main">5012345-67.2025</div><div class="td-sub">Reclamacao Trabalhista</div></td>
                            <td>Silva & Associados Ltda</td>
                            <td><span class="ds-badge ds-badge--success">Em andamento</span></td>
                            <td><div style="display:flex;align-items:center;gap:.4rem;"><div class="ds-av ds-av--navy">RM</div><span style="font-size:.75rem;">Rafael M.</span></div></td>
                            <td class="td-muted">Hoje, 14:30</td>
                        </tr>
                        <tr>
                            <td><div class="td-main">5098765-43.2024</div><div class="td-sub">Indenizacao por Danos</div></td>
                            <td>Joao Pedro Oliveira</td>
                            <td><span class="ds-badge ds-badge--warning">Aguardando</span></td>
                            <td><div style="display:flex;align-items:center;gap:.4rem;"><div class="ds-av ds-av--primary">LC</div><span style="font-size:.75rem;">Leticia C.</span></div></td>
                            <td class="td-muted">Ontem, 09:15</td>
                        </tr>
                        <tr>
                            <td><div class="td-main">5034567-89.2025</div><div class="td-sub">Revisao Contratual</div></td>
                            <td>Construtora Beira Mar</td>
                            <td><span class="ds-badge ds-badge--info">Peticao</span></td>
                            <td><div style="display:flex;align-items:center;gap:.4rem;"><div class="ds-av ds-av--light">AP</div><span style="font-size:.75rem;">Ana P.</span></div></td>
                            <td class="td-muted">12/04</td>
                        </tr>
                        <tr>
                            <td><div class="td-main">5011223-44.2025</div><div class="td-sub">Execucao Fiscal</div></td>
                            <td>Marina Santos</td>
                            <td><span class="ds-badge ds-badge--danger">Prazo hoje</span></td>
                            <td><div style="display:flex;align-items:center;gap:.4rem;"><div class="ds-av ds-av--navy">RM</div><span style="font-size:.75rem;">Rafael M.</span></div></td>
                            <td class="td-muted">Hoje, 08:00</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ds-card ds-card--success">
            <div class="ds-card-head">
                <h3>Metas — Abril</h3>
                <div class="ds-tabs">
                    <button class="ds-tab active">Mensal</button>
                    <button class="ds-tab">Trimestral</button>
                </div>
            </div>
            <div class="ds-card-body">
                <div class="ds-goal">
                    <div class="ds-goal-head"><span class="ds-goal-name">Receita</span><span class="ds-goal-nums">R$ 84k / R$ 75k</span></div>
                    <div class="ds-progress"><div class="ds-progress-fill ds-progress-fill--success" style="width:100%;animation-delay:.3s"></div></div>
                </div>
                <div class="ds-goal">
                    <div class="ds-goal-head"><span class="ds-goal-name">Novos Clientes</span><span class="ds-goal-nums">8 / 10</span></div>
                    <div class="ds-progress"><div class="ds-progress-fill ds-progress-fill--primary" style="width:80%;animation-delay:.4s"></div></div>
                </div>
                <div class="ds-goal">
                    <div class="ds-goal-head"><span class="ds-goal-name">Leads Convertidos</span><span class="ds-goal-nums">62%</span></div>
                    <div class="ds-progress"><div class="ds-progress-fill ds-progress-fill--navy" style="width:62%;animation-delay:.5s"></div></div>
                </div>
                <div class="ds-goal">
                    <div class="ds-goal-head"><span class="ds-goal-name">Horas Faturadas</span><span class="ds-goal-nums">142h / 180h</span></div>
                    <div class="ds-progress"><div class="ds-progress-fill ds-progress-fill--primary" style="width:79%;animation-delay:.6s"></div></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════ COMPONENTS ══════ --}}
    <div class="ds-card ds-a ds-a7">
        <div class="ds-card-head">
            <h3>Componentes</h3>
            <span style="font-size:.55rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--ds-text-3);">Design System v7</span>
        </div>
        <div class="ds-card-body">
            <div class="ds-showcase">
                <div class="ds-showcase-label">Botoes</div>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
                    <button class="ds-btn ds-btn--primary">Primario</button>
                    <button class="ds-btn ds-btn--secondary">Secundario</button>
                    <button class="ds-btn ds-btn--ghost">Ghost</button>
                    <button class="ds-btn ds-btn--primary ds-btn--sm">Pequeno</button>
                    <button class="ds-btn ds-btn--secondary ds-btn--sm">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Com icone
                    </button>
                </div>
            </div>
            <div class="ds-showcase">
                <div class="ds-showcase-label">Badges</div>
                <div style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;">
                    <span class="ds-badge ds-badge--success">Ativo</span>
                    <span class="ds-badge ds-badge--warning">Pendente</span>
                    <span class="ds-badge ds-badge--danger">Urgente</span>
                    <span class="ds-badge ds-badge--info">Analise</span>
                    <span class="ds-badge ds-badge--neutral">Arquivado</span>
                </div>
            </div>
            <div class="ds-showcase">
                <div class="ds-showcase-label">Formulario</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;">
                    <div><label class="ds-label">Nome do cliente</label><input type="text" class="ds-input" placeholder="Digite o nome..."></div>
                    <div><label class="ds-label">CPF / CNPJ</label><input type="text" class="ds-input" placeholder="000.000.000-00"></div>
                    <div><label class="ds-label">Area juridica</label><select class="ds-input"><option>Trabalhista</option><option>Civel</option><option>Previdenciario</option></select></div>
                </div>
            </div>
            <div class="ds-showcase">
                <div class="ds-showcase-label">Avatares & Tabs</div>
                <div style="display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap;">
                    <div style="display:flex;gap:.3rem;">
                        <div class="ds-av ds-av--navy">RM</div>
                        <div class="ds-av ds-av--primary">LC</div>
                        <div class="ds-av ds-av--light">AP</div>
                        <div class="ds-av ds-av--navy">JS</div>
                    </div>
                    <div class="ds-tabs">
                        <button class="ds-tab active">Ativo</button>
                        <button class="ds-tab">Segundo</button>
                        <button class="ds-tab">Terceiro</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
</div>
@endsection
