@extends('layouts.app')

@section('title', 'GDP — Acompanhamentos (Admin)')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-2" style="color: #1B334A;">Acompanhamentos Bimestrais — Validação</h1>
    <p class="text-sm text-gray-500 mb-6">Ciclo: {{ $ciclo->nome ?? 'N/D' }}</p>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full text-sm">
            <thead style="background-color: #385776;">
                <tr>
                    <th class="px-4 py-3 text-left text-white">Advogado</th>
                    <th class="px-4 py-3 text-center text-white">Bimestre</th>
                    <th class="px-4 py-3 text-center text-white">Status</th>
                    <th class="px-4 py-3 text-center text-white">Submetido em</th>
                    <th class="px-4 py-3 text-center text-white">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($acompanhamentos as $a)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">{{ $a->user->name ?? 'N/D' }}</td>
                    <td class="px-4 py-3 text-center">{{ $a->bimestre }}º</td>
                    <td class="px-4 py-3 text-center">
                        @if($a->status === 'validated')
                            <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Validado</span>
                        @elseif($a->status === 'submitted')
                            <span class="px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700">Submetido</span>
                        @elseif($a->status === 'rejected')
                            <span class="px-2 py-0.5 rounded text-xs bg-red-100 text-red-700">Rejeitado</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600">Rascunho</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center text-xs text-gray-500">
                        {{ $a->submitted_at ? $a->submitted_at->format('d/m/Y H:i') : '-' }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($a->status === 'submitted')
                        <div class="flex gap-2 justify-center">
                            <form action="{{ route('gdp.acompanhamento.validar', $a->id) }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="status" value="validated">
                                <button type="submit" class="px-3 py-1 rounded text-xs text-white bg-green-600 hover:bg-green-700">
                                    Validar
                                </button>
                            </form>
                            <form action="{{ route('gdp.acompanhamento.validar', $a->id) }}" method="POST" class="inline"
                                  onsubmit="event.preventDefault(); const obs=prompt('Motivo da rejeição:'); if(obs){this.querySelector('[name=observacoes]').value=obs; this.submit();}">
                                @csrf
                                <input type="hidden" name="status" value="rejected">
                                <input type="hidden" name="observacoes" value="">
                                <button type="submit" class="px-3 py-1 rounded text-xs text-white bg-red-600 hover:bg-red-700">
                                    Rejeitar
                                </button>
                            </form>
                        </div>
                        @elseif($a->status === 'validated')
                            <span class="text-xs text-gray-400">Validado por {{ $a->validador->name ?? 'N/D' }}</span>
                        @else
                            <span class="text-xs text-gray-400">-</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-gray-400">Nenhum acompanhamento encontrado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
