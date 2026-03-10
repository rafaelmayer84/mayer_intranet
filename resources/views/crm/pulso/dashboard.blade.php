@extends('layouts.app')
@section('title', 'Pulso do Cliente')

@section('content')
<div class="max-w-full mx-auto px-6 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1B334A]">Pulso do Cliente</h1>
            <p class="text-sm text-gray-500">Monitoramento de volume de contatos por cliente</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('crm.pulso.alertas') }}" class="px-4 py-2 bg-white border rounded-lg text-sm hover:bg-gray-50 flex items-center gap-1.5">
                🔔 Alertas
                @if($alertasPendentes > 0)
                    <span class="px-1.5 py-0.5 bg-red-500 text-white text-xs rounded-full">{{ $alertasPendentes }}</span>
                @endif
            </a>
            @if(in_array(Auth::user()->role, ['admin','socio']))
            <a href="{{ route('crm.pulso.upload') }}" class="px-4 py-2 bg-white border rounded-lg text-sm hover:bg-gray-50">📞 Upload Ligações</a>
            <a href="{{ route('crm.pulso.config') }}" class="px-4 py-2 bg-white border rounded-lg text-sm hover:bg-gray-50">⚙️ Config</a>
            @endif
        </div>
    </div>

    {{-- Mega cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-400 mb-1">Alertas Pendentes</p>
            <p class="text-2xl font-bold {{ $alertasPendentes > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $alertasPendentes }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-400 mb-1">Clientes em Atenção</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $emAtencao }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-400 mb-1">Clientes em Excessivo</p>
            <p class="text-2xl font-bold text-red-600">{{ $emExcessivo }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-400 mb-1">Upload Ligações</p>
            <p class="text-sm font-medium {{ $uploadPendente ? 'text-yellow-600' : 'text-green-600' }}">{{ $uploadPendente ? '⚠️ Pendente esta semana' : '✅ OK' }}</p>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-white rounded-lg shadow-sm border p-4 mb-6">
        <form method="GET" class="flex items-center gap-4">
            <label class="text-sm text-gray-600">Período:</label>
            <select name="dias" class="border rounded-lg px-3 py-1.5 text-sm" onchange="this.form.submit()">
                <option value="7" {{ $dias == 7 ? 'selected' : '' }}>Últimos 7 dias</option>
                <option value="14" {{ $dias == 14 ? 'selected' : '' }}>Últimos 14 dias</option>
                <option value="30" {{ $dias == 30 ? 'selected' : '' }}>Últimos 30 dias</option>
            </select>
            <label class="text-sm text-gray-600 ml-4">Classificação:</label>
            <select name="classificacao" class="border rounded-lg px-3 py-1.5 text-sm" onchange="this.form.submit()">
                <option value="">Todas</option>
                <option value="excessivo" {{ $filtro == 'excessivo' ? 'selected' : '' }}>Excessivo</option>
                <option value="atencao" {{ $filtro == 'atencao' ? 'selected' : '' }}>Atenção</option>
                <option value="normal" {{ $filtro == 'normal' ? 'selected' : '' }}>Normal</option>
            </select>
        </form>
    </div>

    {{-- Ranking --}}
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Cliente</th>
                    <th class="px-4 py-3 text-center">WA</th>
                    <th class="px-4 py-3 text-center">Tickets</th>
                    <th class="px-4 py-3 text-center">Ligações</th>
                    <th class="px-4 py-3 text-center">CRM</th>
                    <th class="px-4 py-3 text-center">Total</th>
                    <th class="px-4 py-3 text-center">Média/dia</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-left">Último Alerta</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($ranking as $i => $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-400">{{ $i + 1 }}</td>
                    <td class="px-4 py-3">
                        @if($row['account'])
                        <a href="{{ route('crm.accounts.show', $row['account']->id) }}" class="text-[#385776] font-medium hover:underline">{{ $row['account']->name }}</a>
                        @else
                        <span class="text-gray-400">Account #{{ $row['account_id'] }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">{{ $row['soma_wa'] }}</td>
                    <td class="px-4 py-3 text-center">{{ $row['soma_tickets'] }}</td>
                    <td class="px-4 py-3 text-center">{{ $row['soma_phone'] }}</td>
                    <td class="px-4 py-3 text-center">{{ $row['soma_crm'] }}</td>
                    <td class="px-4 py-3 text-center font-bold">{{ $row['soma_total'] }}</td>
                    <td class="px-4 py-3 text-center">{{ number_format((float)$row['media_diaria'], 1, ',', '.') }}</td>
                    <td class="px-4 py-3 text-center">
                        @php $cls = $row['classificacao']; @endphp
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $cls === 'excessivo' ? 'bg-red-100 text-red-700' : ($cls === 'atencao' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                            {{ ucfirst($cls) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">
                        @if($row['ultimo_alerta'])
                            {{ $row['ultimo_alerta']->created_at->format('d/m H:i') }} — {{ Str::limit($row['ultimo_alerta']->tipo, 20) }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="px-4 py-8 text-center text-gray-400">Nenhum contato registrado no período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
