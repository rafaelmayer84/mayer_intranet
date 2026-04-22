@extends('layouts.app')
@section('title', 'CRM - Pipeline')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/crm-modern.css') }}">
@endpush

@section('content')
<div>
    {{-- ══════════════ HERO EDITORIAL ══════════════ --}}
    <section class="crm-hero">
        <div>
            <div class="crm-hero-eyebrow">{{ $kpis['open_count'] ?? 0 }} oportunidades em aberto</div>
            <h1>Pipeline <em>comercial</em>.</h1>
            <p class="crm-hero-sub">Kanban de oportunidades em andamento — estágios, valor estimado, próximas ações.</p>
        </div>
        <div class="crm-hero-right" style="display:flex;gap:10px;align-items:flex-end;">
            <a href="{{ route('crm.carteira') }}" class="crm-section-head-action">← Carteira</a>
            <a href="{{ route('crm.reports') }}" class="crm-section-head-action">Relatórios →</a>
        </div>
    </section>

    {{-- KPIs + Filtro --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="pipeline-kpi" style="--kpi-accent:var(--brand-navy)">
            <p style="font-size:0.68rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--brand-text-muted);margin-bottom:0.3rem">Em aberto</p>
            <p style="font-size:1.65rem;font-weight:700;color:var(--brand-navy);line-height:1.1">{{ $kpis['open_count'] }}</p>
            <p style="font-size:0.72rem;color:var(--brand-text-muted);margin-top:0.15rem">R$ {{ number_format($kpis['open_value'], 0, ',', '.') }}</p>
        </div>
        <div class="pipeline-kpi" style="--kpi-accent:{{ $kpis['overdue_count'] > 0 ? '#dc2626' : '#e2e8f0' }}">
            <p style="font-size:0.68rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--brand-text-muted);margin-bottom:0.3rem">Vencidos</p>
            <p style="font-size:1.65rem;font-weight:700;line-height:1.1;color:{{ $kpis['overdue_count'] > 0 ? '#dc2626' : '#94a3b8' }}">{{ $kpis['overdue_count'] }}</p>
        </div>
        <div class="pipeline-kpi" style="--kpi-accent:#059669">
            <p style="font-size:0.68rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--brand-text-muted);margin-bottom:0.3rem">Ganhos (mês)</p>
            <p style="font-size:1.65rem;font-weight:700;color:#059669;line-height:1.1">{{ $kpis['won_month'] }}</p>
            <p style="font-size:0.72rem;color:var(--brand-text-muted);margin-top:0.15rem">R$ {{ number_format($kpis['won_value'], 0, ',', '.') }}</p>
        </div>
        <div class="pipeline-filter-card">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--brand-text-muted);flex-shrink:0"><path d="M3 4h18M7 12h10M11 20h2"/></svg>
            <form method="GET" class="flex-1">
                <select name="type" onchange="this.form.submit()"
                    style="border:1px solid var(--brand-border);border-radius:8px;padding:0.35rem 0.6rem;font-size:0.78rem;width:100%;background:#fff;color:var(--brand-text)">
                    <option value="">Todos os tipos</option>
                    <option value="aquisicao" {{ request('type') === 'aquisicao' ? 'selected' : '' }}>Aquisição</option>
                    <option value="carteira"  {{ request('type') === 'carteira'  ? 'selected' : '' }}>Carteira</option>
                </select>
            </form>
        </div>
    </div>

    {{-- Kanban Board --}}
    <div class="kanban-board">
        @foreach($kanban as $col)
            @php $stage = $col['stage']; @endphp
            <div class="kanban-col">
                {{-- Column Header --}}
                <div class="kanban-col-header" style="border-top:3px solid {{ $stage->color }}">
                    <span class="kanban-col-title">{{ $stage->name }}</span>
                    <span class="kanban-col-count">{{ count($col['opportunities']) }}</span>
                </div>

                {{-- Cards --}}
                <div class="kanban-col-body">
                    @forelse($col['opportunities'] as $opp)
                        <div class="pipeline-card {{ $opp->isOverdue() ? 'is-overdue' : '' }}"
                             onclick="window.location='{{ route('crm.opportunities.show', $opp->id) }}'">

                            {{-- Delete button (hidden until hover) --}}
                            @if(auth()->user()->role === 'admin')
                            <button class="pipeline-card-delete"
                                    onclick="event.stopPropagation(); excluirOportunidade({{ $opp->id }}, '{{ addslashes($opp->title) }}')"
                                    title="Excluir oportunidade">
                                🗑
                            </button>
                            @endif

                            {{-- Top badges --}}
                            <div class="pipeline-card-top">
                                <span class="pipeline-card-type {{ $opp->type === 'aquisicao' ? 'type-aquisicao' : 'type-carteira' }}">
                                    {{ $opp->type === 'aquisicao' ? 'Aquisição' : 'Carteira' }}
                                </span>
                                @if($opp->isOverdue())
                                <span class="pipeline-overdue-badge">{{ $opp->overdueDays() }}d atraso</span>
                                @endif
                            </div>

                            {{-- Title + Client --}}
                            <p class="pipeline-card-title">{{ $opp->title }}</p>
                            <p class="pipeline-card-client">{{ $opp->account?->name ?? '—' }}</p>

                            {{-- Footer --}}
                            <div class="pipeline-card-footer">
                                <span class="pipeline-card-value">
                                    @if($opp->value_estimated)
                                        R$ {{ number_format($opp->value_estimated, 0, ',', '.') }}
                                    @else
                                        <span style="color:#cbd5e1">—</span>
                                    @endif
                                </span>
                                <div class="pipeline-card-meta">
                                    @if($opp->next_action_at)
                                    <span class="pipeline-card-date {{ $opp->isOverdue() ? 'overdue' : '' }}">
                                        📅 {{ $opp->next_action_at->format('d/m') }}
                                    </span>
                                    @endif
                                    @if($opp->owner)
                                    <span class="pipeline-card-owner"
                                          title="{{ $opp->owner->name }}">{{ mb_substr($opp->owner->name, 0, 2) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="crm-empty" style="padding:2rem 0.5rem">
                            <div class="crm-empty-icon">📭</div>
                            <p class="crm-empty-text">Nenhuma oportunidade</p>
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
function excluirOportunidade(id, titulo) {
    if (!confirm('Excluir "' + titulo + '"?\n\nEsta ação não pode ser desfeita.')) return;
    fetch('/crm/pipeline/' + id, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) { location.reload(); }
        else { alert(data.error || 'Erro ao excluir'); }
    })
    .catch(() => alert('Erro de conexão'));
}
</script>
@endpush

@endsection
