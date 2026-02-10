@extends('layouts.app')

@section('title', 'Equipe - Intranet Mayer')
@section('header', 'Ranking da Equipe - ' . $ano)

@section('content')
<div class="space-y-6">
    <!-- Ranking Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Ranking de Performance</h2>
            <p class="text-sm text-gray-500">Classificação dos advogados por faturamento</p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Posição</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Advogado</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Faturamento</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Processos</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Atividades</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Horas</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($ranking as $index => $advogado)
                    <tr class="{{ $index < 3 ? 'bg-yellow-50' : '' }}">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                @if($index == 0)
                                    <span class="w-8 h-8 bg-yellow-400 rounded-full flex items-center justify-center text-white font-bold">1</span>
                                @elseif($index == 1)
                                    <span class="w-8 h-8 bg-gray-400 rounded-full flex items-center justify-center text-white font-bold">2</span>
                                @elseif($index == 2)
                                    <span class="w-8 h-8 bg-amber-600 rounded-full flex items-center justify-center text-white font-bold">3</span>
                                @else
                                    <span class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center text-gray-600 font-medium">{{ $index + 1 }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                    {{ substr($advogado['nome'], 0, 1) }}
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $advogado['nome'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <span class="text-sm font-medium text-green-600">R$ {{ number_format($advogado['faturamento'], 2, ',', '.') }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <span class="text-sm text-gray-900">{{ number_format($advogado['processos']) }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <span class="text-sm text-gray-900">{{ number_format($advogado['atividades']) }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <span class="text-sm text-gray-900">{{ number_format($advogado['horas'], 1, ',', '.') }}h</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $advogado['score'] >= 70 ? 'bg-green-100 text-green-800' : ($advogado['score'] >= 40 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                {{ $advogado['score'] }} pts
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                            Nenhum advogado encontrado
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Top 3 Cards -->
    @if(count($ranking) >= 3)
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach(array_slice($ranking, 0, 3) as $index => $top)
        <div class="bg-white rounded-xl shadow-sm p-6 {{ $index == 0 ? 'ring-2 ring-yellow-400' : '' }}">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <div class="w-12 h-12 {{ $index == 0 ? 'bg-yellow-400' : ($index == 1 ? 'bg-gray-400' : 'bg-amber-600') }} rounded-full flex items-center justify-center text-white text-xl font-bold">
                        {{ $index + 1 }}
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900">{{ $top['nome'] }}</p>
                        <p class="text-xs text-gray-500">{{ $index == 0 ? 'Líder' : ($index == 1 ? '2º Lugar' : '3º Lugar') }}</p>
                    </div>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Faturamento</span>
                    <span class="font-medium text-green-600">R$ {{ number_format($top['faturamento'], 2, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Processos</span>
                    <span class="font-medium">{{ $top['processos'] }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Score</span>
                    <span class="font-medium">{{ $top['score'] }} pts</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
