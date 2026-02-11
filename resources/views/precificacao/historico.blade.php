@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('precificacao.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm">← Voltar</a>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Histórico de Propostas</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('precificacao.historico', ['status' => 'gerada']) }}" class="px-3 py-1 text-xs rounded-full {{ request('status') === 'gerada' ? 'bg-yellow-200 text-yellow-800' : 'bg-gray-100 text-gray-600' }}">Geradas</a>
            <a href="{{ route('precificacao.historico', ['status' => 'enviada']) }}" class="px-3 py-1 text-xs rounded-full {{ request('status') === 'enviada' ? 'bg-blue-200 text-blue-800' : 'bg-gray-100 text-gray-600' }}">Enviadas</a>
            <a href="{{ route('precificacao.historico', ['status' => 'aceita']) }}" class="px-3 py-1 text-xs rounded-full {{ request('status') === 'aceita' ? 'bg-green-200 text-green-800' : 'bg-gray-100 text-gray-600' }}">Aceitas</a>
            <a href="{{ route('precificacao.historico') }}" class="px-3 py-1 text-xs rounded-full {{ !request('status') ? 'bg-indigo-200 text-indigo-800' : 'bg-gray-100 text-gray-600' }}">Todas</a>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-900/50 text-left text-gray-500 dark:text-gray-400">
                    <th class="px-4 py-3 font-medium">#</th>
                    <th class="px-4 py-3 font-medium">Data</th>
                    <th class="px-4 py-3 font-medium">Proponente</th>
                    <th class="px-4 py-3 font-medium">Área</th>
                    <th class="px-4 py-3 font-medium">Recomendação</th>
                    <th class="px-4 py-3 font-medium">Escolhida</th>
                    <th class="px-4 py-3 font-medium">Valor Final</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($propostas as $p)
                <tr class="border-t border-gray-100 dark:border-gray-700/50 hover:bg-gray-50 dark:hover:bg-gray-900/30">
                    <td class="px-4 py-3 text-gray-500">{{ $p->id }}</td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $p->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">{{ $p->nome_proponente ?? '-' }}</td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $p->area_direito ?? '-' }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300">
                            {{ ucfirst($p->recomendacao_ia ?? '-') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $p->proposta_escolhida ? ucfirst($p->proposta_escolhida) : '-' }}</td>
                    <td class="px-4 py-3 font-medium text-gray-800 dark:text-white">
                        {{ $p->valor_final ? 'R$ ' . number_format($p->valor_final, 2, ',', '.') : '-' }}
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $sc = [
                                'gerada' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                                'enviada' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                                'aceita' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                'recusada' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                            ];
                        @endphp
                        <span class="px-2 py-0.5 text-xs rounded-full {{ $sc[$p->status] ?? 'bg-gray-100 text-gray-700' }}">
                            {{ ucfirst($p->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('precificacao.show', $p->id) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Ver</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">Nenhuma proposta encontrada</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($propostas->hasPages())
    <div class="mt-4">
        {{ $propostas->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
