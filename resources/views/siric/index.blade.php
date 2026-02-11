@extends('layouts.app')

@section('title', 'SIRIC - Análise de Crédito')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">🏦 SIRIC — Análise de Crédito</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Sistema de análise para parcelamento de honorários</p>
        </div>
        <a href="{{ route('siric.create') }}"
           class="mt-3 sm:mt-0 inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow transition">
            + Nova Consulta
        </a>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filtros --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 mb-6">
        <form method="GET" action="{{ route('siric.index') }}" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="busca" value="{{ $filtros['busca'] ?? '' }}"
                   placeholder="Buscar por nome ou CPF/CNPJ..."
                   class="flex-1 rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">

            <select name="status" class="rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm">
                <option value="">Todos os status</option>
                <option value="rascunho" {{ ($filtros['status'] ?? '') === 'rascunho' ? 'selected' : '' }}>Rascunho</option>
                <option value="coletado" {{ ($filtros['status'] ?? '') === 'coletado' ? 'selected' : '' }}>Coletado</option>
                <option value="analisado" {{ ($filtros['status'] ?? '') === 'analisado' ? 'selected' : '' }}>Analisado</option>
                <option value="decidido" {{ ($filtros['status'] ?? '') === 'decidido' ? 'selected' : '' }}>Decidido</option>
            </select>

            <select name="rating" class="rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm">
                <option value="">Todos os ratings</option>
                @foreach(['A','B','C','D','E'] as $r)
                    <option value="{{ $r }}" {{ ($filtros['rating'] ?? '') === $r ? 'selected' : '' }}>{{ $r }}</option>
                @endforeach
            </select>

            <button type="submit" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm rounded-lg transition">Filtrar</button>
        </form>
    </div>

    {{-- Tabela --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nome</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">CPF/CNPJ</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valor</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Parcelas</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Rating</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Decisão</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Data</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($consultas as $c)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $c->id }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $c->nome }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300 font-mono text-xs">{{ $c->cpf_cnpj_formatado }}</td>
                        <td class="px-4 py-3 text-right text-gray-800 dark:text-gray-100">R$ {{ number_format($c->valor_total, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-300">{{ $c->parcelas_desejadas }}x</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                @if($c->status === 'decidido') bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300
                                @elseif($c->status === 'analisado') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300
                                @elseif($c->status === 'coletado') bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300
                                @else bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400
                                @endif">
                                {{ ucfirst($c->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($c->rating)
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold
                                    @if($c->rating === 'A') bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300
                                    @elseif($c->rating === 'B') bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300
                                    @elseif($c->rating === 'C') bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300
                                    @elseif($c->rating === 'D') bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300
                                    @else bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300
                                    @endif">
                                    {{ $c->rating }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($c->decisao_humana)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    @if($c->decisao_humana === 'aprovado') bg-green-100 text-green-800
                                    @elseif($c->decisao_humana === 'condicionado') bg-yellow-100 text-yellow-800
                                    @else bg-red-100 text-red-800
                                    @endif">
                                    {{ ucfirst($c->decisao_humana) }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">{{ $c->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('siric.show', $c->id) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 text-sm font-medium">Ver →</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            Nenhuma consulta encontrada. <a href="{{ route('siric.create') }}" class="text-blue-600 hover:underline">Criar nova consulta</a>.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($consultas->hasPages())
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
            {{ $consultas->appends($filtros)->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
