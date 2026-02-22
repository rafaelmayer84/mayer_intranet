@extends('layouts.app')

@section('title', 'SISRH — Gestão de Remuneração')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: #1B334A;">SISRH — Gestão de Remuneração</h1>
            <p class="text-sm text-gray-500 mt-1">
                Ciclo: {{ $ciclo->nome ?? 'Nenhum ciclo ativo' }} | Ano: {{ $anoAtual }}
            </p>
        </div>
        @if(in_array($user->role, ['admin', 'socio']))
        <div class="flex gap-2">
            <a href="{{ route('sisrh.regras-rb') }}" class="px-4 py-2 text-sm rounded text-white" style="background-color: #385776;">
                ⚙️ Regras RB/Faixas
            </a>
            <a href="{{ route('sisrh.apuracao') }}" class="px-4 py-2 text-sm rounded text-white" style="background-color: #1B334A;">
                📊 Apurar Competência
            </a>
        </div>
        @endif
    </div>

    {{-- Cards resumo --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 border-l-4" style="border-color: #385776;">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Apurações {{ $anoAtual }}</p>
            <p class="text-2xl font-bold" style="color: #1B334A;">{{ $apuracoes->count() }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Fechadas</p>
            <p class="text-2xl font-bold text-green-700">{{ $apuracoes->where('status', 'closed')->count() }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-amber-500">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Em Aberto</p>
            <p class="text-2xl font-bold text-amber-700">{{ $apuracoes->where('status', 'open')->count() }}</p>
        </div>
    </div>

    {{-- Tabela de apurações --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full text-sm">
            <thead style="background-color: #385776;">
                <tr>
                    <th class="px-4 py-3 text-left text-white">Advogado</th>
                    <th class="px-4 py-3 text-center text-white">Mês/Ano</th>
                    <th class="px-4 py-3 text-right text-white">RB</th>
                    <th class="px-4 py-3 text-right text-white">Captação</th>
                    <th class="px-4 py-3 text-center text-white">Score GDP</th>
                    <th class="px-4 py-3 text-right text-white">RV Aplicada</th>
                    <th class="px-4 py-3 text-center text-white">Saldo Banco</th>
                    <th class="px-4 py-3 text-center text-white">Status</th>
                    <th class="px-4 py-3 text-center text-white">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($apuracoes as $ap)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">{{ $ap->user->name ?? 'N/D' }}</td>
                    <td class="px-4 py-3 text-center">{{ str_pad($ap->mes, 2, '0', STR_PAD_LEFT) }}/{{ $ap->ano }}</td>
                    <td class="px-4 py-3 text-right">R$ {{ number_format($ap->rb_valor, 2, ',', '.') }}</td>
                    <td class="px-4 py-3 text-right">R$ {{ number_format($ap->captacao_valor, 2, ',', '.') }}</td>
                    <td class="px-4 py-3 text-center">{{ number_format($ap->gdp_score, 1) }}%</td>
                    <td class="px-4 py-3 text-right font-semibold" style="color: #385776;">
                        R$ {{ number_format($ap->rv_aplicada, 2, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        R$ {{ number_format($saldos[$ap->user_id] ?? 0, 2, ',', '.') }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($ap->bloqueio_motivo)
                            <span class="px-2 py-1 rounded text-xs bg-red-100 text-red-700">{{ $ap->bloqueio_motivo }}</span>
                        @elseif($ap->status === 'closed')
                            <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-700">Fechada</span>
                        @else
                            <span class="px-2 py-1 rounded text-xs bg-amber-100 text-amber-700">Aberta</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <a href="{{ route('sisrh.espelho', [$ap->ano, $ap->mes, $ap->user_id]) }}"
                           class="text-sm underline" style="color: #385776;">
                            Ver Espelho
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-gray-400">
                        Nenhuma apuração encontrada para {{ $anoAtual }}.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
