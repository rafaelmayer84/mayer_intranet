@extends('layouts.app')
@section('title', 'CRM - Meu CRM')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1B334A]">
                {{ $isRestricted ? 'Meu CRM' : 'CRM — Painel Geral' }}
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ $isRestricted ? 'Sua carteira, agenda e alertas' : 'Visão consolidada de toda a operação CRM' }}
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('crm.carteira') }}" class="px-4 py-2 border border-[#385776] text-[#385776] rounded-lg text-sm hover:bg-gray-50">Carteira</a>
            <a href="{{ route('crm.pipeline') }}" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A]">Pipeline →</a>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase">Clientes Ativos</p>
            <p class="text-2xl font-bold text-green-600">{{ number_format($kpis['active_clients']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase">Base Total</p>
            <p class="text-2xl font-bold text-[#1B334A]">{{ number_format($kpis['total_clients']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase">Opps Abertas</p>
            <p class="text-2xl font-bold text-blue-600">{{ $kpis['open_opps'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase">Pipeline</p>
            <p class="text-xl font-bold text-[#385776]">R$ {{ number_format($kpis['pipeline_value'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase">Win Rate (3m)</p>
            <p class="text-2xl font-bold {{ $kpis['win_rate'] >= 50 ? 'text-green-600' : 'text-yellow-600' }}">{{ $kpis['win_rate'] }}%</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase">Ganho Mês</p>
            <p class="text-xl font-bold text-green-600">R$ {{ number_format($kpis['won_month'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 {{ $kpis['sem_contato_30d'] > 0 ? 'ring-2 ring-red-200' : '' }}">
            <p class="text-xs text-gray-500 uppercase">Sem Contato 30d</p>
            <p class="text-2xl font-bold text-red-600">{{ $kpis['sem_contato_30d'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Coluna Esquerda: Agenda --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Agenda do Dia --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-[#1B334A] mb-4">📅 Agenda do Dia</h2>

                @if($agenda->vencidas->isNotEmpty())
                <div class="mb-4">
                    <h3 class="text-xs font-medium text-red-600 uppercase mb-2">🔴 Vencidas</h3>
                    <div class="space-y-2">
                        @foreach($agenda->vencidas as $act)
                        <a href="{{ $act->account ? route('crm.accounts.show', $act->account_id) : '#' }}" class="flex items-center gap-3 p-2 rounded-lg bg-red-50 hover:bg-red-100 transition text-sm">
                            <span>{{ match($act->type) { 'call' => '📞', 'meeting' => '🤝', 'whatsapp' => '💬', 'task' => '✅', 'email' => '✉️', default => '📝' } }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-800 truncate">{{ $act->title }}</p>
                                <p class="text-xs text-gray-500">{{ $act->account?->name }} · venceu {{ $act->due_at?->diffForHumans() }}</p>
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($agenda->hoje->isNotEmpty())
                <div class="mb-4">
                    <h3 class="text-xs font-medium text-blue-600 uppercase mb-2">📌 Hoje</h3>
                    <div class="space-y-2">
                        @foreach($agenda->hoje as $act)
                        <a href="{{ $act->account ? route('crm.accounts.show', $act->account_id) : '#' }}" class="flex items-center gap-3 p-2 rounded-lg bg-blue-50 hover:bg-blue-100 transition text-sm">
                            <span>{{ match($act->type) { 'call' => '📞', 'meeting' => '🤝', 'whatsapp' => '💬', 'task' => '✅', 'email' => '✉️', default => '📝' } }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-800 truncate">{{ $act->title }}</p>
                                <p class="text-xs text-gray-500">{{ $act->account?->name }} · {{ $act->due_at?->format('H:i') }}</p>
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($agenda->amanha->isNotEmpty())
                <div>
                    <h3 class="text-xs font-medium text-gray-500 uppercase mb-2">Amanhã</h3>
                    <div class="space-y-2">
                        @foreach($agenda->amanha as $act)
                        <a href="{{ $act->account ? route('crm.accounts.show', $act->account_id) : '#' }}" class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition text-sm">
                            <span>{{ match($act->type) { 'call' => '📞', 'meeting' => '🤝', 'whatsapp' => '💬', 'task' => '✅', 'email' => '✉️', default => '📝' } }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-800 truncate">{{ $act->title }}</p>
                                <p class="text-xs text-gray-500">{{ $act->account?->name }}</p>
                            </div>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($agenda->hoje->isEmpty() && $agenda->amanha->isEmpty() && $agenda->vencidas->isEmpty())
                <div class="text-center py-8">
                    <p class="text-gray-400">Nenhuma tarefa agendada.</p>
                    <p class="text-xs text-gray-300 mt-1">Registre atividades nos accounts para alimentar a agenda</p>
                </div>
                @endif
            </div>

            {{-- Oportunidades Abertas --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[#1B334A]">🎯 Oportunidades Abertas</h2>
                    <a href="{{ route('crm.pipeline') }}" class="text-xs text-[#385776] hover:underline">Ver pipeline →</a>
                </div>
                @if($openOpps->isEmpty())
                <p class="text-gray-400 text-sm text-center py-4">Nenhuma oportunidade aberta.</p>
                @else
                <div class="space-y-2">
                    @foreach($openOpps as $opp)
                    <a href="{{ route('crm.opportunities.show', $opp->id) }}" class="flex items-center justify-between p-3 rounded-lg border hover:bg-gray-50 transition">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-gray-800 text-sm truncate">{{ $opp->title }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs px-1.5 py-0.5 rounded" style="background-color: {{ $opp->stage?->color ?? '#eee' }}20; color: {{ $opp->stage?->color ?? '#666' }}">{{ $opp->stage?->name ?? '?' }}</span>
                                @if($opp->account)<span class="text-xs text-gray-400">{{ $opp->account->name }}</span>@endif
                            </div>
                        </div>
                        <div class="text-right ml-3 flex-shrink-0">
                            @if($opp->value_estimated)<p class="text-sm font-medium text-[#385776]">R$ {{ number_format($opp->value_estimated, 0, ',', '.') }}</p>@endif
                            @if($opp->next_action_at)
                            <p class="text-xs {{ $opp->next_action_at->isPast() ? 'text-red-500' : 'text-gray-400' }}">{{ $opp->next_action_at->format('d/m') }}</p>
                            @endif
                        </div>
                    </a>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        {{-- Coluna Direita: Alertas + Clientes Recentes --}}
        <div class="space-y-6">
            {{-- Alertas --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-[#1B334A] mb-4">🔔 Alertas</h2>
                @if(empty($alertas))
                <div class="text-center py-6">
                    <p class="text-green-500 text-sm">✅ Tudo em dia!</p>
                </div>
                @else
                <div class="space-y-2">
                    @foreach($alertas as $alerta)
                    <a href="{{ $alerta['link'] }}" class="flex items-start gap-2 p-2 rounded-lg hover:bg-{{ $alerta['cor'] }}-50 transition text-sm">
                        <span class="flex-shrink-0">{{ $alerta['icone'] }}</span>
                        <p class="text-gray-700">{{ $alerta['texto'] }}</p>
                    </a>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Clientes Recentes --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[#1B334A]">👥 Últimos Contatos</h2>
                    <a href="{{ route('crm.carteira') }}" class="text-xs text-[#385776] hover:underline">Ver todos →</a>
                </div>
                @if($recentClients->isEmpty())
                <p class="text-gray-400 text-sm text-center">Nenhum contato recente.</p>
                @else
                <div class="space-y-2">
                    @foreach($recentClients as $cli)
                    <a href="{{ route('crm.accounts.show', $cli->id) }}" class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 transition text-sm">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-800 truncate">{{ $cli->name }}</p>
                            <p class="text-xs text-gray-400">{{ $cli->owner?->name ?? 'Sem resp.' }}</p>
                        </div>
                        <span class="text-xs text-gray-400 flex-shrink-0">{{ $cli->last_touch_at?->diffForHumans(short: true) }}</span>
                    </a>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
