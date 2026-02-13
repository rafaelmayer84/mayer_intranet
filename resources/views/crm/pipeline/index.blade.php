@extends('layouts.app')
@section('title', 'CRM - Pipeline')

@section('content')
<div class="max-w-full mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1B334A]">Pipeline</h1>
            <p class="text-sm text-gray-500 mt-1">Gestão de oportunidades</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('crm.carteira') }}" class="px-4 py-2 border border-[#385776] text-[#385776] rounded-lg text-sm hover:bg-gray-50">← Carteira</a>
            <a href="{{ route('crm.reports') }}" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A]">Relatórios</a>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase">Em aberto</p>
            <p class="text-2xl font-bold text-[#1B334A]">{{ $kpis['open_count'] }}</p>
            <p class="text-xs text-gray-400">R$ {{ number_format($kpis['open_value'], 2, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase">Vencidos</p>
            <p class="text-2xl font-bold {{ $kpis['overdue_count'] > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $kpis['overdue_count'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase">Ganhos (mês)</p>
            <p class="text-2xl font-bold text-green-600">{{ $kpis['won_month'] }}</p>
            <p class="text-xs text-gray-400">R$ {{ number_format($kpis['won_value'], 2, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase">Filtros</p>
            <form method="GET" class="flex gap-2 mt-1">
                <select name="type" onchange="this.form.submit()" class="border rounded px-2 py-1 text-xs flex-1">
                    <option value="">Todos</option>
                    <option value="aquisicao" {{ request('type') === 'aquisicao' ? 'selected' : '' }}>Aquisição</option>
                    <option value="carteira" {{ request('type') === 'carteira' ? 'selected' : '' }}>Carteira</option>
                </select>
            </form>
        </div>
    </div>

    {{-- Kanban Board --}}
    <div class="flex gap-4 overflow-x-auto pb-4" style="min-height: 500px;">
        @foreach($kanban as $col)
            @php $stage = $col['stage']; @endphp
            <div class="flex-shrink-0 w-72 bg-gray-50 rounded-lg border">
                {{-- Stage Header --}}
                <div class="px-3 py-2 border-b flex items-center justify-between" style="border-top: 3px solid {{ $stage->color }};">
                    <span class="font-medium text-sm text-gray-700">{{ $stage->name }}</span>
                    <span class="bg-gray-200 text-gray-600 rounded-full px-2 py-0.5 text-xs">{{ count($col['opportunities']) }}</span>
                </div>

                {{-- Cards --}}
                <div class="p-2 space-y-2 max-h-[60vh] overflow-y-auto">
                    @forelse($col['opportunities'] as $opp)
                        <div class="bg-white rounded-lg shadow-sm border p-3 hover:shadow-md transition cursor-pointer"
                             onclick="window.location='{{ route('crm.opportunities.show', $opp->id) }}'">
                            <div class="flex items-start justify-between mb-1">
                                <span class="text-xs px-1.5 py-0.5 rounded {{ $opp->type === 'aquisicao' ? 'bg-blue-100 text-blue-600' : 'bg-purple-100 text-purple-600' }}">
                                    {{ $opp->type === 'aquisicao' ? 'Aquisição' : 'Carteira' }}
                                </span>
                                @if($opp->isOverdue())
                                    <span class="text-xs text-red-500 font-medium">{{ $opp->overdueDays() }}d atraso</span>
                                @endif
                            </div>
                            <p class="font-medium text-sm text-gray-800 mb-1 line-clamp-2">{{ $opp->title }}</p>
                            <p class="text-xs text-gray-500 mb-2">{{ $opp->account?->name ?? '—' }}</p>
                            <div class="flex items-center justify-between text-xs text-gray-400">
                                @if($opp->value_estimated)
                                    <span class="font-medium text-gray-600">R$ {{ number_format($opp->value_estimated, 0, ',', '.') }}</span>
                                @else
                                    <span>—</span>
                                @endif
                                @if($opp->source)
                                    <span>{{ $opp->source }}</span>
                                @endif
                                @if($opp->owner)
                                    <span>{{ explode(' ', $opp->owner->name)[0] }}</span>
                                @endif
                            </div>
                            @if($opp->next_action_at)
                                <p class="text-xs mt-1 {{ $opp->isOverdue() ? 'text-red-500' : 'text-gray-400' }}">
                                    Ação: {{ $opp->next_action_at->format('d/m') }}
                                </p>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 text-center py-4">Nenhuma oportunidade</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
