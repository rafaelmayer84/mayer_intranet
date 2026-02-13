@extends('layouts.app')

@section('title', 'CRM - Pipeline')

@section('content')
<div class="max-w-full mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">CRM Comercial</h1>
            <p class="text-sm text-gray-500 mt-1">Pipeline de oportunidades</p>
        </div>
        <div class="flex items-center gap-3 mt-3 md:mt-0">
            <a href="{{ route('crm.reports') }}"
               class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                📊 Relatórios
            </a>
            <a href="{{ route('crm.opportunity.create') }}"
               class="px-4 py-2 text-sm bg-[#385776] text-white rounded-lg hover:bg-[#1B334A] transition-colors">
                + Nova Oportunidade
            </a>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Pipeline Aberto</p>
            <p class="text-xl font-bold text-gray-800 mt-1">{{ $kpis['total_open'] }}</p>
            <p class="text-xs text-gray-400 mt-1">R$ {{ number_format($kpis['valor_pipeline'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Ganhos</p>
            <p class="text-xl font-bold text-emerald-600 mt-1">{{ $kpis['total_won'] }}</p>
            <p class="text-xs text-gray-400 mt-1">R$ {{ number_format($kpis['valor_ganho'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Win Rate</p>
            <p class="text-xl font-bold text-[#385776] mt-1">{{ $kpis['win_rate'] }}%</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Atrasadas</p>
            <p class="text-xl font-bold {{ $kpis['overdue'] > 0 ? 'text-red-600' : 'text-gray-800' }} mt-1">{{ $kpis['overdue'] }}</p>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('crm.pipeline') }}" class="flex flex-wrap items-center gap-3 mb-6 bg-white rounded-lg border border-gray-200 p-3">
        <select name="owner" class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white focus:ring-[#385776] focus:border-[#385776]">
            <option value="">Todos responsáveis</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" {{ ($filters['owner'] ?? '') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
            @endforeach
        </select>
        <select name="source" class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white focus:ring-[#385776] focus:border-[#385776]">
            <option value="">Todas origens</option>
            @foreach($sources as $src)
                <option value="{{ $src }}" {{ ($filters['source'] ?? '') == $src ? 'selected' : '' }}>{{ ucfirst($src) }}</option>
            @endforeach
        </select>
        <input type="date" name="period_start" value="{{ $filters['period_start'] ?? '' }}"
               class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-[#385776] focus:border-[#385776]" placeholder="De">
        <input type="date" name="period_end" value="{{ $filters['period_end'] ?? '' }}"
               class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-[#385776] focus:border-[#385776]" placeholder="Até">
        <button type="submit" class="px-4 py-2 text-sm bg-[#385776] text-white rounded-lg hover:bg-[#1B334A]">Filtrar</button>
        @if(!empty(array_filter($filters)))
            <a href="{{ route('crm.pipeline') }}" class="text-sm text-gray-500 hover:text-gray-700">Limpar</a>
        @endif
    </form>

    {{-- Kanban Board --}}
    <div class="flex gap-4 overflow-x-auto pb-4" style="min-height: 60vh;">
        @foreach($pipeline as $col)
            @php $stage = $col['stage']; @endphp

            {{-- Não mostrar colunas terminais se vazias --}}
            @if($stage->isTerminal() && $col['count'] === 0)
                @continue
            @endif

            <div class="flex-shrink-0 w-72 bg-gray-50 rounded-xl border border-gray-200">
                {{-- Column header --}}
                <div class="px-4 py-3 border-b border-gray-200 rounded-t-xl" style="border-top: 3px solid {{ $stage->color }};">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700">{{ $stage->name }}</span>
                        <span class="text-xs bg-gray-200 text-gray-600 rounded-full px-2 py-0.5">{{ $col['count'] }}</span>
                    </div>
                    @if($col['value'] > 0)
                        <p class="text-xs text-gray-400 mt-1">R$ {{ number_format($col['value'], 0, ',', '.') }}</p>
                    @endif
                </div>

                {{-- Cards --}}
                <div class="p-2 space-y-2 max-h-[65vh] overflow-y-auto">
                    @forelse($col['opportunities'] as $opp)
                        <a href="{{ route('crm.opportunity.show', $opp->id) }}"
                           class="block bg-white rounded-lg border {{ $opp->isOverdue() ? 'border-red-300' : 'border-gray-200' }} p-3 hover:shadow-md transition-shadow">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $opp->account->name ?? 'Sem nome' }}</p>
                            <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $opp->title }}</p>

                            <div class="flex flex-wrap gap-1 mt-2">
                                @if($opp->area)
                                    <span class="text-[10px] bg-blue-50 text-blue-700 rounded px-1.5 py-0.5">{{ $opp->area }}</span>
                                @endif
                                @if($opp->source)
                                    <span class="text-[10px] bg-gray-100 text-gray-600 rounded px-1.5 py-0.5">{{ $opp->source }}</span>
                                @endif
                            </div>

                            <div class="flex items-center justify-between mt-2">
                                @if($opp->value_estimated)
                                    <span class="text-xs font-medium text-emerald-600">R$ {{ number_format($opp->value_estimated, 0, ',', '.') }}</span>
                                @else
                                    <span></span>
                                @endif

                                @if($opp->next_action_at)
                                    <span class="text-[10px] {{ $opp->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                                        {{ $opp->next_action_at->format('d/m') }}
                                    </span>
                                @endif
                            </div>

                            @if($opp->owner)
                                <p class="text-[10px] text-gray-400 mt-1">{{ $opp->owner->name }}</p>
                            @endif
                        </a>
                    @empty
                        <p class="text-xs text-gray-400 text-center py-4">Nenhuma oportunidade</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
