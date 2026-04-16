@extends('layouts.app')

@section('title', 'CRM - Pipeline')

@section('content')
<div class="ds">
<div class="ds-page w-full px-4 py-6">

    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 ds-a ds-a1">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--ds-text);">CRM Comercial</h1>
            <p class="text-sm mt-1" style="color: var(--ds-text-3);">Pipeline de oportunidades</p>
        </div>
        <div class="flex items-center gap-3 mt-3 md:mt-0">
            <a href="{{ route('crm.reports') }}" class="ds-btn ds-btn--secondary">
                📊 Relatórios
            </a>
            <a href="{{ route('crm.opportunity.create') }}" class="ds-btn ds-btn--primary">
                + Nova Oportunidade
            </a>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="ds-g4 mb-6 ds-a ds-a2">
        <div class="ds-stat ds-stat--blue">
            <div class="ds-stat-header">
                <span class="ds-stat-icon ds-stat-icon--primary">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 10a8 8 0 1116 0 8 8 0 01-16 0zm8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2z"/></svg>
                </span>
            </div>
            <div class="ds-stat-value">{{ $kpis['total_open'] }}</div>
            <div class="ds-stat-label">Pipeline Aberto</div>
            <p class="text-xs mt-1" style="color: var(--ds-text-3);">R$ {{ number_format($kpis['valor_pipeline'], 0, ',', '.') }}</p>
        </div>
        <div class="ds-stat ds-stat--green">
            <div class="ds-stat-header">
                <span class="ds-stat-icon ds-stat-icon--success">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                </span>
            </div>
            <div class="ds-stat-value" style="color: var(--ds-success);">{{ $kpis['total_won'] }}</div>
            <div class="ds-stat-label">Ganhos</div>
            <p class="text-xs mt-1" style="color: var(--ds-text-3);">R$ {{ number_format($kpis['valor_ganho'], 0, ',', '.') }}</p>
        </div>
        <div class="ds-stat ds-stat--blue">
            <div class="ds-stat-header">
                <span class="ds-stat-icon ds-stat-icon--primary">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zm6-4a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zm6-3a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
                </span>
            </div>
            <div class="ds-stat-value">{{ $kpis['win_rate'] }}%</div>
            <div class="ds-stat-label">Win Rate</div>
        </div>
        <div class="ds-stat ds-stat--{{ $kpis['overdue'] > 0 ? 'red' : 'orange' }}">
            <div class="ds-stat-header">
                <span class="ds-stat-icon ds-stat-icon--{{ $kpis['overdue'] > 0 ? 'danger' : 'warning' }}">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                </span>
            </div>
            <div class="ds-stat-value">{{ $kpis['overdue'] }}</div>
            <div class="ds-stat-label">Atrasadas</div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="ds-card ds-a ds-a3 mb-6">
        <form method="GET" action="{{ route('crm.pipeline') }}" class="ds-card-body flex flex-wrap items-center gap-3">
            <select name="owner" class="text-sm border rounded-lg px-3 py-2 bg-white" style="border-color: var(--ds-border);">
                <option value="">Todos responsáveis</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ ($filters['owner'] ?? '') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                @endforeach
            </select>
            <select name="source" class="text-sm border rounded-lg px-3 py-2 bg-white" style="border-color: var(--ds-border);">
                <option value="">Todas origens</option>
                @foreach($sources as $src)
                    <option value="{{ $src }}" {{ ($filters['source'] ?? '') == $src ? 'selected' : '' }}>{{ ucfirst($src) }}</option>
                @endforeach
            </select>
            <input type="date" name="period_start" value="{{ $filters['period_start'] ?? '' }}"
                   class="text-sm border rounded-lg px-3 py-2" style="border-color: var(--ds-border);" placeholder="De">
            <input type="date" name="period_end" value="{{ $filters['period_end'] ?? '' }}"
                   class="text-sm border rounded-lg px-3 py-2" style="border-color: var(--ds-border);" placeholder="Até">
            <button type="submit" class="ds-btn ds-btn--primary ds-btn--sm">Filtrar</button>
            @if(!empty(array_filter($filters)))
                <a href="{{ route('crm.pipeline') }}" class="ds-btn ds-btn--ghost ds-btn--sm">Limpar</a>
            @endif
        </form>
    </div>

    {{-- Kanban Board --}}
    <div class="flex gap-4 overflow-x-auto pb-4 ds-a ds-a4" style="min-height: 60vh;">
        @foreach($pipeline as $col)
            @php $stage = $col['stage']; @endphp

            {{-- Não mostrar colunas terminais se vazias --}}
            @if($stage->isTerminal() && $col['count'] === 0)
                @continue
            @endif

            <div class="flex-shrink-0 w-72 ds-card" style="border-radius: var(--ds-radius);">
                {{-- Column header --}}
                <div class="ds-card-head" style="border-top: 3px solid {{ $stage->color }};">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold" style="color: var(--ds-text);">{{ $stage->name }}</span>
                        <span class="ds-badge ds-badge--neutral">{{ $col['count'] }}</span>
                    </div>
                    @if($col['value'] > 0)
                        <p class="text-xs mt-1" style="color: var(--ds-text-3);">R$ {{ number_format($col['value'], 0, ',', '.') }}</p>
                    @endif
                </div>

                {{-- Cards --}}
                <div class="ds-card-body--flush p-2 space-y-2 max-h-[65vh] overflow-y-auto">
                    @forelse($col['opportunities'] as $opp)
                        <a href="{{ route('crm.opportunity.show', $opp->id) }}"
                           class="ds-card block {{ $opp->isOverdue() ? 'border-red-300' : '' }}" style="margin: 0; padding: .75rem;">
                            <p class="text-sm font-medium truncate" style="color: var(--ds-text);">{{ $opp->account->name ?? 'Sem nome' }}</p>
                            <p class="text-xs mt-0.5 truncate" style="color: var(--ds-text-3);">{{ $opp->title }}</p>

                            <div class="flex flex-wrap gap-1 mt-2">
                                @if($opp->area)
                                    <span class="ds-badge ds-badge--info">{{ $opp->area }}</span>
                                @endif
                                @if($opp->source)
                                    <span class="ds-badge ds-badge--neutral">{{ $opp->source }}</span>
                                @endif
                            </div>

                            <div class="flex items-center justify-between mt-2">
                                @if($opp->value_estimated)
                                    <span class="text-xs font-medium" style="color: var(--ds-success);">R$ {{ number_format($opp->value_estimated, 0, ',', '.') }}</span>
                                @else
                                    <span></span>
                                @endif

                                @if($opp->next_action_at)
                                    <span class="text-[10px] {{ $opp->isOverdue() ? 'ds-badge ds-badge--danger' : '' }}" style="{{ !$opp->isOverdue() ? 'color: var(--ds-text-3);' : '' }}">
                                        {{ $opp->next_action_at->format('d/m') }}
                                    </span>
                                @endif
                            </div>

                            @if($opp->owner)
                                <p class="text-[10px] mt-1" style="color: var(--ds-text-3);">{{ $opp->owner->name }}</p>
                            @endif
                        </a>
                    @empty
                        <p class="text-xs text-center py-4" style="color: var(--ds-text-3);">Nenhuma oportunidade</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
</div>
@endsection
