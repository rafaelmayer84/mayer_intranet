@extends('layouts.app')

@section('title', 'Eventos do Sistema')



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

    <nav class="flex mb-4 text-sm text-gray-500">
        <a href="{{ route('relatorios.index') }}" class="hover:text-[#385776]">Relatórios</a>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-400">Saúde do Sistema</span>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-700 font-medium">Eventos do Sistema</span>
    </nav>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800 font-mono tracking-wide">Eventos do Sistema</h1>
            <p class="text-xs text-gray-400 font-mono mt-1">Saúde do Sistema • Log centralizado de operações</p>
        </div>
        <div class="flex items-center gap-2">
            @if(isset($exportRoute))
            <a href="{{ $exportRoute }}&type=xlsx" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold font-mono rounded-lg transition-colors tracking-wider">EXCEL</a>
            <a href="{{ $exportRoute }}&type=pdf" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold font-mono rounded-lg transition-colors tracking-wider">PDF</a>
            @endif
        </div>
    </div>

    {{-- Severity Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <div class="text-2xl font-bold font-mono text-gray-800">{{ $stats['total'] }}</div>
            <div class="text-xs text-gray-500 font-mono mt-1">TOTAL</div>
        </div>
        <div class="bg-blue-50 rounded-xl border border-blue-200 p-4 text-center">
            <div class="text-2xl font-bold font-mono text-blue-700">{{ $stats['info'] }}</div>
            <div class="text-xs text-blue-600 font-mono mt-1">INFO</div>
        </div>
        <div class="bg-yellow-50 rounded-xl border border-yellow-200 p-4 text-center">
            <div class="text-2xl font-bold font-mono text-yellow-700">{{ $stats['warning'] }}</div>
            <div class="text-xs text-yellow-600 font-mono mt-1">WARNING</div>
        </div>
        <div class="bg-red-50 rounded-xl border border-red-200 p-4 text-center">
            <div class="text-2xl font-bold font-mono text-red-700">{{ $stats['error'] }}</div>
            <div class="text-xs text-red-600 font-mono mt-1">ERROR</div>
        </div>
        <div class="bg-red-100 rounded-xl border border-red-300 p-4 text-center">
            <div class="text-2xl font-bold font-mono text-red-900">{{ $stats['critical'] }}</div>
            <div class="text-xs text-red-700 font-mono mt-1">CRITICAL</div>
        </div>
    </div>

    @include('reports._filters', ['filters' => $filters])

    <div class="flex items-center justify-between mb-3">
        <div class="text-xs text-gray-500 font-mono">
            @if(method_exists($data, 'total'))
                Registros {{ $data->firstItem() ?? 0 }}-{{ $data->lastItem() ?? 0 }} de {{ $data->total() }}
            @endif
        </div>
        <div class="flex items-center gap-2 text-xs font-mono">
            <span class="text-gray-500">POR PAG:</span>
            @foreach([25, 50, 100] as $pp)
            <a href="{{ request()->fullUrlWithQuery(['per_page' => $pp, 'page' => 1]) }}"
               class="px-2 py-1 rounded {{ (request('per_page', 25) == $pp) ? 'bg-[#385776] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">{{ $pp }}</a>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-300 overflow-hidden">
        
        
        <div class="overflow-x-auto">
            <table class="min-w-full report-table">
                <thead>
                    <tr>
                        @foreach($columns as $col)
                        <th class="whitespace-nowrap">
                            @if(isset($col['sortable']) && $col['sortable'])
                            <a href="{{ request()->fullUrlWithQuery(['sort' => $col['key'], 'dir' => (request('sort') === $col['key'] && request('dir') === 'asc') ? 'desc' : 'asc']) }}" class="flex items-center gap-1 text-white hover:text-gray-300">
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
                    @forelse($data as $row)
                    <tr>
                        @foreach($columns as $col)
                        <td class="whitespace-nowrap">
                            @php
                                $val = is_array($row) ? ($row[$col['key']] ?? '') : ($row->{$col['key']} ?? '');
                                $fmt = $col['format'] ?? 'text';
                            @endphp
                            @if($fmt === 'badge')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold font-mono {{ $col['badge_colors'][$val] ?? 'bg-gray-100 text-gray-600' }}">{{ $val }}</span>
                            @else
                                {{ \Illuminate\Support\Str::limit($val, $col['limit'] ?? 200) }}
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @empty
                    <tr><td colspan="{{ count($columns) }}" class="px-4 py-12 text-center text-gray-400 font-mono">░░░ NENHUM REGISTRO ░░░</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(method_exists($data, 'links'))
    <div class="mt-4">{{ $data->appends(request()->query())->links() }}</div>
    @endif
</div>
@endsection
