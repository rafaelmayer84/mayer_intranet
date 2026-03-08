@extends('layouts.app')
@section('title', 'Pipeline de Oportunidades')
@section('content')
<style>
    .report-table { font-family: "Courier New", Courier, monospace !important; border-collapse: collapse !important; width: 100%; }
    .report-table thead tr th { background-color: #1B334A !important; color: #ffffff !important; font-size: 0.7rem !important; letter-spacing: 1px; text-transform: uppercase; padding: 10px 12px !important; border: 1px solid #0f1f2e !important; white-space: nowrap; }
    .report-table thead tr th a, .report-table thead tr th a:hover { color: #ffffff !important; text-decoration: none !important; }
    .report-table tbody tr:nth-child(even) td { background-color: #dcfce7 !important; }
    .report-table tbody tr:nth-child(odd) td { background-color: #ffffff !important; }
    .report-table tbody tr:hover td { background-color: #fef3c7 !important; }
    .report-table td { font-size: 0.78rem !important; padding: 6px 10px !important; border: 1px solid #d1d5db !important; }
</style>
<div class="mx-auto px-2 sm:px-4 lg:px-6 py-6">
    <nav class="flex mb-4 text-sm text-gray-500">
        <a href="{{ route('relatorios.index') }}" class="hover:text-[#385776]">Relatórios</a>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-400">CRM / Clientes</span>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-700 font-medium">Pipeline</span>
    </nav>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800 font-mono tracking-wide">Pipeline de Oportunidades</h1>
            <p class="text-xs text-gray-400 font-mono mt-1">CRM / Clientes • {{ $stats['total'] }} oportunidades</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ $exportRoute }}&type=xlsx" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold font-mono rounded-lg transition-colors tracking-wider">EXCEL</a>
            <a href="{{ $exportRoute }}&type=pdf" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold font-mono rounded-lg transition-colors tracking-wider">PDF</a>
        </div>
    </div>

    {{-- Pipeline Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3 mb-5">
        <div class="bg-white rounded-xl border border-gray-200 p-3 text-center">
            <div class="text-xl font-bold font-mono text-gray-800">{{ $stats['total'] }}</div>
            <div class="text-xs text-gray-500 font-mono">TOTAL</div>
        </div>
        <div class="bg-blue-50 rounded-xl border border-blue-200 p-3 text-center">
            <div class="text-xl font-bold font-mono text-blue-700">{{ $stats['open'] }}</div>
            <div class="text-xs text-blue-600 font-mono">ABERTOS</div>
        </div>
        <div class="bg-emerald-50 rounded-xl border border-emerald-200 p-3 text-center">
            <div class="text-xl font-bold font-mono text-emerald-700">{{ $stats['won'] }}</div>
            <div class="text-xs text-emerald-600 font-mono">GANHOS</div>
        </div>
        <div class="bg-red-50 rounded-xl border border-red-200 p-3 text-center">
            <div class="text-xl font-bold font-mono text-red-700">{{ $stats['lost'] }}</div>
            <div class="text-xs text-red-600 font-mono">PERDIDOS</div>
        </div>
        <div class="bg-green-50 rounded-xl border border-green-200 p-3 text-center">
            <div class="text-xl font-bold font-mono text-green-700">{{ $stats['taxa_conversao'] }}%</div>
            <div class="text-xs text-green-600 font-mono">CONVERSÃO</div>
        </div>
        <div class="bg-amber-50 rounded-xl border border-amber-200 p-3 text-center">
            <div class="text-lg font-bold font-mono text-amber-700">R$ {{ number_format($stats['valor_ganho'], 0, ',', '.') }}</div>
            <div class="text-xs text-amber-600 font-mono">VALOR GANHO</div>
        </div>
        <div class="bg-violet-50 rounded-xl border border-violet-200 p-3 text-center">
            <div class="text-lg font-bold font-mono text-violet-700">R$ {{ number_format($stats['valor_pipeline'], 0, ',', '.') }}</div>
            <div class="text-xs text-violet-600 font-mono">EM PIPELINE</div>
        </div>
        <div class="bg-gray-50 rounded-xl border border-gray-200 p-3 text-center">
            <div class="text-xl font-bold font-mono text-gray-700">{{ $stats['dias_medio_conversao'] }}d</div>
            <div class="text-xs text-gray-500 font-mono">DIAS P/ FECHAR</div>
        </div>
    </div>

    @include('reports._filters', ['filters' => $filters])

    <div class="flex items-center justify-between mb-3">
        <div class="text-xs text-gray-500 font-mono">
            @if(is_object($data) && method_exists($data, 'total')) Registros {{ $data->firstItem() ?? 0 }}-{{ $data->lastItem() ?? 0 }} de {{ $data->total() }} @endif
        </div>
        <div class="flex items-center gap-2 text-xs font-mono">
            <span class="text-gray-500">POR PAG:</span>
            @foreach([25, 50, 100] as $pp)
            <a href="{{ request()->fullUrlWithQuery(['per_page' => $pp, 'page' => 1]) }}" class="px-2 py-1 rounded {{ (request('per_page', 25) == $pp) ? 'bg-[#385776] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">{{ $pp }}</a>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full report-table">
                <thead><tr>
                    @foreach($columns as $col)
                    <th>@if(isset($col['sortable']) && $col['sortable'])<a href="{{ request()->fullUrlWithQuery(['sort' => $col['key'], 'dir' => (request('sort') === $col['key'] && request('dir') === 'asc') ? 'desc' : 'asc']) }}">{{ $col['label'] }}@if(request('sort') === $col['key']) {{ request('dir') === 'desc' ? '▼' : '▲' }}@endif</a>@else{{ $col['label'] }}@endif</th>
                    @endforeach
                </tr></thead>
                <tbody>
                @forelse($data as $row)
                <tr>
                    @foreach($columns as $col)
                    <td class="whitespace-nowrap">
                        @php $val = is_array($row) ? ($row[$col['key']] ?? '') : ($row->{$col['key']} ?? ''); $fmt = $col['format'] ?? 'text'; @endphp
                        @if($fmt === 'currency' && is_numeric($val)) R$ {{ number_format((float)$val, 2, ',', '.') }}
                        @elseif($fmt === 'date' && $val) {{ \Carbon\Carbon::parse($val)->format('d/m/Y') }}
                        @elseif($fmt === 'badge') <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold font-mono {{ $col['badge_colors'][$val] ?? 'bg-gray-100 text-gray-600' }}">{{ $val }}</span>
                        @else {{ \Illuminate\Support\Str::limit($val, $col['limit'] ?? 200) }}
                        @endif
                    </td>
                    @endforeach
                </tr>
                @empty
                <tr><td colspan="{{ count($columns) }}" class="px-4 py-12 text-center text-gray-400 font-mono">NENHUM REGISTRO ENCONTRADO</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if(is_object($data) && method_exists($data, 'links') && $data->hasPages())
    <div class="mt-4">{{ $data->appends(request()->query())->links() }}</div>
    @endif
</div>
@endsection
