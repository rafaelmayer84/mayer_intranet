@extends('layouts.app')

@section('title', 'Estatísticas de Captura')



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
        <span class="text-gray-400">Jurisprudência</span>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-700 font-medium">Estatísticas de Captura</span>
    </nav>

    <h1 class="text-xl font-bold text-gray-800 font-mono tracking-wide mb-5">Estatísticas de Captura — Base Jurisprudencial</h1>

    {{-- Big number --}}
    <div class="bg-gradient-to-r from-indigo-600 to-indigo-800 rounded-2xl p-6 mb-6 shadow-lg">
        <div class="text-center">
            <div class="text-4xl font-bold font-mono text-white">{{ number_format($totalGeral, 0, ',', '.') }}</div>
            <div class="text-indigo-200 text-sm font-mono mt-1">ACÓRDÃOS INDEXADOS — {{ count($data) }} TRIBUNAIS</div>
        </div>
    </div>

    {{-- Cards por tribunal --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-{{ count($data) }} gap-4 mb-6">
        @php
            $tribunalColors = [
                'TJSC' => ['from-blue-500', 'to-blue-700', 'text-blue-100'],
                'STJ' => ['from-emerald-500', 'to-emerald-700', 'text-emerald-100'],
                'TRT12' => ['from-amber-500', 'to-amber-700', 'text-amber-100'],
                'TRF4/Outros' => ['from-violet-500', 'to-violet-700', 'text-violet-100'],
            ];
        @endphp
        @foreach($data as $row)
        @php $tc = $tribunalColors[$row['tribunal']] ?? ['from-gray-500', 'to-gray-700', 'text-gray-100']; @endphp
        <div class="bg-gradient-to-br {{ $tc[0] }} {{ $tc[1] }} rounded-xl p-5 shadow-md">
            <div class="text-white font-bold font-mono text-lg">{{ $row['tribunal'] }}</div>
            <div class="text-3xl font-bold font-mono text-white mt-2">{{ number_format($row['total'], 0, ',', '.') }}</div>
            <div class="{{ $tc[2] }} text-xs font-mono mt-2 space-y-1">
                <div>Fonte: {{ $row['fonte'] }}</div>
                <div>Período: {{ $row['periodo_de'] ? \Carbon\Carbon::parse($row['periodo_de'])->format('m/Y') : '?' }} — {{ $row['periodo_ate'] ? \Carbon\Carbon::parse($row['periodo_ate'])->format('m/Y') : '?' }}</div>
                <div>Novos este mês: <span class="text-white font-bold">{{ $row['novos_mes'] }}</span></div>
                <div>Últ. import.: {{ $row['ultima_importacao'] ? \Carbon\Carbon::parse($row['ultima_importacao'])->format('d/m/Y H:i') : '-' }}</div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Tabela detalhada --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-300 overflow-hidden">
        
        
        <div class="overflow-x-auto">
            <table class="min-w-full report-table">
                <thead>
                    <tr>
                        @foreach($columns as $col)
                        <th class="whitespace-nowrap">{{ $col['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($data as $row)
                    <tr>
                        @foreach($columns as $col)
                        <td class="whitespace-nowrap">
                            @php
                                $val = is_array($row) ? ($row[$col['key']] ?? '') : ($row->{$col['key']} ?? '');
                                $fmt = $col['format'] ?? 'text';
                            @endphp
                            @if($fmt === 'badge')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold font-mono {{ $col['badge_colors'][$val] ?? 'bg-gray-100 text-gray-600' }}">{{ $val }}</span>
                            @elseif($fmt === 'date' && $val)
                                {{ \Carbon\Carbon::parse($val)->format('d/m/Y') }}
                            @else
                                {{ $val }}
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
                @if(!empty($totals))
                <tfoot>
                    <tr>
                        @foreach($columns as $col)
                        <td class="whitespace-nowrap">
                            @if(isset($totals[$col['key']]))
                                <strong>{{ number_format($totals[$col['key']], 0, ',', '.') }}</strong>
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

</div>
@endsection
