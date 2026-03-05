@extends('layouts.app')

@section('title', 'Painel CRM')



@section('content')
<style>
    .painel-header {
        background: linear-gradient(135deg, #1B334A 0%, #385776 50%, #4A7A9B 100%);
        border-radius: 16px;
        padding: 32px;
        color: white;
        margin-bottom: 28px;
        position: relative;
        overflow: hidden;
    }
    .painel-header::before {
        content: '';
        position: absolute;
        top: -40px; right: -40px;
        width: 200px; height: 200px;
        background: rgba(255,255,255,0.04);
        border-radius: 50%;
    }
    .painel-header::after {
        content: '';
        position: absolute;
        bottom: -60px; left: 30%;
        width: 300px; height: 300px;
        background: rgba(255,255,255,0.02);
        border-radius: 50%;
    }
    .painel-header h1 { font-size: 1.75rem; font-weight: 700; letter-spacing: -0.02em; margin: 0; color: #ffffff; }
    .painel-header p { opacity: 0.7; margin: 6px 0 0; font-size: 0.9rem; color: #ffffff; }

    /* ── Mega Cards ── */
    .mega-card {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 1px 4px rgba(27,51,74,0.06), 0 8px 24px rgba(27,51,74,0.04);
        padding: 28px;
        transition: box-shadow 0.2s ease, transform 0.15s ease;
        border: 1px solid rgba(27,51,74,0.06);
    }
    .mega-card:hover {
        box-shadow: 0 2px 8px rgba(27,51,74,0.1), 0 12px 32px rgba(27,51,74,0.07);
        transform: translateY(-1px);
    }
    .mega-card-title {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #6B7A8D;
        margin-bottom: 12px;
    }
    .mega-number {
        font-size: 2.5rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        line-height: 1;
        color: #1B334A;
    }
    .mega-number.accent { color: #385776; }
    .mega-number.success { color: #16A34A; }
    .mega-number.danger { color: #DC2626; }
    .mega-number.warning { color: #D97706; }
    .mega-sublabel {
        font-size: 0.82rem;
        color: #8B95A5;
        margin-top: 6px;
    }

    /* ── Lifecycle pills ── */
    .lc-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
    .lc-pill {
        display: flex; align-items: center; gap: 8px;
        padding: 8px 14px;
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 600;
        background: #F1F5F9;
        color: #475569;
        border: 1px solid transparent;
    }
    .lc-pill.ativo { background: #DCFCE7; color: #166534; border-color: #BBF7D0; }
    .lc-pill.onboarding { background: #DBEAFE; color: #1E40AF; border-color: #BFDBFE; }
    .lc-pill.arquivado { background: #F1F5F9; color: #64748B; border-color: #E2E8F0; }
    .lc-pill.adormecido { background: #FEF3C7; color: #92400E; border-color: #FDE68A; }
    .lc-pill .lc-count { font-size: 1.1rem; font-weight: 800; }

    /* ── Health bar ── */
    .health-bar { display: flex; height: 10px; border-radius: 6px; overflow: hidden; margin-top: 14px; gap: 2px; }
    .health-bar .seg { transition: width 0.4s ease; }
    .health-bar .seg.excelente { background: #16A34A; }
    .health-bar .seg.bom { background: #65A30D; }
    .health-bar .seg.atencao { background: #D97706; }
    .health-bar .seg.critico { background: #EA580C; }
    .health-bar .seg.perdido { background: #DC2626; }
    .health-legend { display: flex; gap: 14px; margin-top: 10px; flex-wrap: wrap; }
    .health-legend span { font-size: 0.75rem; color: #64748B; display: flex; align-items: center; gap: 4px; }
    .health-legend span::before { content: ''; width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .health-legend .l-exc::before { background: #16A34A; }
    .health-legend .l-bom::before { background: #65A30D; }
    .health-legend .l-ate::before { background: #D97706; }
    .health-legend .l-cri::before { background: #EA580C; }
    .health-legend .l-per::before { background: #DC2626; }

    /* ── Forecast funnel ── */
    .funnel-stage {
        display: flex; align-items: center; gap: 14px;
        padding: 14px 0;
        border-bottom: 1px solid #F1F5F9;
    }
    .funnel-stage:last-child { border-bottom: none; }
    .funnel-bar-wrap { flex: 1; height: 28px; background: #F1F5F9; border-radius: 8px; overflow: hidden; position: relative; }
    .funnel-bar {
        height: 100%;
        border-radius: 8px;
        background: linear-gradient(90deg, #385776, #4A7A9B);
        transition: width 0.5s ease;
        min-width: 4px;
    }
    .funnel-bar-wrap .bar-label {
        position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
        font-size: 0.75rem; font-weight: 700; color: #1B334A;
    }
    .funnel-name { width: 110px; font-size: 0.82rem; font-weight: 600; color: #475569; flex-shrink: 0; }
    .funnel-meta { width: 140px; text-align: right; font-size: 0.78rem; color: #8B95A5; flex-shrink: 0; }

    /* ── Atividade grid ── */
    .atividade-user {
        display: flex; align-items: center; gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid #F8FAFC;
    }
    .atividade-user:last-child { border-bottom: none; }
    .atividade-avatar {
        width: 36px; height: 36px; border-radius: 10px;
        background: linear-gradient(135deg, #1B334A, #385776);
        color: white; font-weight: 700; font-size: 0.82rem;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .atividade-badges { display: flex; gap: 6px; flex-wrap: wrap; }
    .atividade-badge {
        font-size: 0.72rem; font-weight: 600;
        padding: 3px 8px; border-radius: 6px;
        background: #EFF6FF; color: #1E40AF;
    }
    .atividade-badge.meeting { background: #FEF3C7; color: #92400E; }
    .atividade-badge.task { background: #DCFCE7; color: #166534; }
    .atividade-badge.note { background: #F3E8FF; color: #7C3AED; }
    .atividade-badge.call { background: #FFE4E6; color: #BE123C; }

    /* ── Alertas ── */
    .alerta-item {
        display: flex; align-items: flex-start; gap: 14px;
        padding: 14px 16px;
        border-radius: 10px;
        margin-bottom: 8px;
        transition: background 0.15s;
        text-decoration: none;
        color: inherit;
    }
    .alerta-item:hover { background: #F8FAFC; }
    .alerta-icon {
        width: 36px; height: 36px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; font-size: 1rem;
    }
    .alerta-icon.danger { background: #FEE2E2; color: #DC2626; }
    .alerta-icon.warning { background: #FEF3C7; color: #D97706; }
    .alerta-titulo { font-size: 0.85rem; font-weight: 600; color: #1E293B; line-height: 1.3; }
    .alerta-detalhe { font-size: 0.75rem; color: #8B95A5; margin-top: 2px; }

    /* ── Section titles ── */
    .section-title {
        font-size: 1rem;
        font-weight: 700;
        color: #1B334A;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .section-title .section-icon {
        width: 28px; height: 28px; border-radius: 8px;
        background: linear-gradient(135deg, #1B334A, #385776);
        color: white; display: flex; align-items: center; justify-content: center;
        font-size: 0.82rem;
    }
    .section-title .section-count {
        margin-left: auto;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 20px;
        background: #F1F5F9;
        color: #64748B;
    }

    /* ── Alert summary badges ── */
    .alert-summary { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
    .alert-summary-badge {
        font-size: 0.72rem; font-weight: 700;
        padding: 5px 12px; border-radius: 20px;
        display: flex; align-items: center; gap: 5px;
        cursor: default;
    }
    .alert-summary-badge.danger { background: #FEE2E2; color: #DC2626; }
    .alert-summary-badge.warning { background: #FEF3C7; color: #D97706; }
    .alert-summary-badge.info { background: #DBEAFE; color: #1E40AF; }

    /* ── Empty state ── */
    .empty-state {
        text-align: center; padding: 40px 20px;
        color: #94A3B8; font-size: 0.9rem;
    }
    .empty-state svg { width: 48px; height: 48px; opacity: 0.3; margin: 0 auto 12px; }

    /* ── AI Insights ── */
    .ai-digest-card {
        background: linear-gradient(135deg, #0F172A 0%, #1E293B 50%, #334155 100%);
        border-radius: 14px;
        padding: 28px;
        color: #E2E8F0;
        position: relative;
        overflow: hidden;
    }
    .ai-digest-card::before {
        content: '';
        position: absolute;
        top: -30px; right: -30px;
        width: 160px; height: 160px;
        background: rgba(99,102,241,0.08);
        border-radius: 50%;
    }
    .ai-digest-card .ai-badge {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.1em; color: #818CF8;
        margin-bottom: 12px;
    }
    .ai-digest-card .ai-title {
        font-size: 1.15rem; font-weight: 700; color: #F8FAFC;
        margin-bottom: 14px; line-height: 1.3;
    }
    .ai-digest-card .ai-body {
        font-size: 0.85rem; line-height: 1.7; color: #CBD5E1;
    }
    .ai-digest-card .ai-body p { margin-bottom: 10px; }
    .ai-digest-card .ai-actions {
        margin-top: 18px; padding-top: 16px;
        border-top: 1px solid rgba(255,255,255,0.08);
    }
    .ai-digest-card .ai-action-item {
        display: flex; align-items: flex-start; gap: 8px;
        font-size: 0.82rem; color: #A5B4FC;
        margin-bottom: 8px;
    }
    .ai-digest-card .ai-action-item::before {
        content: '→'; color: #6366F1; font-weight: 700; flex-shrink: 0;
    }
    .ai-digest-card .ai-meta {
        margin-top: 16px; font-size: 0.7rem; color: #64748B;
    }
    .ai-empty {
        text-align: center; padding: 40px;
        background: #F8FAFC; border-radius: 14px;
        border: 2px dashed #E2E8F0;
    }
    .ai-empty-icon { font-size: 2rem; margin-bottom: 10px; }
    .ai-empty p { color: #94A3B8; font-size: 0.85rem; margin: 0; }
    .btn-generate-digest {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 10px 20px; border-radius: 10px;
        background: linear-gradient(135deg, #4F46E5, #6366F1);
        color: white; font-size: 0.82rem; font-weight: 600;
        border: none; cursor: pointer; margin-top: 14px;
        transition: opacity 0.2s;
    }
    .btn-generate-digest:hover { opacity: 0.9; }
    .btn-generate-digest:disabled { opacity: 0.5; cursor: not-allowed; }

</style>
<div style="max-width: 1280px; margin: 0 auto; padding: 24px;">

    {{-- ══════ HEADER ══════ --}}
    <div class="painel-header">
        <h1>Painel CRM</h1>
        <p>Visão gerencial da carteira, pipeline e atividade comercial</p>
    </div>

    {{-- ══════ SEÇÃO 1 — KPIs DE CARTEIRA ══════ --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 28px;">

        {{-- Card: Total de Contas --}}
        <div class="mega-card">
            <div class="mega-card-title">Contas na Base</div>
            <div class="mega-number">{{ number_format($kpis['total_contas']) }}</div>
            <div class="lc-row">
                @php
                    $lcLabels = ['ativo' => 'Ativo', 'onboarding' => 'Onboarding', 'arquivado' => 'Arquivado', 'adormecido' => 'Adormecido'];
                @endphp
                @foreach($lcLabels as $key => $label)
                    <div class="lc-pill {{ $key }}">
                        <span class="lc-count">{{ $kpis['por_lifecycle'][$key] ?? 0 }}</span>
                        {{ $label }}
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Card: Sem Responsável --}}
        <div class="mega-card">
            <div class="mega-card-title">Contas sem Responsável</div>
            <div class="mega-number {{ $kpis['sem_owner'] > 100 ? 'danger' : ($kpis['sem_owner'] > 0 ? 'warning' : 'success') }}">
                {{ number_format($kpis['sem_owner']) }}
            </div>
            <div class="mega-sublabel">
                @if($kpis['sem_owner'] > 0)
                    {{ round($kpis['sem_owner'] / max($kpis['total_contas'], 1) * 100) }}% da base sem owner atribuído
                @else
                    Todas as contas possuem responsável
                @endif
            </div>
        </div>

        {{-- Card: Health Score Médio --}}
        <div class="mega-card">
            <div class="mega-card-title">Health Score Médio</div>
            @php
                $hsMedia = $kpis['health_score']->media ?? 0;
                $hsTotal = $kpis['health_score']->total_com_score ?? 0;
                $hsClass = $hsMedia >= 80 ? 'success' : ($hsMedia >= 60 ? 'accent' : ($hsMedia >= 40 ? 'warning' : 'danger'));
            @endphp
            <div class="mega-number {{ $hsClass }}">{{ number_format($hsMedia, 0) }}</div>
            <div class="mega-sublabel">Baseado em {{ number_format($hsTotal) }} contas com score</div>

            @if($hsTotal > 0)
                @php
                    $exc = $kpis['health_score']->excelente ?? 0;
                    $bom = $kpis['health_score']->bom ?? 0;
                    $ate = $kpis['health_score']->atencao ?? 0;
                    $cri = $kpis['health_score']->critico ?? 0;
                    $per = $kpis['health_score']->perdido ?? 0;
                @endphp
                <div class="health-bar">
                    <div class="seg excelente" style="width: {{ $exc / $hsTotal * 100 }}%"></div>
                    <div class="seg bom" style="width: {{ $bom / $hsTotal * 100 }}%"></div>
                    <div class="seg atencao" style="width: {{ $ate / $hsTotal * 100 }}%"></div>
                    <div class="seg critico" style="width: {{ $cri / $hsTotal * 100 }}%"></div>
                    <div class="seg perdido" style="width: {{ $per / $hsTotal * 100 }}%"></div>
                </div>
                <div class="health-legend">
                    <span class="l-exc">{{ $exc }} Excelente</span>
                    <span class="l-bom">{{ $bom }} Bom</span>
                    <span class="l-ate">{{ $ate }} Atenção</span>
                    <span class="l-cri">{{ $cri }} Crítico</span>
                    <span class="l-per">{{ $per }} Perdido</span>
                </div>
            @endif
        </div>

        {{-- Card: Forecast --}}
        <div class="mega-card">
            <div class="mega-card-title">Forecast Pipeline</div>
            <div class="mega-number accent">R$ {{ number_format($pipeline['forecast_total'], 0, ',', '.') }}</div>
            <div class="mega-sublabel">{{ $pipeline['total_abertas'] }} oportunidade(s) aberta(s), receita ponderada por probabilidade</div>
        </div>
    </div>

    {{-- ══════ SEÇÃO 2 — PIPELINE POR STAGE ══════ --}}
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px;">

        <div class="mega-card">
            <div class="section-title">
                <span class="section-icon">📊</span>
                Pipeline por Stage
            </div>

            @if(count($pipeline['por_stage']) > 0)
                @php $maxValor = max(array_column($pipeline['por_stage'], 'valor_ponderado')) ?: 1; @endphp
                @foreach($pipeline['por_stage'] as $stage)
                    <div class="funnel-stage">
                        <div class="funnel-name">{{ $stage['stage'] }}</div>
                        <div class="funnel-bar-wrap">
                            <div class="funnel-bar" style="width: {{ ($stage['valor_ponderado'] / $maxValor) * 100 }}%"></div>
                            <span class="bar-label">R$ {{ number_format($stage['valor_ponderado'], 0, ',', '.') }}</span>
                        </div>
                        <div class="funnel-meta">
                            {{ $stage['quantidade'] }}x · {{ $stage['probabilidade'] * 100 }}%
                        </div>
                    </div>
                @endforeach
            @else
                <div class="empty-state">Nenhuma oportunidade aberta no pipeline</div>
            @endif
        </div>

        <div class="mega-card">
            <div class="section-title">
                <span class="section-icon">📈</span>
                Histórico de Conversão
            </div>

            @php
                $won = $pipeline['historico']->total_won ?? 0;
                $lost = $pipeline['historico']->total_lost ?? 0;
                $totalHist = $won + $lost;
                $taxaConv = $totalHist > 0 ? round($won / $totalHist * 100) : 0;
            @endphp

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-top: 8px;">
                <div>
                    <div class="mega-number success" style="font-size: 2rem;">{{ $won }}</div>
                    <div class="mega-sublabel">Ganhas</div>
                    <div style="font-size: 0.78rem; color: #16A34A; font-weight: 600; margin-top: 2px;">
                        R$ {{ number_format($pipeline['historico']->receita_won ?? 0, 0, ',', '.') }}
                    </div>
                </div>
                <div>
                    <div class="mega-number danger" style="font-size: 2rem;">{{ $lost }}</div>
                    <div class="mega-sublabel">Perdidas</div>
                    <div style="font-size: 0.78rem; color: #DC2626; font-weight: 600; margin-top: 2px;">
                        R$ {{ number_format($pipeline['historico']->valor_lost ?? 0, 0, ',', '.') }}
                    </div>
                </div>
                <div>
                    <div class="mega-number accent" style="font-size: 2rem;">{{ $taxaConv }}%</div>
                    <div class="mega-sublabel">Taxa de conversão</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════ SEÇÃO 3 — ATIVIDADE DA SEMANA + ALERTAS ══════ --}}
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px;">

        {{-- Atividade da Semana --}}
        <div class="mega-card">
            <div class="section-title">
                <span class="section-icon">🗓</span>
                Atividade da Semana
                <span class="section-count">{{ $atividade['periodo'] }}</span>
            </div>

            @if(count($atividade['por_usuario']) > 0)
                @foreach($atividade['por_usuario'] as $user)
                    <div class="atividade-user">
                        <div class="atividade-avatar">
                            {{ strtoupper(substr($user['user_name'], 0, 2)) }}
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 0.85rem; font-weight: 600; color: #1E293B;">
                                {{ $user['user_name'] }}
                            </div>
                            <div class="atividade-badges">
                                @foreach($user['tipos'] as $tipo => $qtd)
                                    <span class="atividade-badge {{ $tipo }}">{{ $tipo }} · {{ $qtd }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div style="font-size: 1.2rem; font-weight: 800; color: #1B334A;">
                            {{ $user['total'] }}
                        </div>
                    </div>
                @endforeach

                <div style="margin-top: 14px; padding-top: 14px; border-top: 2px solid #F1F5F9; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.82rem; font-weight: 600; color: #64748B;">Total da semana</span>
                    <span style="font-size: 1.4rem; font-weight: 800; color: #1B334A;">{{ $atividade['total_semana'] }}</span>
                </div>
            @else
                <div class="empty-state">Nenhuma atividade registrada esta semana</div>
            @endif
        </div>

        {{-- Alertas Acionáveis --}}
        <div class="mega-card">
            <div class="section-title">
                <span class="section-icon">⚠</span>
                Alertas Acionáveis
                <span class="section-count">{{ $alertas['total_alertas'] }} alerta(s)</span>
            </div>

            @if($alertas['total_alertas'] > 0)
                <div class="alert-summary">
                    @if($alertas['por_tipo']['health_score'] > 0)
                        <span class="alert-summary-badge danger">❤ {{ $alertas['por_tipo']['health_score'] }} Health Crítico</span>
                    @endif
                    @if($alertas['por_tipo']['oportunidade_parada'] > 0)
                        <span class="alert-summary-badge warning">⏱ {{ $alertas['por_tipo']['oportunidade_parada'] }} Op. Parada</span>
                    @endif
                    @if($alertas['por_tipo']['inadimplencia'] > 0)
                        <span class="alert-summary-badge danger">💰 {{ $alertas['por_tipo']['inadimplencia'] }} Inadimplente</span>
                    @endif
                    @if($alertas['por_tipo']['lead_quente'] > 0)
                        <span class="alert-summary-badge warning">🔥 {{ $alertas['por_tipo']['lead_quente'] }} Lead Quente</span>
                    @endif
                </div>

                <div style="max-height: 420px; overflow-y: auto;">
                    @foreach($alertas['alertas'] as $alerta)
                        <a href="{{ $alerta['link'] }}" class="alerta-item">
                            <div class="alerta-icon {{ $alerta['severidade'] }}">
                                @if($alerta['icone'] === 'heart-pulse') ❤ @endif
                                @if($alerta['icone'] === 'clock') ⏱ @endif
                                @if($alerta['icone'] === 'banknotes') 💰 @endif
                                @if($alerta['icone'] === 'flame') 🔥 @endif
                            </div>
                            <div>
                                <div class="alerta-titulo">{{ $alerta['titulo'] }}</div>
                                <div class="alerta-detalhe">{{ $alerta['detalhe'] }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="empty-state">
                    <div style="font-size: 2rem; margin-bottom: 8px;">✅</div>
                    Nenhum alerta ativo — tudo sob controle
                </div>
            @endif
        </div>
    </div>

    {{-- ══════ SEÇÃO EXTRA — DISTRIBUIÇÃO POR RESPONSÁVEL ══════ --}}
    <div class="mega-card" style="margin-bottom: 28px;">
        <div class="section-title">
            <span class="section-icon">👥</span>
            Distribuição por Responsável
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            @foreach($kpis['por_owner'] as $owner)
                @php
                    $isOrfao = $owner->owner_name === 'Sem responsável';
                @endphp
                <div style="padding: 16px; border-radius: 10px; background: {{ $isOrfao ? '#FEF2F2' : '#F8FAFC' }}; border: 1px solid {{ $isOrfao ? '#FECACA' : '#E2E8F0' }};">
                    <div style="font-size: 1.5rem; font-weight: 800; color: {{ $isOrfao ? '#DC2626' : '#1B334A' }};">
                        {{ $owner->total }}
                    </div>
                    <div style="font-size: 0.82rem; font-weight: 600; color: {{ $isOrfao ? '#991B1B' : '#475569' }}; margin-top: 2px;">
                        {{ $owner->owner_name }}
                    </div>
                    <div style="font-size: 0.72rem; color: #94A3B8; margin-top: 2px;">
                        {{ round($owner->total / max($kpis['total_contas'], 1) * 100) }}% da base
                    </div>
                </div>
            @endforeach
        </div>
    </div>

</div>

    {{-- ══════ SEÇÃO 5 — INTELIGÊNCIA ARTIFICIAL ══════ --}}
    <div class="mega-card" style="margin-bottom: 28px;">
        <div class="section-title">
            <span class="section-icon" style="background: linear-gradient(135deg, #4F46E5, #6366F1);">🤖</span>
            Inteligência Artificial CRM
            <span class="section-count">gpt-5-mini</span>
        </div>

        @if(isset($aiDigest) && $aiDigest)
            <div class="ai-digest-card">
                <div class="ai-badge">✦ Weekly Digest — Prioridade {{ $aiDigest->priority }}</div>
                <div class="ai-title">{{ $aiDigest->titulo }}</div>
                <div class="ai-body">
                    @foreach(explode("\n", $aiDigest->insight_text) as $paragrafo)
                        @if(trim($paragrafo))
                            <p>{{ trim($paragrafo) }}</p>
                        @endif
                    @endforeach
                </div>

                @if($aiDigest->action_suggested)
                    <div class="ai-actions">
                        @foreach(explode("\n", $aiDigest->action_suggested) as $action)
                            @if(trim($action))
                                <div class="ai-action-item">{{ trim($action) }}</div>
                            @endif
                        @endforeach
                    </div>
                @endif

                <div class="ai-meta">
                    Gerado em {{ $aiDigest->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                </div>
            </div>

            <div style="margin-top: 14px; text-align: right;">
                <button class="btn-generate-digest" onclick="gerarDigest()" id="btn-digest">
                    ↻ Gerar Novo Digest
                </button>
            </div>
        @else
            <div class="ai-empty">
                <div class="ai-empty-icon">🤖</div>
                <p>Nenhum digest gerado ainda.</p>
                <button class="btn-generate-digest" onclick="gerarDigest()" id="btn-digest">
                    ✦ Gerar Primeiro Digest Semanal
                </button>
            </div>
        @endif
    </div>

    <script>
    function gerarDigest() {
        const btn = document.getElementById('btn-digest');
        btn.disabled = true;
        btn.textContent = '⏳ Gerando com IA...';

        fetch('{{ url("/crm/painel/generate-digest") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Erro: ' + (data.error || 'Falha ao gerar digest'));
                btn.disabled = false;
                btn.textContent = '↻ Tentar novamente';
            }
        })
        .catch(err => {
            alert('Erro de conexão: ' + err.message);
            btn.disabled = false;
            btn.textContent = '↻ Tentar novamente';
        });
    }
    </script>


@endsection
