@extends('layouts.app')
@section('title', 'CRM - Painel')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/crm-modern.css') }}">
<style>
.crm-dash { padding: 1.5rem; }
.section-title {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: var(--brand-text-muted);
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.chart-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid var(--brand-border);
    padding: 1.25rem;
    height: 100%;
}
.chart-card-title {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--brand-navy);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.chart-canvas-wrap { position: relative; }
</style>
@endpush

@section('content')
<div class="crm-dash">

    {{-- ══════════════ HERO EDITORIAL ══════════════ --}}
    <section class="crm-hero">
        <div>
            <div class="crm-hero-eyebrow">{{ now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM') }}</div>
            <h1>{{ $isRestricted ? 'Meu' : 'CRM — Painel' }} <em>{{ $isRestricted ? 'CRM' : 'Geral' }}</em>.</h1>
            <p class="crm-hero-sub">{{ $isRestricted ? 'Sua carteira, agenda e alertas.' : 'Visão consolidada das operações comerciais e relacionamento.' }}</p>
        </div>
        <div class="crm-hero-right" style="display:flex;gap:10px;align-items:flex-end;">
            <a href="{{ route('crm.carteira') }}" class="crm-section-head-action">Carteira →</a>
            <a href="{{ route('crm.pipeline') }}" class="crm-section-head-action">Pipeline →</a>
        </div>
    </section>

    {{-- ══════════════ KPIs ══════════════ --}}
    <div class="crm-section-head">
        <div>
            <div class="crm-section-head-label">Bloco I</div>
            <h2>Indicadores <em>chave</em>.</h2>
        </div>
        <div class="crm-section-head-line"></div>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
        <div class="crm-kpi-card" style="--kpi-accent:#059669">
            <p class="crm-kpi-label">Clientes Ativos</p>
            <p class="crm-kpi-value value-green">{{ number_format($kpis['active_clients']) }}</p>
            <span class="crm-kpi-icon">👥</span>
        </div>
        <div class="crm-kpi-card" style="--kpi-accent:var(--brand-navy)">
            <p class="crm-kpi-label">Base Total</p>
            <p class="crm-kpi-value">{{ number_format($kpis['total_clients']) }}</p>
            <span class="crm-kpi-icon">🏢</span>
        </div>
        <div class="crm-kpi-card" style="--kpi-accent:#3b82f6">
            <p class="crm-kpi-label">Opps Abertas</p>
            <p class="crm-kpi-value value-blue">{{ $kpis['open_opps'] }}</p>
            <span class="crm-kpi-icon">🎯</span>
        </div>
        <div class="crm-kpi-card" style="--kpi-accent:var(--brand-blue)">
            <p class="crm-kpi-label">Pipeline</p>
            <p class="crm-kpi-value" style="font-size:1.1rem">R$&nbsp;{{ number_format($kpis['pipeline_value'], 0, ',', '.') }}</p>
            <span class="crm-kpi-icon">💰</span>
        </div>
        <div class="crm-kpi-card" style="--kpi-accent:{{ $kpis['win_rate'] >= 50 ? '#059669' : '#d97706' }}">
            <p class="crm-kpi-label">Win Rate 3m</p>
            <p class="crm-kpi-value {{ $kpis['win_rate'] >= 50 ? 'value-green' : 'value-amber' }}">{{ $kpis['win_rate'] }}%</p>
            <span class="crm-kpi-icon">🏆</span>
        </div>
        <div class="crm-kpi-card" style="--kpi-accent:#059669">
            <p class="crm-kpi-label">Ganho Mês</p>
            <p class="crm-kpi-value value-green" style="font-size:1.1rem">R$&nbsp;{{ number_format($kpis['won_month'], 0, ',', '.') }}</p>
            <span class="crm-kpi-icon">✅</span>
        </div>
        <div class="crm-kpi-card" style="--kpi-accent:{{ $kpis['sem_contato_30d'] > 0 ? '#dc2626' : '#059669' }}">
            <p class="crm-kpi-label">Sem Contato 30d</p>
            <p class="crm-kpi-value {{ $kpis['sem_contato_30d'] > 0 ? 'value-red' : 'value-green' }}">{{ $kpis['sem_contato_30d'] }}</p>
            <span class="crm-kpi-icon">⏰</span>
        </div>
    </div>

    {{-- ══════════════ GRÁFICOS ══════════════ --}}
    <div class="crm-section-head" style="margin-top:36px;">
        <div>
            <div class="crm-section-head-label">Bloco II</div>
            <h2>Análises <em>visuais</em>.</h2>
        </div>
        <div class="crm-section-head-line"></div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">

        {{-- Gráfico 1: Pipeline por Estágio --}}
        <div class="chart-card">
            <div class="chart-card-title">
                <span>Pipeline por Estágio</span>
                <span style="font-size:0.7rem;color:var(--brand-text-muted);font-weight:400">{{ $kpis['open_opps'] }} oportunidades</span>
            </div>
            @if($charts['pipeline_stages']->isEmpty())
                <div class="crm-empty"><div class="crm-empty-icon">📊</div><p class="crm-empty-text">Sem dados</p></div>
            @else
            <div class="chart-canvas-wrap" style="height:200px">
                <canvas id="chartPipeline"></canvas>
            </div>
            {{-- Mini legenda --}}
            <div class="mt-3 space-y-1">
                @foreach($charts['pipeline_stages'] as $s)
                <div style="display:flex;align-items:center;justify-content:space-between;font-size:0.72rem">
                    <span style="display:flex;align-items:center;gap:0.4rem">
                        <span style="width:8px;height:8px;border-radius:50%;background:{{ $s['color'] }};display:inline-block;flex-shrink:0"></span>
                        {{ $s['name'] }}
                    </span>
                    <span style="color:var(--brand-text-muted)">{{ $s['count'] }} · R$ {{ number_format($s['value'], 0, ',', '.') }}</span>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Gráfico 2: Tendência Mensal --}}
        <div class="chart-card">
            <div class="chart-card-title">
                <span>Tendência — Ganhos vs Perdidos</span>
                <span style="font-size:0.7rem;color:var(--brand-text-muted);font-weight:400">últimos 6 meses</span>
            </div>
            <div class="chart-canvas-wrap" style="height:220px">
                <canvas id="chartTrend"></canvas>
            </div>
        </div>

        {{-- Gráfico 3: Atividades + Lifecycle --}}
        <div class="chart-card">
            <div class="chart-card-title">
                <span>Atividades por Tipo</span>
                <span style="font-size:0.7rem;color:var(--brand-text-muted);font-weight:400">últimos 30 dias</span>
            </div>
            @php
                $actLabels = ['call'=>'📞 Ligação','meeting'=>'🤝 Reunião','whatsapp'=>'💬 WhatsApp','task'=>'✅ Tarefa','email'=>'✉️ Email'];
                $actColors = ['call'=>'#3b82f6','meeting'=>'#8b5cf6','whatsapp'=>'#22c55e','task'=>'#f59e0b','email'=>'#ef4444'];
                $actTotal = $charts['activities']->sum();
            @endphp
            @if($actTotal == 0)
                <div class="crm-empty"><div class="crm-empty-icon">📋</div><p class="crm-empty-text">Sem atividades no período</p></div>
            @else
            <div class="chart-canvas-wrap" style="height:160px">
                <canvas id="chartAct"></canvas>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-1">
                @foreach($charts['activities'] as $type => $count)
                <div style="display:flex;align-items:center;gap:0.35rem;font-size:0.7rem">
                    <span style="width:8px;height:8px;border-radius:2px;background:{{ $actColors[$type] ?? '#94a3b8' }};display:inline-block;flex-shrink:0"></span>
                    <span>{{ $actLabels[$type] ?? $type }}</span>
                    <span style="color:var(--brand-text-muted);margin-left:auto">{{ $count }}</span>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- ══════════════ AGENDA + SIDEBAR ══════════════ --}}
    <div class="crm-section-head" style="margin-top:36px;">
        <div>
            <div class="crm-section-head-label">Bloco III</div>
            <h2>Operacional <em>do dia</em>.</h2>
        </div>
        <div class="crm-section-head-line"></div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Coluna Esquerda (2/3): Agenda + Opps --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Agenda --}}
            <div class="crm-card">
                <div class="crm-card-header">
                    <h2>📅 Agenda do Dia</h2>
                    @php $totalAgenda = $agenda->vencidas->count() + $agenda->hoje->count() + $agenda->amanha->count(); @endphp
                    @if($totalAgenda > 0)
                    <span style="font-size:0.72rem;color:var(--brand-text-muted)">{{ $totalAgenda }} atividade{{ $totalAgenda > 1 ? 's' : '' }}</span>
                    @endif
                </div>
                <div class="crm-card-body space-y-3">
                    @if($agenda->vencidas->isNotEmpty())
                    <div>
                        <div class="mb-1"><span class="agenda-section-label agenda-section-overdue">🔴 Vencidas · {{ $agenda->vencidas->count() }}</span></div>
                        <div class="space-y-1">
                            @foreach($agenda->vencidas as $act)
                            <a href="{{ $act->account ? route('crm.accounts.show', $act->account_id).'#activity-'.$act->id : '#' }}" class="agenda-item agenda-item-overdue">
                                <span class="agenda-item-icon">{{ match($act->type) { 'call'=>'📞','meeting'=>'🤝','whatsapp'=>'💬','task'=>'✅','email'=>'✉️',default=>'📝' } }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="agenda-item-title">{{ $act->title }}</div>
                                    @if($act->account)<div style="font-size:0.7rem;color:#b91c1c">{{ $act->account->name }}</div>@endif
                                </div>
                                <span class="agenda-item-meta" style="color:#dc2626">{{ $act->due_at?->diffForHumans(short: true) }}</span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if($agenda->hoje->isNotEmpty())
                    <div>
                        <div class="mb-1"><span class="agenda-section-label agenda-section-today">📌 Hoje · {{ $agenda->hoje->count() }}</span></div>
                        <div class="space-y-1">
                            @foreach($agenda->hoje as $act)
                            <a href="{{ $act->account ? route('crm.accounts.show', $act->account_id).'#activity-'.$act->id : '#' }}" class="agenda-item agenda-item-today">
                                <span class="agenda-item-icon">{{ match($act->type) { 'call'=>'📞','meeting'=>'🤝','whatsapp'=>'💬','task'=>'✅','email'=>'✉️',default=>'📝' } }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="agenda-item-title">{{ $act->title }}</div>
                                    @if($act->account)<div style="font-size:0.7rem;color:var(--brand-text-muted)">{{ $act->account->name }}</div>@endif
                                </div>
                                @if($act->due_at)<span class="agenda-item-meta">{{ $act->due_at->format('H:i') }}</span>@endif
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if($agenda->amanha->isNotEmpty())
                    <div>
                        <div class="mb-1"><span class="agenda-section-label agenda-section-tomorrow">Amanhã · {{ $agenda->amanha->count() }}</span></div>
                        <div class="space-y-1">
                            @foreach($agenda->amanha as $act)
                            <a href="{{ $act->account ? route('crm.accounts.show', $act->account_id).'#activity-'.$act->id : '#' }}" class="agenda-item agenda-item-tomorrow">
                                <span class="agenda-item-icon">{{ match($act->type) { 'call'=>'📞','meeting'=>'🤝','whatsapp'=>'💬','task'=>'✅','email'=>'✉️',default=>'📝' } }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="agenda-item-title">{{ $act->title }}</div>
                                    @if($act->account)<div style="font-size:0.7rem;color:var(--brand-text-muted)">{{ $act->account->name }}</div>@endif
                                </div>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if($totalAgenda === 0)
                    <div class="crm-empty">
                        <div class="crm-empty-icon">📅</div>
                        <p class="crm-empty-text">Nenhuma tarefa agendada.</p>
                        <p style="font-size:0.7rem;color:#cbd5e1;margin-top:0.2rem">Registre atividades nos accounts para alimentar a agenda</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Oportunidades Abertas --}}
            <div class="crm-card">
                <div class="crm-card-header">
                    <h2>🎯 Oportunidades Abertas</h2>
                    <a href="{{ route('crm.pipeline') }}" style="font-size:0.75rem;color:var(--brand-blue);font-weight:600;text-decoration:none">Ver pipeline →</a>
                </div>
                <div class="crm-card-body">
                    @if($openOpps->isEmpty())
                    <div class="crm-empty"><div class="crm-empty-icon">🎯</div><p class="crm-empty-text">Nenhuma oportunidade aberta.</p></div>
                    @else
                    <div class="space-y-1">
                        @foreach($openOpps as $opp)
                        <a href="{{ route('crm.opportunities.show', $opp->id) }}" class="opp-item">
                            <div class="min-w-0 flex-1">
                                <p style="font-size:0.82rem;font-weight:600;color:var(--brand-text)" class="truncate">{{ $opp->title }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    @if($opp->stage)<span class="opp-stage-badge" style="background:{{ $opp->stage->color }}20;color:{{ $opp->stage->color }}">{{ $opp->stage->name }}</span>@endif
                                    @if($opp->account)<span style="font-size:0.7rem;color:var(--brand-text-muted)" class="truncate">{{ $opp->account->name }}</span>@endif
                                </div>
                            </div>
                            <div class="text-right ml-3 flex-shrink-0">
                                @if($opp->value_estimated)<p style="font-size:0.82rem;font-weight:700;color:var(--brand-blue)">R$ {{ number_format($opp->value_estimated, 0, ',', '.') }}</p>@endif
                                @if($opp->next_action_at)
                                <p style="font-size:0.7rem;color:{{ $opp->next_action_at->isPast() ? '#dc2626' : 'var(--brand-text-muted)' }}">{{ $opp->next_action_at->format('d/m') }}</p>
                                @endif
                            </div>
                        </a>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Coluna Direita (1/3): Alertas + Clientes + Lifecycle --}}
        <div class="space-y-4">

            {{-- Alertas --}}
            <div class="crm-card">
                <div class="crm-card-header">
                    <h2>🔔 Alertas</h2>
                    @if(!empty($alertas))<span style="background:#fee2e2;color:#dc2626;font-size:0.65rem;font-weight:700;padding:2px 8px;border-radius:10px">{{ count($alertas) }}</span>@endif
                </div>
                <div class="crm-card-body">
                    @if(empty($alertas))
                    <div class="crm-empty" style="padding:1.5rem 1rem">
                        <div class="crm-empty-icon">✅</div>
                        <p class="crm-empty-text" style="color:#059669;font-weight:600">Tudo em dia!</p>
                    </div>
                    @else
                    <div class="space-y-1">
                        @foreach($alertas as $al)
                        <a href="{{ $al['link'] }}" class="alerta-item">
                            <span style="flex-shrink:0">{{ $al['icone'] }}</span>
                            <p style="font-size:0.78rem;color:var(--brand-text)">{{ $al['texto'] }}</p>
                        </a>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            {{-- Clientes por Lifecycle (mini gráfico) --}}
            @if($charts['lifecycle']->isNotEmpty())
            <div class="crm-card">
                <div class="crm-card-header"><h2>🏢 Base por Situação</h2></div>
                <div class="crm-card-body">
                    @php
                        $lcLabels = ['ativo'=>'Ativo','onboarding'=>'Onboarding','adormecido'=>'Adormecido','churned'=>'Churn'];
                        $lcColors = ['ativo'=>'#059669','onboarding'=>'#3b82f6','adormecido'=>'#d97706','churned'=>'#dc2626'];
                        $lcTotal = $charts['lifecycle']->sum();
                    @endphp
                    <div class="space-y-2">
                        @foreach($charts['lifecycle'] as $lc => $cnt)
                        @php $pct = $lcTotal > 0 ? round($cnt / $lcTotal * 100) : 0 @endphp
                        <div>
                            <div style="display:flex;justify-content:space-between;font-size:0.72rem;margin-bottom:3px">
                                <span style="font-weight:600;color:var(--brand-text)">{{ $lcLabels[$lc] ?? $lc }}</span>
                                <span style="color:var(--brand-text-muted)">{{ $cnt }} ({{ $pct }}%)</span>
                            </div>
                            <div style="height:6px;background:#f1f5f9;border-radius:4px;overflow:hidden">
                                <div style="height:100%;width:{{ $pct }}%;background:{{ $lcColors[$lc] ?? '#94a3b8' }};border-radius:4px;transition:width 0.6s ease"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- Últimos Contatos --}}
            <div class="crm-card">
                <div class="crm-card-header">
                    <h2>👥 Últimos Contatos</h2>
                    <a href="{{ route('crm.carteira') }}" style="font-size:0.75rem;color:var(--brand-blue);font-weight:600;text-decoration:none">Ver todos →</a>
                </div>
                <div class="crm-card-body">
                    @if($recentClients->isEmpty())
                    <div class="crm-empty" style="padding:1.5rem 1rem"><p class="crm-empty-text">Nenhum contato recente.</p></div>
                    @else
                    <div class="space-y-1">
                        @foreach($recentClients as $cli)
                        <a href="{{ route('crm.accounts.show', $cli->id) }}" class="contact-item">
                            <div class="contact-avatar">{{ mb_strtoupper(mb_substr($cli->name, 0, 2)) }}</div>
                            <div class="min-w-0 flex-1">
                                <p style="font-size:0.8rem;font-weight:600;color:var(--brand-text)" class="truncate">{{ $cli->name }}</p>
                                <p style="font-size:0.7rem;color:var(--brand-text-muted)">{{ $cli->owner?->name ?? 'Sem resp.' }}</p>
                            </div>
                            <span style="font-size:0.7rem;color:var(--brand-text-muted);flex-shrink:0">{{ $cli->last_touch_at?->diffForHumans(short: true) }}</span>
                        </a>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(!empty($gatesQualidade))
    <div class="mt-6 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between bg-gradient-to-r from-amber-50 to-white">
            <div>
                <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-600" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                    </svg>
                    Qualidade de dados (gates DJ × CRM)
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Divergências entre DataJuri e realidade. Escalado = penalidade PEN-C01 (3 pts, Atendimento).</p>
            </div>
        </div>

        @if($gatesQualidade['modo'] === 'advogado')
            <div class="px-5 py-4 grid grid-cols-3 gap-3 text-center">
                <div class="p-3 rounded-lg bg-amber-50 border border-amber-200">
                    <div class="text-2xl font-bold text-amber-700">{{ $gatesQualidade['totais']['aberto'] ?? 0 }}</div>
                    <div class="text-xs text-amber-800">Aguardando revisão</div>
                </div>
                <div class="p-3 rounded-lg bg-blue-50 border border-blue-200">
                    <div class="text-2xl font-bold text-blue-700">{{ $gatesQualidade['totais']['em_revisao'] ?? 0 }}</div>
                    <div class="text-xs text-blue-800">Em revisão (pós-DJ)</div>
                </div>
                <div class="p-3 rounded-lg bg-red-50 border border-red-300">
                    <div class="text-2xl font-bold text-red-700">{{ $gatesQualidade['totais']['escalado'] ?? 0 }}</div>
                    <div class="text-xs text-red-800">Escalado (gera PEN-C01)</div>
                </div>
            </div>
        @else
            @if($gatesQualidade['ranking']->isEmpty())
                <div class="px-5 py-6 text-center text-sm text-gray-500">Nenhum gate ativo no momento.</div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-600">
                        <tr>
                            <th class="px-4 py-2 text-left">Responsável</th>
                            <th class="px-3 py-2 text-right">Aberto</th>
                            <th class="px-3 py-2 text-right">Em revisão</th>
                            <th class="px-3 py-2 text-right">Escalado</th>
                            <th class="px-3 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($gatesQualidade['ranking'] as $r)
                        <tr class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-2 font-medium text-gray-800">{{ $r->owner_name ?? '(sem responsável)' }}</td>
                            <td class="px-3 py-2 text-right text-amber-700">{{ $r->abertos }}</td>
                            <td class="px-3 py-2 text-right text-blue-700">{{ $r->em_revisao }}</td>
                            <td class="px-3 py-2 text-right font-bold {{ $r->escalados > 0 ? 'text-red-700' : 'text-gray-500' }}">{{ $r->escalados }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ $r->total }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        @endif
    </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    Chart.defaults.font.family = "'Montserrat', 'Inter', system-ui";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = '#64748B';

    // ── 1. Pipeline por Estágio (Doughnut) ──
    @if($charts['pipeline_stages']->isNotEmpty())
    new Chart(document.getElementById('chartPipeline').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: {!! $charts['pipeline_stages']->pluck('name')->toJson() !!},
            datasets: [{
                data: {!! $charts['pipeline_stages']->pluck('count')->toJson() !!},
                backgroundColor: {!! $charts['pipeline_stages']->pluck('color')->map(fn($c) => $c)->toJson() !!},
                borderWidth: 2,
                borderColor: '#fff',
                hoverBorderWidth: 3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: ${ctx.parsed} opp(s)`
                    }
                }
            }
        }
    });
    @endif

    // ── 2. Tendência (Line) ──
    const trendLabels = {!! $charts['trend']->pluck('label')->toJson() !!};
    const trendWon    = {!! $charts['trend']->pluck('won')->toJson() !!};
    const trendLost   = {!! $charts['trend']->pluck('lost')->toJson() !!};

    new Chart(document.getElementById('chartTrend').getContext('2d'), {
        type: 'bar',
        data: {
            labels: trendLabels,
            datasets: [
                {
                    label: 'Ganhos',
                    data: trendWon,
                    backgroundColor: '#059669',
                    borderRadius: 4,
                    borderSkipped: false,
                },
                {
                    label: 'Perdidos',
                    data: trendLost,
                    backgroundColor: '#fca5a5',
                    borderRadius: 4,
                    borderSkipped: false,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { grid: { display: false }, border: { display: false } },
                y: {
                    grid: { color: '#f1f5f9' },
                    border: { display: false },
                    ticks: { stepSize: 1, precision: 0 }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: { boxWidth: 10, boxHeight: 10, borderRadius: 3, useBorderRadius: true, padding: 10 }
                }
            }
        }
    });

    // ── 3. Atividades (Doughnut) ──
    @if($charts['activities']->isNotEmpty())
    const actColors = { call:'#3b82f6', meeting:'#8b5cf6', whatsapp:'#22c55e', task:'#f59e0b', email:'#ef4444' };
    const actLabels = { call:'Ligação', meeting:'Reunião', whatsapp:'WhatsApp', task:'Tarefa', email:'Email' };
    const actKeys   = {!! $charts['activities']->keys()->toJson() !!};
    const actVals   = {!! $charts['activities']->values()->toJson() !!};

    new Chart(document.getElementById('chartAct').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: actKeys.map(k => actLabels[k] || k),
            datasets: [{
                data: actVals,
                backgroundColor: actKeys.map(k => actColors[k] || '#94a3b8'),
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed}` } }
            }
        }
    });
    @endif
});
</script>
@endpush

@endsection
