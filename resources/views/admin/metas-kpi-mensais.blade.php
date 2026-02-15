@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-2">📊 Planejamento de Metas</h1>
                <p class="text-gray-600 dark:text-gray-400">Balanced Scorecard - Metas Mensais e Anuais</p>
            </div>
            <div class="bg-blue-100 dark:bg-blue-900 px-6 py-3 rounded-lg">
                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Ano</label>
                <select id="ano-filter" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-[#385776] text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                    @for($y = 2020; $y <= 2030; $y++)
                        <option value="{{ $y }}" {{ $ano == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-50 dark:bg-green-900 border-l-4 border-green-500 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 dark:bg-red-900 border-l-4 border-red-500 p-4 mb-6">
                <ul class="text-sm text-red-800 dark:text-red-200">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('config.metas-kpi-mensais.store') }}" class="space-y-8">
        @csrf
        <input type="hidden" name="ano" id="ano-input" value="{{ $ano }}">

        <!-- PERSPECTIVA FINANCEIRA: RECEITA -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
            <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    💰 Perspectiva Financeira - RECEITA
                </h2>
                <p class="text-green-100 text-sm mt-1">Metas de receita por tipo de cliente (PF/PJ)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">KPI</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Jan</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Fev</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Mar</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Abr</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Mai</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Jun</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Jul</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Ago</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Set</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Out</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Nov</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Dez</th>
                            <th class="px-6 py-3 text-center text-sm font-bold text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-600">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Receita PF -->
                        <tr class="border-b border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">Receita PF</td>
                            @php $total_pf = 0; @endphp
                            @for($m = 1; $m <= 12; $m++)
                                @php 
                                    $valor = $metas->get('receita_pf_' . $m) ?? '';
                                    $total_pf += (float)$valor;
                                @endphp
                                <td class="px-4 py-4">
                                    <input 
                                        type="number" 
                                        name="metas[receita_pf][{{ $m }}]"
                                        value="{{ $valor }}"
                                        placeholder="0"
                                        class="w-full px-2 py-2 border border-gray-300 dark:border-gray-600 rounded text-center text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-green-500"
                                        step="0.01"
                                    >
                                </td>
                            @endfor
                            <td class="px-6 py-4 text-center text-sm font-bold text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-600">
                                <span class="total-pf">{{ number_format($total_pf, 2, ',', '.') }}</span>
                            </td>
                        </tr>

                        <!-- Receita PJ -->
                        <tr class="border-b border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">Receita PJ</td>
                            @php $total_pj = 0; @endphp
                            @for($m = 1; $m <= 12; $m++)
                                @php 
                                    $valor = $metas->get('receita_pj_' . $m) ?? '';
                                    $total_pj += (float)$valor;
                                @endphp
                                <td class="px-4 py-4">
                                    <input 
                                        type="number" 
                                        name="metas[receita_pj][{{ $m }}]"
                                        value="{{ $valor }}"
                                        placeholder="0"
                                        class="w-full px-2 py-2 border border-gray-300 dark:border-gray-600 rounded text-center text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-green-500"
                                        step="0.01"
                                    >
                                </td>
                            @endfor
                            <td class="px-6 py-4 text-center text-sm font-bold text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-600">
                                <span class="total-pj">{{ number_format($total_pj, 2, ',', '.') }}</span>
                            </td>
                        </tr>

                        <!-- Receita Total (Calculada) -->
                        <tr class="bg-green-50 dark:bg-green-900/20 border-b border-gray-200 dark:border-gray-600">
                            <td class="px-6 py-4 text-sm font-bold text-green-900 dark:text-green-100">📈 Receita Total</td>
                            @for($m = 1; $m <= 12; $m++)
                                <td class="px-4 py-4 text-center text-sm font-semibold text-green-900 dark:text-green-100">
                                    <span class="receita-total-mes-{{ $m }}">0,00</span>
                                </td>
                            @endfor
                            <td class="px-6 py-4 text-center text-sm font-bold text-green-900 dark:text-green-100 bg-green-100 dark:bg-green-900/40">
                                <span class="total-receita">0,00</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PERSPECTIVA FINANCEIRA: DESPESA -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
            <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    💸 Perspectiva Financeira - DESPESA
                </h2>
                <p class="text-red-100 text-sm mt-1">Metas de despesas operacionais</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">KPI</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Jan</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Fev</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Mar</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Abr</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Mai</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Jun</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Jul</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Ago</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Set</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Out</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Nov</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Dez</th>
                            <th class="px-6 py-3 text-center text-sm font-bold text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-600">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Despesas -->
                        <tr class="border-b border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">Despesas</td>
                            @php $total_despesas = 0; @endphp
                            @for($m = 1; $m <= 12; $m++)
                                @php 
                                    $valor = $metas->get('despesas_' . $m) ?? '';
                                    $total_despesas += (float)$valor;
                                @endphp
                                <td class="px-4 py-4">
                                    <input 
                                        type="number" 
                                        name="metas[despesas][{{ $m }}]"
                                        value="{{ $valor }}"
                                        placeholder="0"
                                        class="w-full px-2 py-2 border border-gray-300 dark:border-gray-600 rounded text-center text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500"
                                        step="0.01"
                                    >
                                </td>
                            @endfor
                            <td class="px-6 py-4 text-center text-sm font-bold text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-600">
                                <span class="total-despesas">{{ number_format($total_despesas, 2, ',', '.') }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PERSPECTIVA FINANCEIRA: RESULTADO -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    📊 Perspectiva Financeira - RESULTADO
                </h2>
                <p class="text-blue-100 text-sm mt-1">Resultado e rentabilidade (calculados automaticamente)</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">KPI</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Jan</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Fev</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Mar</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Abr</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Mai</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Jun</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Jul</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Ago</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Set</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Out</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Nov</th>
                            <th class="px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-white">Dez</th>
                            <th class="px-6 py-3 text-center text-sm font-bold text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-600">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Resultado Líquido -->
                        <tr class="bg-blue-50 dark:bg-blue-900/20 border-b border-gray-200 dark:border-gray-600">
                            <td class="px-6 py-4 text-sm font-bold text-blue-900 dark:text-blue-100">📈 Resultado Líquido</td>
                            @for($m = 1; $m <= 12; $m++)
                                <td class="px-4 py-4 text-center text-sm font-semibold text-blue-900 dark:text-blue-100">
                                    <span class="resultado-mes-{{ $m }}">0,00</span>
                                </td>
                            @endfor
                            <td class="px-6 py-4 text-center text-sm font-bold text-blue-900 dark:text-blue-100 bg-blue-100 dark:bg-blue-900/40">
                                <span class="total-resultado">0,00</span>
                            </td>
                        </tr>

                        <!-- Margem Líquida -->
                        <tr class="bg-blue-50 dark:bg-blue-900/20">
                            <td class="px-6 py-4 text-sm font-bold text-blue-900 dark:text-blue-100">📊 Margem Líquida (%)</td>
                            @for($m = 1; $m <= 12; $m++)
                                <td class="px-4 py-4 text-center text-sm font-semibold text-blue-900 dark:text-blue-100">
                                    <span class="margem-mes-{{ $m }}">0,00%</span>
                                </td>
                            @endfor
                            <td class="px-6 py-4 text-center text-sm font-bold text-blue-900 dark:text-blue-100 bg-blue-100 dark:bg-blue-900/40">
                                <span class="total-margem">0,00%</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PERSPECTIVA DE SAÚDE FINANCEIRA -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
            <div class="bg-gradient-to-r from-orange-600 to-orange-700 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    ⚠️ Perspectiva de Saúde Financeira
                </h2>
                <p class="text-orange-100 text-sm mt-1">Metas de cobrança e inadimplência</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6">
                <!-- Dias Médio Atraso -->
                <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Dias Médio Atraso (Meta)
                    </label>
                    <input 
                        type="number" 
                        name="metas[dias_atraso_meta]"
                        value="{{ $metas->get('dias_atraso_meta') ?? '30' }}"
                        placeholder="30"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500"
                        step="1"
                    >
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Meta recomendada: 30 dias</p>
                </div>

                <!-- Taxa de Cobrança -->
                <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Taxa de Cobrança (Meta %)
                    </label>
                    <input 
                        type="number" 
                        name="metas[taxa_cobranca_meta]"
                        value="{{ $metas->get('taxa_cobranca_meta') ?? '95' }}"
                        placeholder="95"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500"
                        step="0.1"
                        min="0"
                        max="100"
                    >
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Meta recomendada: 95%</p>
                </div>

                <!-- Inadimplência Máxima -->
                <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Inadimplência Máxima (%)
                    </label>
                    <input 
                        type="number" 
                        name="metas[inadimplencia_meta]"
                        value="{{ $metas->get('inadimplencia_meta') ?? '20' }}"
                        placeholder="20"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-orange-500"
                        step="0.1"
                        min="0"
                        max="100"
                    >
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Meta recomendada: 20%</p>
                </div>
            </div>
        </div>

        <!-- PERSPECTIVA DE EFICIÊNCIA -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
            <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    ⚡ Perspectiva de Eficiência
                </h2>
                <p class="text-purple-100 text-sm mt-1">Metas de eficiência operacional</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                <!-- Expense Ratio -->
                <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Expense Ratio Máximo (%)
                    </label>
                    <input 
                        type="number" 
                        name="metas[expense_ratio_meta]"
                        value="{{ $metas->get('expense_ratio_meta') ?? '40' }}"
                        placeholder="40"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500"
                        step="0.1"
                        min="0"
                        max="100"
                    >
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Fórmula: Despesas / Receita × 100</p>
                </div>

                <!-- Receita YoY -->
                <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                        Crescimento Receita YoY Mínimo (%)
                    </label>
                    <input 
                        type="number" 
                        name="metas[yoy_growth_meta]"
                        value="{{ $metas->get('yoy_growth_meta') ?? '5' }}"
                        placeholder="5"
                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500"
                        step="0.1"
                    >
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Meta recomendada: 5% ao ano</p>
                </div>
            </div>
        </div>

        <!-- Botão de Salvar -->
        <div class="flex justify-end gap-4 pt-8">
            <button 
                type="reset" 
                class="px-6 py-3 border border-gray-300 dark:border-gray-600 rounded-lg font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
            >
                Cancelar
            </button>
            <button 
                type="submit" 
                class="px-8 py-3 bg-brand hover-bg-brand-dark text-white font-bold rounded-lg transition shadow-lg flex items-center gap-2"
            >
                💾 Salvar Metas
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Atualizar ano no input hidden
    document.getElementById('ano-filter').addEventListener('change', function() {
        document.getElementById('ano-input').value = this.value;
        window.location.href = `?ano=${this.value}`;
    });

    // Função para calcular totais e resultados
    function calcularTotais() {
        let totalPF = 0, totalPJ = 0, totalDespesas = 0;

        // Somar Receita PF
        document.querySelectorAll('input[name="metas[receita_pf][*]"]').forEach(input => {
            totalPF += parseFloat(input.value) || 0;
        });

        // Somar Receita PJ
        document.querySelectorAll('input[name="metas[receita_pj][*]"]').forEach(input => {
            totalPJ += parseFloat(input.value) || 0;
        });

        // Somar Despesas
        document.querySelectorAll('input[name="metas[despesas][*]"]').forEach(input => {
            totalDespesas += parseFloat(input.value) || 0;
        });

        // Atualizar totais anuais
        document.querySelector('.total-pf').textContent = formatarMoeda(totalPF);
        document.querySelector('.total-pj').textContent = formatarMoeda(totalPJ);
        document.querySelector('.total-despesas').textContent = formatarMoeda(totalDespesas);
        document.querySelector('.total-receita').textContent = formatarMoeda(totalPF + totalPJ);
        document.querySelector('.total-resultado').textContent = formatarMoeda(totalPF + totalPJ - totalDespesas);

        // Calcular margem
        let receita_total = totalPF + totalPJ;
        let margem = receita_total > 0 ? ((receita_total - totalDespesas) / receita_total * 100) : 0;
        document.querySelector('.total-margem').textContent = formatarPercentual(margem);

        // Calcular por mês
        for (let m = 1; m <= 12; m++) {
            let pf = parseFloat(document.querySelector(`input[name="metas[receita_pf][${m}]"]`)?.value) || 0;
            let pj = parseFloat(document.querySelector(`input[name="metas[receita_pj][${m}]"]`)?.value) || 0;
            let desp = parseFloat(document.querySelector(`input[name="metas[despesas][${m}]"]`)?.value) || 0;
            
            let receita_mes = pf + pj;
            let resultado_mes = receita_mes - desp;
            let margem_mes = receita_mes > 0 ? (resultado_mes / receita_mes * 100) : 0;

            document.querySelector(`.receita-total-mes-${m}`).textContent = formatarMoeda(receita_mes);
            document.querySelector(`.resultado-mes-${m}`).textContent = formatarMoeda(resultado_mes);
            document.querySelector(`.margem-mes-${m}`).textContent = formatarPercentual(margem_mes);
        }
    }

    function formatarMoeda(valor) {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor);
    }

    function formatarPercentual(valor) {
        return (valor || 0).toFixed(2).replace('.', ',') + '%';
    }

    // Adicionar listeners aos inputs
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('input', calcularTotais);
    });

    // Calcular na carga da página
    calcularTotais();
});
</script>
@endsection
