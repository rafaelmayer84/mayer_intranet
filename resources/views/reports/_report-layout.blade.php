@extends('layouts.app')

@section('title', $reportTitle ?? 'Relatório')



@section('content')
<style>
    .report-table { font-family: "Courier New", Courier, monospace !important; border-collapse: collapse !important; width: 100%; }
    .report-table thead tr th {
        background-color: #1B334A !important; color: #ffffff !important;
        font-size: 0.7rem !important; letter-spacing: 1px; text-transform: uppercase;
        padding: 10px 12px !important; border: 1px solid #0f1f2e !important; white-space: nowrap;
    }
    .report-table thead tr th a,
    .report-table thead tr th a:hover,
    .report-table thead tr th a:visited { color: #ffffff !important; text-decoration: none !important; }
    .report-table tbody tr:nth-child(even) td { background-color: #dcfce7 !important; }
    .report-table tbody tr:nth-child(odd) td { background-color: #ffffff !important; }
    .report-table tbody tr:hover td { background-color: #fef3c7 !important; }
    .report-table td { font-size: 0.78rem !important; padding: 6px 10px !important; border: 1px solid #d1d5db !important; }
    .report-table tfoot td { background-color: #cbd5e1 !important; font-weight: bold !important; border-top: 3px double #374151 !important; }
</style>
<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">

    {{-- Breadcrumb --}}
    <nav class="flex mb-4 text-sm text-gray-500" aria-label="Breadcrumb">
        <a href="{{ route('relatorios.index') }}" class="hover:text-[#385776]">Relatórios</a>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-400">{{ $domainLabel ?? '' }}</span>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-700 font-medium">{{ $reportTitle ?? '' }}</span>
    </nav>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800 font-mono tracking-wide">{{ $reportTitle ?? 'Relatório' }}</h1>
            <p class="text-xs text-gray-400 font-mono mt-1">{{ $domainLabel ?? '' }} • Sistema RESULTADOS!</p>
        </div>
        <div class="flex items-center gap-2">
            @if(isset($exportRoute))
            <a href="{{ $exportRoute }}&type=xlsx"
               class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold font-mono rounded-lg transition-colors tracking-wider">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                EXCEL
            </a>
            <a href="{{ $exportRoute }}&type=pdf" target="_blank"
               class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold font-mono rounded-lg transition-colors tracking-wider">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                PDF
            </a>
            @endif
        </div>
    </div>

    {{-- Filtros --}}
    @if(isset($filters) && count($filters) > 0)
    <form method="GET" action="" class="bg-gray-50 rounded-xl border border-gray-200 p-4 mb-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3 items-end">
            @foreach($filters as $filter)
            <div>
                <label class="block text-xs font-bold text-gray-500 mb-1 font-mono uppercase tracking-wider">{{ $filter['label'] }}</label>
                @if($filter['type'] === 'select')
                <select name="{{ $filter['name'] }}" class="w-full rounded-lg border-gray-300 text-sm font-mono focus:ring-[#385776] focus:border-[#385776]">
                    <option value="">Todos</option>
                    @foreach($filter['options'] as $val => $lbl)
                    <option value="{{ $val }}" {{ request($filter['name']) == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                    @endforeach
                </select>
                @elseif($filter['type'] === 'month')
                <input type="month" name="{{ $filter['name'] }}" value="{{ request($filter['name']) }}"
                       class="w-full rounded-lg border-gray-300 text-sm font-mono focus:ring-[#385776] focus:border-[#385776]">
                @elseif($filter['type'] === 'text')
                <input type="text" name="{{ $filter['name'] }}" value="{{ request($filter['name']) }}" placeholder="{{ $filter['placeholder'] ?? '' }}"
                       class="w-full rounded-lg border-gray-300 text-sm font-mono focus:ring-[#385776] focus:border-[#385776]">
                @elseif($filter['type'] === 'number')
                <input type="number" name="{{ $filter['name'] }}" value="{{ request($filter['name']) }}" placeholder="{{ $filter['placeholder'] ?? '' }}"
                       class="w-full rounded-lg border-gray-300 text-sm font-mono focus:ring-[#385776] focus:border-[#385776]">
                @endif
            </div>
            @endforeach
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-[#385776] hover:bg-[#1B334A] text-white text-xs font-bold font-mono rounded-lg transition-colors tracking-wider">
                    FILTRAR
                </button>
                <a href="{{ url()->current() }}" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-mono rounded-lg transition-colors">
                    LIMPAR
                </a>
            </div>
        </div>
    </form>
    @endif

    {{-- Info bar --}}
    <div class="flex items-center justify-between mb-3">
        <div class="text-xs text-gray-500 font-mono">
            @if(isset($data) && is_object($data) && method_exists($data, 'total'))
                Registros {{ $data->firstItem() ?? 0 }}-{{ $data->lastItem() ?? 0 }} de {{ $data->total() }}
            @elseif(isset($data))
                {{ is_object($data) && method_exists($data, 'count') ? $data->count() : count($data) }} registros
            @endif
        </div>
        <div class="flex items-center gap-2 text-xs font-mono">
            <span class="text-gray-500">POR PAG:</span>
            @foreach([25, 50, 100] as $pp)
            <a href="{{ request()->fullUrlWithQuery(['per_page' => $pp, 'page' => 1]) }}"
               class="px-2 py-1 rounded {{ (request('per_page', 25) == $pp) ? 'bg-[#385776] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                {{ $pp }}
            </a>
            @endforeach
        </div>
    </div>

    {{-- Tabela estilo formulário contínuo --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-300 overflow-hidden">
        {{-- Furos laterais --}}
        
        

        <div class="overflow-x-auto">
            <table class="min-w-full report-table">
                <thead>
                    <tr>
                        @foreach($columns as $col)
                        <th class="whitespace-nowrap">
                            @if(isset($col['sortable']) && $col['sortable'])
                            <a href="{{ request()->fullUrlWithQuery(['sort' => $col['key'], 'dir' => (request('sort') === $col['key'] && request('dir') === 'asc') ? 'desc' : 'asc']) }}"
                               class="flex items-center gap-1 text-white hover:text-gray-300">
                                {{ $col['label'] }}
                                @if(request('sort') === $col['key'])
                                <svg class="w-3 h-3 {{ request('dir') === 'desc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                @endif
                            </a>
                            @else
                            {{ $col['label'] }}
                            @endif
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($data ?? [] as $i => $row)
                    <tr>
                        @foreach($columns as $col)
                        <td class="whitespace-nowrap">
                            @php
                                $val = is_array($row) ? ($row[$col['key']] ?? '') : ($row->{$col['key']} ?? '');
                                $fmt = $col['format'] ?? 'text';
                            @endphp
                            @if($fmt === 'currency')
                                @php $numVal = is_numeric($val) ? (float)$val : 0; @endphp
                                <span class="{{ $numVal < 0 ? 'text-red-600 font-bold' : '' }}">R$ {{ number_format($numVal, 2, ',', '.') }}</span>
                            @elseif($fmt === 'percent')
                                {{ number_format($val * 100, 1, ',', '.') }}%
                            @elseif($fmt === 'date' && $val)
                                {{ \Carbon\Carbon::parse($val)->format('d/m/Y') }}
                            @elseif($fmt === 'badge')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold font-mono {{ $col['badge_colors'][$val] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $val }}
                                </span>
                            @else
                                {{ \Illuminate\Support\Str::limit($val, $col['limit'] ?? 200) }}
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="px-4 py-12 text-center text-gray-400 font-mono">
                            ░░░ NENHUM REGISTRO ENCONTRADO ░░░
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if(!empty($totals))
                <tfoot>
                    <tr>
                        @foreach($columns as $col)
                        <td class="whitespace-nowrap">
                            @if(isset($totals[$col['key']]))
                                @php $fmt = $col['format'] ?? 'text'; @endphp
                                @if($fmt === 'currency')
                                    R$ {{ number_format((float)($totals[$col['key']] ?? 0), 2, ',', '.') }}
                                @else
                                    {{ $totals[$col['key']] }}
                                @endif
                            @elseif($loop->first)
                                ► TOTAL
                            @endif
                        </td>
                        @endforeach
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Paginação --}}
    @if(isset($data) && is_object($data) && method_exists($data, 'links') && method_exists($data, 'hasPages') && $data->hasPages())
    <div class="mt-4">
        {{ $data->appends(request()->query())->links() }}
    </div>
    @endif

</div>
@endsection
