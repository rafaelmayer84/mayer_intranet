@extends('layouts.app')

@section('title', 'SISRH — Banco de Créditos')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold" style="color: #1B334A;">Banco de Créditos</h1>
        <a href="{{ route('sisrh.index') }}" class="text-sm underline" style="color: #385776;">← Voltar</a>
    </div>

    {{-- Saldos --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        @foreach($saldos as $uid => $info)
        <div class="bg-white rounded-lg shadow p-4 border-l-4" style="border-color: {{ $info['saldo'] > 0 ? '#385776' : '#d1d5db' }};">
            <p class="text-xs text-gray-500">{{ $info['nome'] }}</p>
            <p class="text-xl font-bold {{ $info['saldo'] > 0 ? '' : 'text-gray-400' }}" style="{{ $info['saldo'] > 0 ? 'color: #385776;' : '' }}">
                R$ {{ number_format($info['saldo'], 2, ',', '.') }}
            </p>
        </div>
        @endforeach
    </div>

    {{-- Movimentações --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full text-sm">
            <thead style="background-color: #385776;">
                <tr>
                    <th class="px-4 py-3 text-left text-white">Data</th>
                    <th class="px-4 py-3 text-left text-white">Advogado</th>
                    <th class="px-4 py-3 text-center text-white">Tipo</th>
                    <th class="px-4 py-3 text-right text-white">Valor</th>
                    <th class="px-4 py-3 text-center text-white">Ref. Mês</th>
                    <th class="px-4 py-3 text-left text-white">Motivo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($movimentacoes as $mov)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-xs text-gray-500">{{ $mov->created_at ? \Carbon\Carbon::parse($mov->created_at)->format('d/m/Y H:i') : '-' }}</td>
                    <td class="px-4 py-2">{{ $mov->user->name ?? 'N/D' }}</td>
                    <td class="px-4 py-2 text-center">
                        @if($mov->tipo === 'credit')
                            <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Crédito</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-xs bg-red-100 text-red-700">Débito</span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right font-medium {{ $mov->tipo === 'credit' ? 'text-green-700' : 'text-red-600' }}">
                        {{ $mov->tipo === 'credit' ? '+' : '-' }} R$ {{ number_format($mov->valor, 2, ',', '.') }}
                    </td>
                    <td class="px-4 py-2 text-center text-xs">{{ str_pad($mov->mes, 2, '0', STR_PAD_LEFT) }}/{{ $mov->ano }}</td>
                    <td class="px-4 py-2 text-xs text-gray-500">{{ Str::limit($mov->motivo, 60) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">Nenhuma movimentação registrada.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @if($movimentacoes->hasPages())
        <div class="px-4 py-3 border-t">{{ $movimentacoes->links() }}</div>
        @endif
    </div>
</div>
@endsection
