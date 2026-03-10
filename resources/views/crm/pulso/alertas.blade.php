@extends('layouts.app')
@section('title', 'Pulso - Alertas')

@section('content')
<div class="max-w-full mx-auto px-6 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-[#1B334A]">Alertas do Pulso</h1>
        <a href="{{ route('crm.pulso') }}" class="px-4 py-2 bg-white border rounded-lg text-sm hover:bg-gray-50">← Voltar ao Pulso</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border p-4 mb-4">
        <form method="GET" class="flex items-center gap-4">
            <label class="text-sm text-gray-600">Filtrar:</label>
            <select name="status" class="border rounded-lg px-3 py-1.5 text-sm" onchange="this.form.submit()">
                <option value="pendente" {{ $status == 'pendente' ? 'selected' : '' }}>Pendentes</option>
                <option value="visto" {{ $status == 'visto' ? 'selected' : '' }}>Vistos</option>
                <option value="resolvido" {{ $status == 'resolvido' ? 'selected' : '' }}>Resolvidos</option>
                <option value="todos" {{ $status == 'todos' ? 'selected' : '' }}>Todos</option>
            </select>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Data</th>
                    <th class="px-4 py-3 text-left">Cliente</th>
                    <th class="px-4 py-3 text-left">Tipo</th>
                    <th class="px-4 py-3 text-left">Descrição</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($alertas as $alerta)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $alerta->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('crm.accounts.show', $alerta->account_id) }}" class="text-[#385776] hover:underline">{{ $alerta->account->name ?? '—' }}</a>
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $tipoColors = ['diario_excedido' => 'bg-red-100 text-red-700', 'semanal_excedido' => 'bg-orange-100 text-orange-700', 'reiteracao' => 'bg-yellow-100 text-yellow-700', 'fora_horario' => 'bg-purple-100 text-purple-700'];
                        @endphp
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $tipoColors[$alerta->tipo] ?? 'bg-gray-100' }}">{{ str_replace('_', ' ', $alerta->tipo) }}</span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600 max-w-md truncate">{{ $alerta->descricao }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs {{ $alerta->status === 'pendente' ? 'bg-red-100 text-red-700' : ($alerta->status === 'visto' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">{{ ucfirst($alerta->status) }}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($alerta->status === 'pendente')
                            <form method="POST" action="{{ route('crm.pulso.alertas.visto', $alerta->id) }}" class="inline">@csrf<button class="text-xs text-blue-600 hover:underline mr-2">Visto</button></form>
                            <form method="POST" action="{{ route('crm.pulso.alertas.resolver', $alerta->id) }}" class="inline">@csrf<button class="text-xs text-green-600 hover:underline">Resolver</button></form>
                        @elseif($alerta->status === 'visto')
                            <form method="POST" action="{{ route('crm.pulso.alertas.resolver', $alerta->id) }}" class="inline">@csrf<button class="text-xs text-green-600 hover:underline">Resolver</button></form>
                        @else
                            <span class="text-xs text-gray-400">{{ $alerta->resolvidoPorUser->name ?? '—' }} em {{ $alerta->resolvido_em?->format('d/m') }}</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Nenhum alerta encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $alertas->links() }}</div>
</div>
@endsection
