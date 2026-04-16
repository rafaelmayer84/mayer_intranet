@extends('layouts.app')

@section('title', 'EVIDENTIA - Custos')

@section('content')
<div class="w-full px-4 py-6">

    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('evidentia.index') }}" class="text-sm hover:underline" style="color: #385776;">← EVIDENTIA</a>
            <h1 class="text-xl font-bold text-gray-800 mt-1">Painel de Custos</h1>
        </div>
    </div>

    {{-- Budget de hoje --}}
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-600 uppercase mb-3">Budget Diário</h2>
        <div class="flex items-end gap-4">
            <div>
                <p class="text-3xl font-bold" style="color: #385776;">${{ number_format($todayBudget, 4) }}</p>
                <p class="text-sm text-gray-500">de ${{ number_format($dailyLimit, 2) }} limite</p>
            </div>
            <div class="flex-1 h-4 bg-gray-200 rounded-full overflow-hidden">
                @php $pct = $dailyLimit > 0 ? min(100, ($todayBudget / $dailyLimit) * 100) : 0; @endphp
                <div class="h-full rounded-full transition-all {{ $pct > 80 ? 'bg-red-500' : ($pct > 50 ? 'bg-amber-500' : 'bg-green-500') }}"
                     style="width: {{ $pct }}%"></div>
            </div>
            <span class="text-sm font-medium {{ $pct > 80 ? 'text-red-600' : 'text-gray-600' }}">
                {{ number_format($pct, 1) }}%
            </span>
        </div>
    </div>

    {{-- Tabela de custos por dia --}}
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-sm font-semibold text-gray-600 uppercase mb-4">Últimos 30 dias</h2>

        @if($dailyCosts->isEmpty())
            <p class="text-sm text-gray-500">Nenhuma busca registrada no período.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 text-xs font-medium text-gray-500 uppercase">Data</th>
                            <th class="text-right py-2 text-xs font-medium text-gray-500 uppercase">Buscas</th>
                            <th class="text-right py-2 text-xs font-medium text-gray-500 uppercase">Tokens In</th>
                            <th class="text-right py-2 text-xs font-medium text-gray-500 uppercase">Tokens Out</th>
                            <th class="text-right py-2 text-xs font-medium text-gray-500 uppercase">Custo USD</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dailyCosts as $day)
                            <tr class="border-b border-gray-50 hover:bg-gray-50">
                                <td class="py-2 text-gray-800">{{ \Carbon\Carbon::parse($day->dia)->format('d/m/Y') }}</td>
                                <td class="py-2 text-right text-gray-600">{{ number_format($day->total_searches) }}</td>
                                <td class="py-2 text-right text-gray-600">{{ number_format($day->total_tokens_in) }}</td>
                                <td class="py-2 text-right text-gray-600">{{ number_format($day->total_tokens_out) }}</td>
                                <td class="py-2 text-right font-medium" style="color: #385776;">${{ number_format((float)$day->total_cost, 4) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300">
                            <td class="py-2 font-bold text-gray-800">Total</td>
                            <td class="py-2 text-right font-bold text-gray-800">{{ number_format($dailyCosts->sum('total_searches')) }}</td>
                            <td class="py-2 text-right font-bold text-gray-800">{{ number_format($dailyCosts->sum('total_tokens_in')) }}</td>
                            <td class="py-2 text-right font-bold text-gray-800">{{ number_format($dailyCosts->sum('total_tokens_out')) }}</td>
                            <td class="py-2 text-right font-bold" style="color: #385776;">${{ number_format((float)$dailyCosts->sum('total_cost'), 4) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
