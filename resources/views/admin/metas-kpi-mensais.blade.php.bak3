@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Metas de KPIs</h1>
        <p class="text-gray-600 mb-6">Planejamento anual e mensal de metas financeiras</p>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('config.metas-kpi-mensais.store') }}" class="space-y-6">
            @csrf

            <!-- SELETOR DE ANO -->
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Ano</label>
                <select name="ano" class="block w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @for($y = 2020; $y <= 2030; $y++)
                        <option value="{{ $y }}" {{ $ano == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            <!-- TABELA DE METAS ANUAIS -->
            <div class="overflow-x-auto mb-8">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Metas Anuais</h2>
                <table class="w-full border-collapse border border-gray-300">
                    <thead>
                        <tr class="bg-blue-600 text-white">
                            <th class="border border-gray-300 px-4 py-2 text-left font-semibold">KPI</th>
                            <th class="border border-gray-300 px-4 py-2 text-center font-semibold">{{ $ano }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $kpisAnuais = [
                                'receita_total_ano' => 'Receita Total',
                                'despesa_total_ano' => 'Despesa Total',
                                'resultado_liquido_ano' => 'Resultado Líquido',
                                'margem_liquida_ano' => 'Margem Líquida (%)',
                            ];
                        @endphp
                        @foreach($kpisAnuais as $key => $label)
                            <tr class="hover:bg-gray-50">
                                <td class="border border-gray-300 px-4 py-2 font-medium text-gray-700">{{ $label }}</td>
                                <td class="border border-gray-300 px-4 py-2">
                                    <input 
                                        type="text" 
                                        name="metas[{{ $key }}][{{ $ano }}]"
                                        value="{{ $metas->get($key . '_' . $ano) ?? '' }}"
                                        placeholder="Indisponível"
                                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- TABELA DE METAS MENSAIS -->
            <div class="overflow-x-auto">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Metas Mensais - {{ $ano }}</h2>
                <table class="w-full border-collapse border border-gray-300 text-sm">
                    <thead>
                        <tr class="bg-blue-600 text-white">
                            <th class="border border-gray-300 px-3 py-2 text-left font-semibold">KPI</th>
                            @foreach(['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'] as $i => $mes)
                                <th class="border border-gray-300 px-2 py-2 text-center font-semibold">{{ $mes }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $kpisMensais = [
                                'receita_pf' => 'Receita PF',
                                'receita_pj' => 'Receita PJ',
                                'despesas' => 'Despesas',
                            ];
                        @endphp
                        @foreach($kpisMensais as $key => $label)
                            <tr class="hover:bg-gray-50">
                                <td class="border border-gray-300 px-3 py-2 font-medium text-gray-700 bg-gray-100">{{ $label }}</td>
                                @for($mes = 1; $mes <= 12; $mes++)
                                    <td class="border border-gray-300 px-2 py-2">
                                        <input 
                                            type="text" 
                                            name="metas[{{ $key }}][{{ $mes }}]"
                                            value="{{ $metas->get($key . '_' . $mes) ?? '' }}"
                                            placeholder="—"
                                            class="w-full px-2 py-1 border border-gray-300 rounded text-center text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        >
                                    </td>
                                @endfor
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- BOTÃO DE SALVAR -->
            <div class="flex justify-end mt-8">
                <button 
                    type="submit" 
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition duration-200"
                >
                    💾 Salvar Metas
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
