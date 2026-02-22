@extends('layouts.app')
@section('title', 'Folha de Pagamento')
@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Folha de Pagamento</h1>
        <a href="{{ route('sisrh.index') }}" class="text-sm underline" style="color: #385776;">← Voltar</a>
    </div>
    <form method="GET" class="flex items-center gap-2 mb-6">
        <label class="text-sm text-gray-600">Competência:</label>
        <select name="mes" class="border rounded px-2 py-1.5 text-sm">
            @for($m = 1; $m <= 12; $m++)
            <option value="{{ $m }}" {{ $mes == $m ? 'selected' : '' }}>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
            @endfor
        </select>
        <input type="number" name="ano" value="{{ $ano }}" min="2024" max="2030" class="border rounded px-2 py-1.5 text-sm w-20">
        <button type="submit" class="px-4 py-1.5 rounded text-white text-sm" style="background-color: #385776;">Consultar</button>
        <a href="{{ route('sisrh.lancamentos') }}?ano={{ $ano }}&mes={{ $mes }}" class="ml-4 px-4 py-1.5 rounded border text-sm" style="color: #385776; border-color: #385776;">Lançamentos Manuais</a>
        <a href="{{ route('sisrh.rubricas') }}" class="px-4 py-1.5 rounded border text-sm" style="color: #385776; border-color: #385776;">Rubricas</a>
        <button type="button" onclick="window.print()" class="ml-2 px-4 py-1.5 rounded text-white text-sm" style="background-color: #385776;">🖨️ Imprimir</button>
    </form>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden" id="folha-area">
        <div class="px-5 py-3 border-b border-gray-300 text-center">
            <p class="text-sm font-bold text-gray-800 uppercase">Mayer Sociedade de Advogados - CNPJ: 18.716.288/0001-60</p>
            <p class="text-xs text-gray-500">Folha de Pagamento — Competência {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}/{{ $ano }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr style="background-color: #385776;">
                        <th class="px-2 py-2 text-left text-white whitespace-nowrap">Advogado</th>
                        <th class="px-2 py-2 text-right text-white whitespace-nowrap">Pró-labore (RB)</th>
                        <th class="px-2 py-2 text-right text-white whitespace-nowrap">RV</th>
                        <th class="px-2 py-2 text-right text-white whitespace-nowrap">Grat. Função</th>
                        <th class="px-2 py-2 text-right text-white whitespace-nowrap">Outros Prov.</th>
                        <th class="px-2 py-2 text-right text-white whitespace-nowrap font-bold">Total Prov.</th>
                        <th class="px-2 py-2 text-right text-white whitespace-nowrap">INSS (11%)</th>
                        <th class="px-2 py-2 text-right text-white whitespace-nowrap">Outros Desc.</th>
                        <th class="px-2 py-2 text-right text-white whitespace-nowrap font-bold">Total Desc.</th>
                        <th class="px-2 py-2 text-right text-white whitespace-nowrap font-bold">Líquido</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($folha['folha'] as $h)
                    @php
                        $rb = collect($h['proventos'])->firstWhere('codigo', '001')['valor'] ?? 0;
                        $rv = collect($h['proventos'])->firstWhere('codigo', '002')['valor'] ?? 0;
                        $grat = collect($h['proventos'])->firstWhere('codigo', '003')['valor'] ?? 0;
                        $outrosProv = $h['total_proventos'] - $rb - $rv - $grat;
                        $inss = collect($h['descontos'])->firstWhere('codigo', '045')['valor'] ?? 0;
                        $outrosDesc = $h['total_descontos'] - $inss;
                    @endphp
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="px-2 py-2 whitespace-nowrap font-medium text-gray-800">{{ $h['user']->name }}</td>
                        <td class="px-2 py-2 text-right text-gray-700">{{ $rb > 0 ? 'R$ '.number_format($rb,2,',','.') : '-' }}</td>
                        <td class="px-2 py-2 text-right text-gray-700">{{ $rv > 0 ? 'R$ '.number_format($rv,2,',','.') : '-' }}</td>
                        <td class="px-2 py-2 text-right text-gray-700">{{ $grat > 0 ? 'R$ '.number_format($grat,2,',','.') : '-' }}</td>
                        <td class="px-2 py-2 text-right text-gray-700">{{ $outrosProv > 0.01 ? 'R$ '.number_format($outrosProv,2,',','.') : '-' }}</td>
                        <td class="px-2 py-2 text-right font-bold text-gray-800">R$ {{ number_format($h['total_proventos'],2,',','.') }}</td>
                        <td class="px-2 py-2 text-right text-red-600">{{ $inss > 0 ? 'R$ '.number_format($inss,2,',','.') : '-' }}</td>
                        <td class="px-2 py-2 text-right text-red-600">{{ $outrosDesc > 0.01 ? 'R$ '.number_format($outrosDesc,2,',','.') : '-' }}</td>
                        <td class="px-2 py-2 text-right font-bold text-red-600">R$ {{ number_format($h['total_descontos'],2,',','.') }}</td>
                        <td class="px-2 py-2 text-right font-bold" style="color: #385776;">R$ {{ number_format($h['liquido'],2,',','.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-400" style="background-color: #f1f5f9;">
                        <td class="px-2 py-2 font-bold text-gray-800">TOTAL</td>
                        <td colspan="4"></td>
                        <td class="px-2 py-2 text-right font-bold text-gray-800">R$ {{ number_format($folha['totais']['proventos'],2,',','.') }}</td>
                        <td colspan="2"></td>
                        <td class="px-2 py-2 text-right font-bold text-red-600">R$ {{ number_format($folha['totais']['descontos'],2,',','.') }}</td>
                        <td class="px-2 py-2 text-right font-bold" style="color: #385776;">R$ {{ number_format($folha['totais']['liquido'],2,',','.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    {{-- Detalhamento --}}
    <div class="mt-6 space-y-4" x-data="{ open: null }">
        <h2 class="text-lg font-semibold text-gray-700">Detalhamento Individual</h2>
        @foreach($folha['folha'] as $idx => $h)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <button @click="open = open === {{ $idx }} ? null : {{ $idx }}" class="w-full px-4 py-3 flex justify-between items-center hover:bg-gray-50">
                <span class="font-medium text-gray-800">{{ $h['user']->name }}</span>
                <span class="text-sm" style="color: #385776;">Líquido: R$ {{ number_format($h['liquido'],2,',','.') }}</span>
            </button>
            <div x-show="open === {{ $idx }}" x-cloak class="border-t border-gray-200 px-4 py-3">
                <table class="w-full text-sm">
                    <thead><tr class="text-xs text-gray-500 uppercase border-b"><th class="text-left py-1">Descrição</th><th class="text-center py-1">Ref</th><th class="text-right py-1">Proventos</th><th class="text-right py-1">Descontos</th></tr></thead>
                    <tbody>
                        @foreach($h['proventos'] as $p)
                        <tr class="border-b border-gray-50"><td class="py-1 text-gray-700">{{ $p['nome'] }}</td><td class="py-1 text-center text-gray-500 text-xs">{{ $p['referencia'] }}</td><td class="py-1 text-right">R$ {{ number_format($p['valor'],2,',','.') }}</td><td></td></tr>
                        @endforeach
                        @foreach($h['descontos'] as $d)
                        <tr class="border-b border-gray-50"><td class="py-1 text-gray-700">{{ $d['nome'] }}</td><td class="py-1 text-center text-gray-500 text-xs">{{ $d['referencia'] }}</td><td></td><td class="py-1 text-right text-red-600">R$ {{ number_format($d['valor'],2,',','.') }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr class="border-t border-gray-300"><td class="py-1 font-semibold" colspan="2">Totais</td><td class="py-1 text-right font-semibold">R$ {{ number_format($h['total_proventos'],2,',','.') }}</td><td class="py-1 text-right font-semibold text-red-600">R$ {{ number_format($h['total_descontos'],2,',','.') }}</td></tr></tfoot>
                </table>
            </div>
        </div>
        @endforeach
    </div>
</div>
@push('styles')
<style>@media print{nav,.sidebar,header,footer,form,button,a,.no-print,[x-data]>div:not(#folha-area){display:none!important}#folha-area{box-shadow:none!important}body{background:white!important;font-size:10px!important}}</style>
@endpush
@endsection
