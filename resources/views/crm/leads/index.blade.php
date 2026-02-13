@extends('layouts.app')
@section('title', 'CRM - Leads')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1B334A]">Leads</h1>
            <p class="text-sm text-gray-500 mt-1">Leads qualificados no pipeline comercial</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('crm.pipeline') }}" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A] transition">Oportunidades →</a>
        </div>
    </div>

    {{-- Cards KPI --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <a href="{{ route('crm.leads', ['status' => 'novo']) }}" class="bg-white rounded-lg shadow-sm border p-4 hover:shadow-md transition {{ request('status') === 'novo' ? 'ring-2 ring-blue-400' : '' }}">
            <p class="text-xs text-gray-500 uppercase">Novos</p>
            <p class="text-2xl font-bold text-blue-600">{{ number_format($totals['novo']) }}</p>
        </a>
        <a href="{{ route('crm.leads', ['status' => 'em_contato']) }}" class="bg-white rounded-lg shadow-sm border p-4 hover:shadow-md transition {{ request('status') === 'em_contato' ? 'ring-2 ring-green-400' : '' }}">
            <p class="text-xs text-gray-500 uppercase">Em Contato</p>
            <p class="text-2xl font-bold text-green-600">{{ number_format($totals['em_contato']) }}</p>
        </a>
        <a href="{{ route('crm.leads', ['status' => 'perdido']) }}" class="bg-white rounded-lg shadow-sm border p-4 hover:shadow-md transition {{ request('status') === 'perdido' ? 'ring-2 ring-red-400' : '' }}">
            <p class="text-xs text-gray-500 uppercase">Perdidos</p>
            <p class="text-2xl font-bold text-red-600">{{ number_format($totals['perdido']) }}</p>
        </a>
        <a href="{{ route('crm.leads') }}" class="bg-white rounded-lg shadow-sm border p-4 hover:shadow-md transition {{ !request('status') ? 'ring-2 ring-gray-400' : '' }}">
            <p class="text-xs text-gray-500 uppercase">Total</p>
            <p class="text-2xl font-bold text-[#1B334A]">{{ number_format($totals['total']) }}</p>
        </a>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('crm.leads') }}" class="bg-white rounded-lg shadow-sm border p-4 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar nome, email, telefone..."
                   class="border rounded-lg px-3 py-2 text-sm col-span-2">
            <select name="status" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os status</option>
                <option value="novo" {{ request('status') === 'novo' ? 'selected' : '' }}>Novo</option>
                <option value="em_contato" {{ request('status') === 'em_contato' ? 'selected' : '' }}>Em Contato</option>
                <option value="convertido" {{ request('status') === 'convertido' ? 'selected' : '' }}>Convertido</option>
                <option value="perdido" {{ request('status') === 'perdido' ? 'selected' : '' }}>Perdido</option>
            </select>
            <select name="owner_user_id" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os responsáveis</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('owner_user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A]">Filtrar</button>
        </div>
    </form>

    {{-- Tabela --}}
    <div class="bg-white rounded-lg shadow-sm border overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Nome</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Contato</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Responsável</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Último contato</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Criado em</th>
                    <th class="text-center px-4 py-3 font-medium text-gray-600">Opps</th>
                    <th class="px-4 py-3 font-medium text-gray-600">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($leads as $lead)
                <tr class="hover:bg-gray-50" id="row-{{ $lead->id }}">
                    <td class="px-4 py-3">
                        <a href="{{ route('crm.accounts.show', $lead->id) }}" class="font-medium text-[#385776] hover:underline">
                            {{ $lead->name }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        @if($lead->phone_e164)
                            <span class="block text-xs">{{ $lead->phone_e164 }}</span>
                        @endif
                        @if($lead->email)
                            <span class="block text-xs text-gray-400">{{ $lead->email }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $statusMap = [
                                'onboarding' => ['Novo', 'bg-blue-100 text-blue-700'],
                                'ativo'      => ['Em Contato', 'bg-green-100 text-green-700'],
                                'adormecido' => ['Adormecido', 'bg-yellow-100 text-yellow-700'],
                                'risco'      => ['Perdido', 'bg-red-100 text-red-700'],
                            ];
                            $st = $statusMap[$lead->lifecycle] ?? ['—', 'bg-gray-100 text-gray-600'];
                        @endphp
                        <select onchange="updateStatus({{ $lead->id }}, this.value)"
                                class="text-xs border rounded px-2 py-1 {{ $st[1] }}">
                            <option value="onboarding" {{ $lead->lifecycle === 'onboarding' ? 'selected' : '' }}>Novo</option>
                            <option value="ativo" {{ $lead->lifecycle === 'ativo' ? 'selected' : '' }}>Em Contato</option>
                            <option value="adormecido" {{ $lead->lifecycle === 'adormecido' ? 'selected' : '' }}>Adormecido</option>
                            <option value="risco" {{ $lead->lifecycle === 'risco' ? 'selected' : '' }}>Perdido</option>
                        </select>
                    </td>
                    <td class="px-4 py-3">
                        <select onchange="assignOwner({{ $lead->id }}, this.value)"
                                class="text-xs border rounded px-2 py-1">
                            <option value="">—</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ $lead->owner_user_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td class="px-4 py-3 text-gray-600 text-xs">
                        {{ $lead->last_touch_at ? $lead->last_touch_at->diffForHumans() : 'Nunca' }}
                    </td>
                    <td class="px-4 py-3 text-gray-600 text-xs">
                        {{ $lead->created_at->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3 text-center text-gray-600">{{ $lead->open_opps ?? 0 }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('crm.accounts.show', $lead->id) }}" class="text-[#385776] hover:underline text-xs">Ver 360 →</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-400">Nenhum lead encontrado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $leads->links() }}</div>
</div>

<script>
const csrf = '{{ csrf_token() }}';

function updateStatus(id, lifecycle) {
    fetch(`/crm/leads/${id}/status`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ lifecycle })
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            const row = document.getElementById('row-' + id);
            row.classList.add('bg-green-50');
            setTimeout(() => row.classList.remove('bg-green-50'), 1000);
        }
    });
}

function assignOwner(id, owner_user_id) {
    fetch(`/crm/leads/${id}/assign`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ owner_user_id: owner_user_id || null })
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            const row = document.getElementById('row-' + id);
            row.classList.add('bg-green-50');
            setTimeout(() => row.classList.remove('bg-green-50'), 1000);
        }
    });
}
</script>
@endsection
