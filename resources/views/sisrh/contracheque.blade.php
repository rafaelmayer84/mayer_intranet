@extends('layouts.app')
@section('title', 'Meu Contracheque')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Meu Contracheque</h1>
        <form method="GET" class="flex items-center gap-2 ml-auto">
            <select name="mes" class="border rounded px-2 py-1.5 text-sm">
                @for($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" {{ $mes == $m ? 'selected' : '' }}>{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                @endfor
            </select>
            <input type="number" name="ano" value="{{ $ano }}" min="2024" max="2030" class="border rounded px-2 py-1.5 text-sm w-20">
            <button type="submit" class="px-4 py-1.5 rounded text-white text-sm" style="background-color: #385776;">Consultar</button>
        </form>
    </div>
    <div class="bg-white border border-gray-300 rounded-lg shadow-sm" id="contracheque-area">
        <div class="border-b border-gray-300 p-5 text-center">
            <p class="text-sm font-bold text-gray-800 uppercase tracking-wide">Mayer Sociedade de Advogados - CNPJ: 18.716.288/0001-60</p>
            <p class="text-xs text-gray-500 mt-0.5">Avenida Marcos Konder, 1207, 62, Centro, Itajaí, SC, CEP 88301-980</p>
            <p class="text-xs text-gray-500">OAB SC 2097</p>
        </div>
        <div class="flex justify-between items-center px-5 py-3 border-b border-gray-200">
            <span class="text-sm font-semibold text-gray-700">Recibo de Pagamento</span>
            <span class="text-sm text-gray-600">Mês: <strong>{{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}/{{ $ano }}</strong></span>
        </div>
        <div class="px-5 py-3 border-b border-gray-200">
            <p class="text-xs text-gray-500 uppercase">Sócio/Advogado/Contribuinte Individual</p>
            <p class="text-sm font-semibold text-gray-800">{{ $holerite['user']->name ?? '—' }}</p>
        </div>
        <div class="px-5 py-3">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-300 text-xs text-gray-500 uppercase">
                        <th class="text-left py-2 w-2/5">Descrição</th>
                        <th class="text-center py-2 w-1/6">Referência</th>
                        <th class="text-right py-2 w-1/5">Proventos</th>
                        <th class="text-right py-2 w-1/5">Descontos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($holerite['proventos'] as $p)
                    <tr class="border-b border-gray-100">
                        <td class="py-1.5 text-gray-700">{{ $p['nome'] }}</td>
                        <td class="py-1.5 text-center text-gray-500">{{ $p['referencia'] }}</td>
                        <td class="py-1.5 text-right text-gray-800">R$ {{ number_format($p['valor'], 2, ',', '.') }}</td>
                        <td class="py-1.5"></td>
                    </tr>
                    @endforeach
                    @foreach($holerite['descontos'] as $d)
                    <tr class="border-b border-gray-100">
                        <td class="py-1.5 text-gray-700">{{ $d['nome'] }}</td>
                        <td class="py-1.5 text-center text-gray-500">{{ $d['referencia'] }}</td>
                        <td class="py-1.5"></td>
                        <td class="py-1.5 text-right text-red-600">R$ {{ number_format($d['valor'], 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                    @foreach($holerite['informativos'] ?? [] as $info)
                    <tr class="border-b border-gray-100 bg-amber-50">
                        <td class="py-1.5 text-gray-500 italic text-xs">{{ $info['nome'] }}</td>
                        <td class="py-1.5 text-center text-gray-400 text-xs">{{ $info['referencia'] }}</td>
                        <td colspan="2" class="py-1.5 text-right text-gray-400 italic text-xs">R$ {{ number_format($info['valor'], 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                    @if(empty($holerite['proventos']) && empty($holerite['descontos']))
                    <tr><td colspan="4" class="py-4 text-center text-gray-400 text-sm">Nenhum lançamento para esta competência.</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div class="border-t-2 border-gray-300 px-5 py-3">
            <div class="flex justify-between text-sm">
                <div>
                    <span class="text-gray-500 text-xs uppercase">Total dos Vencimentos</span>
                    <p class="font-semibold text-gray-800">R$ {{ number_format($holerite['total_proventos'], 2, ',', '.') }}</p>
                </div>
                <div class="text-right">
                    <span class="text-gray-500 text-xs uppercase">Total de Descontos</span>
                    <p class="font-semibold text-red-600">R$ {{ number_format($holerite['total_descontos'], 2, ',', '.') }}</p>
                </div>
            </div>
        </div>
        <div class="border-t-2 border-gray-800 px-5 py-4" style="background-color: #f8fafc;">
            <div class="flex justify-between items-center">
                <p class="text-xs text-gray-500">Pagamento em conta corrente por meio de chave PIX</p>
                <div class="text-right">
                    <span class="text-xs text-gray-500 uppercase">Líquido a Receber</span>
                    <p class="text-xl font-bold" style="color: #385776;">R$ {{ number_format($holerite['liquido'], 2, ',', '.') }}</p>
                </div>
            </div>
        </div>
    </div>
    <div class="flex justify-end mt-4">
        <button onclick="window.print()" class="px-4 py-2 rounded text-white text-sm" style="background-color: #385776;">🖨️ Imprimir</button>
    </div>
</div>
@push('styles')
<style>
@media print { nav,.sidebar,header,footer,form,button,.no-print{display:none!important} #contracheque-area{border:1px solid #000!important;box-shadow:none!important} body{background:white!important} }
</style>
@endpush
@endsection
