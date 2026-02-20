@extends('layouts.app')
@section('title', 'CRM - Carteira')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1B334A]">Carteira de Clientes</h1>
            <p class="text-sm text-gray-500 mt-1">Visão gerencial de toda a base CRM</p>
        </div>
        <a href="{{ route('crm.pipeline') }}" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A] transition">
            Ver Pipeline →
        </a>
    </div>

    {{-- Cards KPI --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase">Total</p>
            <p class="text-2xl font-bold text-[#1B334A]">{{ number_format($totals['total']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 cursor-pointer hover:ring-2 hover:ring-green-300" onclick="window.location='{{ route('crm.carteira', ['lifecycle' => 'ativo']) }}'">
            <p class="text-xs text-gray-500 uppercase">Ativos</p>
            <p class="text-2xl font-bold text-green-600">{{ number_format($totals['ativos']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 cursor-pointer hover:ring-2 hover:ring-yellow-300" onclick="window.location='{{ route('crm.carteira', ['lifecycle' => 'adormecido']) }}'">
            <p class="text-xs text-gray-500 uppercase">Adormecidos</p>
            <p class="text-2xl font-bold text-yellow-600">{{ number_format($totals['adormecido']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 cursor-pointer hover:ring-2 hover:ring-gray-300" onclick="window.location='{{ route('crm.carteira', ['lifecycle' => 'arquivado']) }}'">
            <p class="text-xs text-gray-500 uppercase">Arquivados</p>
            <p class="text-2xl font-bold text-gray-500">{{ number_format($totals['arquivado']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 cursor-pointer hover:ring-2 hover:ring-blue-300" onclick="window.location='{{ route('crm.carteira', ['lifecycle' => 'onboarding']) }}'">
            <p class="text-xs text-gray-500 uppercase">Onboarding</p>
            <p class="text-2xl font-bold text-blue-600">{{ number_format($totals['onboarding']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 border-red-200 cursor-pointer hover:ring-2 hover:ring-red-300" onclick="window.location='{{ route('crm.carteira', ['lifecycle' => 'ativo', 'sem_contato_dias' => 30]) }}'">
            <p class="text-xs text-red-500 uppercase">Sem Contato 30d</p>
            <p class="text-2xl font-bold text-red-600">{{ number_format($totals['sem_contato_30d']) }}</p>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('crm.carteira') }}" class="bg-white rounded-lg shadow-sm border p-4 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar nome, doc, email..."
                   class="border rounded-lg px-3 py-2 text-sm col-span-2">
            <select name="kind" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os tipos</option>
                <option value="client" {{ request('kind') === 'client' ? 'selected' : '' }}>Clientes</option>
                <option value="prospect" {{ request('kind') === 'prospect' ? 'selected' : '' }}>Prospects</option>
            </select>
            <select name="lifecycle" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os ciclos</option>
                @foreach(['onboarding','ativo','adormecido','arquivado','risco'] as $lc)
                    <option value="{{ $lc }}" {{ request('lifecycle') === $lc ? 'selected' : '' }}>{{ ucfirst($lc) }}</option>
                @endforeach
            </select>
            <select name="owner_user_id" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os responsáveis</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('owner_user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <select name="sem_contato_dias" class="border rounded-lg px-3 py-2 text-sm flex-1">
                    <option value="">Sem contato</option>
                    <option value="7" {{ request('sem_contato_dias') == '7' ? 'selected' : '' }}>+7 dias</option>
                    <option value="15" {{ request('sem_contato_dias') == '15' ? 'selected' : '' }}>+15 dias</option>
                    <option value="30" {{ request('sem_contato_dias') == '30' ? 'selected' : '' }}>+30 dias</option>
                    <option value="60" {{ request('sem_contato_dias') == '60' ? 'selected' : '' }}>+60 dias</option>
                    <option value="90" {{ request('sem_contato_dias') == '90' ? 'selected' : '' }}>+90 dias</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A]">Filtrar</button>
            </div>
        </div>
        <div class="flex gap-3 mt-2">
            <label class="flex items-center gap-1 text-sm">
                <input type="checkbox" name="overdue_only" value="1" {{ request()->boolean('overdue_only') ? 'checked' : '' }}>
                Ação vencida
            </label>
        </div>
    </form>

    {{-- Tabela --}}
    <div class="bg-white rounded-lg shadow-sm border overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Nome</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Tipo</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Responsável</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Último contato</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Próxima ação</th>
                    <th class="text-center px-4 py-3 font-medium text-gray-600">Saúde</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Ciclo</th>
                    <th class="text-center px-4 py-3 font-medium text-gray-600">Opps</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($accounts as $acc)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <a href="{{ route('crm.accounts.show', $acc->id) }}" class="font-medium text-[#385776] hover:underline">
                            {{ $acc->name }}
                        </a>
                        @if($acc->doc_digits)
                            <span class="text-xs text-gray-400 ml-1">{{ strlen($acc->doc_digits) === 11 ? 'PF' : 'PJ' }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded text-xs {{ $acc->kind === 'client' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                            {{ $acc->kind === 'client' ? 'Cliente' : 'Prospect' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $acc->owner?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">
                        @if($acc->last_touch_at)
                            <span class="{{ $acc->last_touch_at->diffInDays(now()) > 30 ? 'text-red-500' : '' }}">
                                {{ $acc->last_touch_at->diffForHumans() }}
                            </span>
                        @else
                            <span class="text-gray-400">Nunca</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($acc->next_touch_at)
                            <span class="{{ $acc->next_touch_at->isPast() ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                                {{ $acc->next_touch_at->format('d/m/Y') }}
                                @if($acc->next_touch_at->isPast())
                                    <span class="text-xs">({{ $acc->next_touch_at->diffInDays(now()) }}d atraso)</span>
                                @endif
                            </span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($acc->health_score !== null)
                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                {{ $acc->health_score >= 70 ? 'bg-green-100 text-green-700' : ($acc->health_score >= 40 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                {{ $acc->health_score }}
                            </span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $lcColors = ['onboarding' => 'bg-blue-100 text-blue-700', 'ativo' => 'bg-green-100 text-green-700', 'adormecido' => 'bg-yellow-100 text-yellow-700', 'risco' => 'bg-red-100 text-red-700', 'arquivado' => 'bg-gray-200 text-gray-600'];
                        @endphp
                        <span class="px-2 py-0.5 rounded text-xs {{ $lcColors[$acc->lifecycle] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ ucfirst($acc->lifecycle) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center text-gray-600">{{ $acc->open_opps_count ?? 0 }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('crm.accounts.show', $acc->id) }}" class="text-[#385776] hover:underline text-xs">360 →</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-gray-400">Nenhum registro encontrado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginação --}}
    <div class="mt-4">{{ $accounts->links() }}</div>
</div>
@endsection
