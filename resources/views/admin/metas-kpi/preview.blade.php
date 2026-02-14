@extends('layouts.app')

@section('title', 'Confirmar Importação de Metas')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">📋 Confirmar Importação</h1>
            <p class="text-sm text-gray-500 mt-1">Revise os dados antes de gravar no banco.</p>
        </div>
        <a href="{{ route('admin.metas-kpi.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">
            ← Cancelar
        </a>
    </div>

    {{-- Resumo --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-emerald-50 rounded-xl border border-emerald-200 p-5">
            <div class="text-xs font-medium text-emerald-600 uppercase">Válidos</div>
            <div class="text-3xl font-bold text-emerald-700 mt-1">{{ count($validos) }}</div>
            <div class="text-xs text-emerald-500 mt-1">serão importados</div>
        </div>
        <div class="bg-red-50 rounded-xl border border-red-200 p-5">
            <div class="text-xs font-medium text-red-600 uppercase">Inválidos</div>
            <div class="text-3xl font-bold text-red-700 mt-1">{{ count($invalidos) }}</div>
            <div class="text-xs text-red-500 mt-1">serão ignorados</div>
        </div>
        <div class="bg-blue-50 rounded-xl border border-blue-200 p-5">
            <div class="text-xs font-medium text-blue-600 uppercase">Novos / Atualizações</div>
            @php
                $novos = collect($validos)->where('_status', 'novo')->count();
                $updates = collect($validos)->where('_status', 'atualizar')->count();
            @endphp
            <div class="text-3xl font-bold text-blue-700 mt-1">{{ $novos }} / {{ $updates }}</div>
            <div class="text-xs text-blue-500 mt-1">inserir / sobrescrever</div>
        </div>
    </div>

    {{-- Tabela de válidos --}}
    @if(count($validos) > 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6 overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">✅ Registros Válidos ({{ count($validos) }})</h3>
                <span class="text-xs text-gray-400">Mostrando primeiros {{ min(count($validos), 50) }}</span>
            </div>
            <div class="overflow-x-auto max-h-96">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">#</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Módulo</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">KPI</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">Mês</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-gray-500">Meta</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">Unidade</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">Tipo</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach(array_slice($validos, 0, 50) as $i => $l)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-1.5 text-gray-400">{{ $i + 1 }}</td>
                                <td class="px-3 py-1.5 text-gray-600">{{ $l['modulo'] }}</td>
                                <td class="px-3 py-1.5 font-medium text-gray-700">
                                    {{ $l['descricao'] ?? $l['kpi_key'] }}
                                </td>
                                <td class="px-3 py-1.5 text-center text-gray-600">
                                    {{ str_pad($l['mes'] ?? '', 2, '0', STR_PAD_LEFT) }}/{{ $l['ano'] ?? '' }}
                                </td>
                                <td class="px-3 py-1.5 text-right font-mono text-gray-800">
                                    @if(($l['unidade'] ?? '') === 'BRL')
                                        R$ {{ number_format((float)($l['meta_valor'] ?? 0), 2, ',', '.') }}
                                    @elseif(($l['unidade'] ?? '') === 'PCT')
                                        {{ number_format((float)($l['meta_valor'] ?? 0), 1, ',', '.') }}%
                                    @else
                                        {{ number_format((float)($l['meta_valor'] ?? 0), 0, ',', '.') }}
                                    @endif
                                </td>
                                <td class="px-3 py-1.5 text-center text-gray-500">{{ $l['unidade'] ?? '' }}</td>
                                <td class="px-3 py-1.5 text-center">
                                    @if(($l['tipo_meta'] ?? '') === 'max')
                                        <span class="text-xs text-red-500">↓ teto</span>
                                    @else
                                        <span class="text-xs text-green-500">↑ piso</span>
                                    @endif
                                </td>
                                <td class="px-3 py-1.5 text-center">
                                    @if(($l['_status'] ?? '') === 'atualizar')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-600">sobrescrever</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-600">novo</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Tabela de inválidos --}}
    @if(count($invalidos) > 0)
        <div class="bg-white rounded-xl shadow-sm border border-red-200 mb-6 overflow-hidden">
            <div class="px-5 py-3 bg-red-50 border-b border-red-200">
                <h3 class="text-sm font-semibold text-red-700">❌ Registros Inválidos ({{ count($invalidos) }}) — serão ignorados</h3>
            </div>
            <div class="overflow-x-auto max-h-48">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Módulo</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">KPI</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-red-500">Erro</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($invalidos as $l)
                            <tr>
                                <td class="px-3 py-1.5 text-gray-500">{{ $l['modulo'] ?? '—' }}</td>
                                <td class="px-3 py-1.5 text-gray-500">{{ $l['kpi_key'] ?? '—' }}</td>
                                <td class="px-3 py-1.5 text-red-600 text-xs">{{ $l['_erro'] ?? 'Desconhecido' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Botões de ação --}}
    <div class="flex items-center justify-between bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <a href="{{ route('admin.metas-kpi.index') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">
            ✖ Cancelar
        </a>

        @if(count($validos) > 0)
            <form method="POST" action="{{ route('admin.metas-kpi.confirmar') }}">
                @csrf
                <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 text-white text-sm font-semibold
                           rounded-lg hover:bg-emerald-700 transition-colors shadow-sm">
                    ✅ Confirmar e Gravar {{ count($validos) }} Metas
                </button>
            </form>
        @endif
    </div>

</div>
@endsection
