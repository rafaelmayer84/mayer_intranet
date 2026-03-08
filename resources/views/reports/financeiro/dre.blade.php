@extends('layouts.app')

@section('title', 'DRE — Demonstrativo de Resultado')



@section('content')
<style>
    .dre-table { font-family: "Courier New", Courier, monospace !important; border-collapse: collapse !important; width: 100%; table-layout: fixed; }
    .dre-table thead tr th {
        background-color: #1B334A !important; color: #ffffff !important;
        font-size: 0.68rem !important; letter-spacing: 1px; text-transform: uppercase;
        padding: 10px 12px !important; text-align: right; border: 1px solid #0f1f2e !important; white-space: nowrap;
    }
    .dre-table thead tr th:first-child { text-align: left !important; width: 45%; }
    .dre-table td { font-size: 0.78rem !important; padding: 6px 12px !important; text-align: right; border: 1px solid #d1d5db !important; white-space: nowrap; }
    .dre-table td:first-child { text-align: left !important; white-space: normal; }
    .dre-table tbody tr:nth-child(even):not(.dre-row-bold):not(.dre-row-section):not(.dre-row-empty) td { background-color: #dcfce7 !important; }
    .dre-table tbody tr:nth-child(odd):not(.dre-row-bold):not(.dre-row-section):not(.dre-row-empty) td { background-color: #fff !important; }
    .dre-table tbody tr:hover td { background-color: #fef3c7 !important; }
    .dre-row-bold td { font-weight: bold !important; border-top: 3px double #374151 !important; background-color: #cbd5e1 !important; font-size: 0.82rem !important; }
    .dre-row-section td { font-weight: bold !important; color: #1B334A !important; background-color: #dbeafe !important; border-bottom: 2px solid #385776 !important; letter-spacing: 1px; font-size: 0.8rem !important; text-transform: uppercase; }
    .dre-row-empty td { background: #f9fafb !important; border-left: none !important; border-right: none !important; height: 6px; }
</style>
<div class="mx-auto px-2 sm:px-4 lg:px-6 py-6">

    <nav class="flex mb-4 text-sm text-gray-500">
        <a href="{{ route('relatorios.index') }}" class="hover:text-[#385776]">Relatórios</a>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-400">Financeiro</span>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-700 font-medium">DRE</span>
    </nav>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800 font-mono tracking-wide">DRE — Demonstrativo de Resultado</h1>
            <p class="text-xs text-gray-400 font-mono mt-1">Financeiro • Sistema RESULTADOS!</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ $exportRoute }}&type=xlsx" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold font-mono rounded-lg transition-colors tracking-wider">EXCEL</a>
            <a href="{{ $exportRoute }}&type=pdf" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold font-mono rounded-lg transition-colors tracking-wider">PDF</a>
        </div>
    </div>

    @include('reports._filters', ['filters' => $filters])

    <div class="bg-white rounded-xl shadow-sm border border-gray-300 overflow-hidden">
        
        
        <div class="overflow-x-auto">
            <table class="dre-table">
                <thead>
                    <tr>
                        @foreach($columns as $col)
                        <th>{{ $col['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($data as $row)
                    @php
                        $classif = is_array($row) ? ($row['classificacao'] ?? '') : ($row->classificacao ?? '');
                        $isBold = is_array($row) ? ($row['is_bold'] ?? false) : ($row->is_bold ?? false);
                        $rubrica = is_array($row) ? ($row['rubrica'] ?? '') : ($row->rubrica ?? '');
                        $isSection = $classif === 'SECAO' && $rubrica !== '';
                        $isEmpty = $classif === 'SECAO' && $rubrica === '';
                    @endphp
                    <tr class="{{ $isEmpty ? 'dre-row-empty' : ($isSection ? 'dre-row-section' : ($isBold ? 'dre-row-bold' : '')) }}">
                        @foreach($columns as $col)
                        <td>
                            @php
                                $val = is_array($row) ? ($row[$col['key']] ?? '') : ($row->{$col['key']} ?? '');
                            @endphp
                            @if($col['key'] === 'rubrica')
                                {{ $val }}
                            @elseif($val === '' || $isEmpty || $isSection)
                                {{-- vazio --}}
                            @elseif($classif === 'MARGEM')
                                {{ number_format($val, 1, ',', '.') }}%
                            @elseif(($col['format'] ?? '') === 'currency' && is_numeric($val))
                                <span class="{{ $val < 0 ? 'text-red-600' : '' }}">R$ {{ number_format($val, 2, ',', '.') }}</span>
                            @else
                                {{ $val }}
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
