@extends('layouts.app')

@section('title', 'Metas KPI')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">🎯 Metas KPI</h1>
            <p class="text-sm text-gray-500 mt-1">Gerencie as metas mensais dos dashboards RESULTADOS!</p>
        </div>
        <div class="flex items-center gap-3">
            {{-- Seletor de ano --}}
            <form method="GET" action="{{ route('admin.metas-kpi.index') }}" class="flex items-center gap-2">
                <select name="ano" onchange="this.form.submit()"
                    class="rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                    @foreach($anosDisponiveis as $a)
                        <option value="{{ $a }}" {{ $a == $ano ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-700">
            ✅ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700">
            ❌ {{ session('error') }}
        </div>
    @endif

    {{-- Cards Resumo --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">KPIs com Meta</div>
            <div class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['total_kpis'] }}</div>
            <div class="text-xs text-gray-400 mt-1">de 17 disponíveis</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Metas Definidas</div>
            <div class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['total_metas'] }}</div>
            <div class="text-xs text-gray-400 mt-1">registros mês a mês</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Módulos</div>
            <div class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['modulos'] }}</div>
            <div class="text-xs text-gray-400 mt-1">de 3 dashboards</div>
        </div>
    </div>

    {{-- Ações: Upload + Template + Limpar --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Importação via Planilha</h2>
        <div class="flex flex-wrap items-end gap-4">
            {{-- Upload --}}
            <form method="POST" action="{{ route('admin.metas-kpi.upload') }}" enctype="multipart/form-data"
                  class="flex items-end gap-3">
                @csrf
                <input type="hidden" name="ano" value="{{ $ano }}">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Arquivo XLSX</label>
                    <input type="file" name="arquivo" accept=".xlsx,.xls" required
                        class="block text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg
                               file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700
                               hover:file:bg-blue-100 cursor-pointer">
                </div>
                <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium
                           rounded-lg hover:bg-blue-700 transition-colors">
                    📤 Enviar e Pré-visualizar
                </button>
            </form>

            {{-- Download Template --}}
            <a href="{{ route('admin.metas-kpi.template') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium
                      rounded-lg hover:bg-gray-200 transition-colors">
                📥 Baixar Template {{ $ano }}
            </a>

            {{-- Limpar Ano --}}
            @if($stats['total_metas'] > 0)
                <form method="POST" action="{{ route('admin.metas-kpi.limpar') }}"
                      onsubmit="return confirm('Tem certeza? Isso removerá TODAS as {{ $stats['total_metas'] }} metas de {{ $ano }}.')">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="ano" value="{{ $ano }}">
                    <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 text-red-600 text-sm font-medium
                               rounded-lg hover:bg-red-100 transition-colors">
                        🗑️ Limpar {{ $ano }}
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Tabela de Metas Vigentes --}}
    @if(empty($agrupado))
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
            <div class="text-4xl mb-3">📋</div>
            <h3 class="text-lg font-medium text-gray-700">Nenhuma meta definida para {{ $ano }}</h3>
            <p class="text-sm text-gray-400 mt-1">Baixe o template, preencha as metas e faça upload.</p>
        </div>
    @else
        @php
            $moduloLabels = [
                'financeiro' => ['label' => 'Dashboard Financeiro', 'icon' => '💰', 'color' => 'blue'],
                'clientes_mercado' => ['label' => 'Clientes & Mercado', 'icon' => '👥', 'color' => 'purple'],
                'processos_internos' => ['label' => 'Processos Internos', 'icon' => '⚙️', 'color' => 'amber'],
            ];
            $mesesAbrev = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        @endphp

        @foreach($agrupado as $modulo => $kpis)
            @php $ml = $moduloLabels[$modulo] ?? ['label' => $modulo, 'icon' => '📊', 'color' => 'gray']; @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6 overflow-hidden">
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">
                        {{ $ml['icon'] }} {{ $ml['label'] }}
                        <span class="text-xs font-normal text-gray-400 ml-2">({{ count($kpis) }} KPIs)</span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase w-52">KPI</th>
                                <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 w-12">Tipo</th>
                                @for($m = 1; $m <= 12; $m++)
                                    <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 w-20">{{ $mesesAbrev[$m] }}</th>
                                @endfor
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($kpis as $kpiKey => $kpiData)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-2 font-medium text-gray-700">
                                        {{ $kpiData['descricao'] }}
                                        <span class="text-xs text-gray-400 block">{{ $kpiKey }}</span>
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        @if($kpiData['tipo_meta'] === 'max')
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-50 text-red-600">↓ teto</span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-50 text-green-600">↑ piso</span>
                                        @endif
                                    </td>
                                    @for($m = 1; $m <= 12; $m++)
                                        <td class="px-2 py-2 text-center text-gray-600">
                                            @if(isset($kpiData['meses'][$m]))
                                                @if($kpiData['unidade'] === 'BRL')
                                                    {{ number_format((float)$kpiData['meses'][$m], 0, ',', '.') }}
                                                @elseif($kpiData['unidade'] === 'PCT')
                                                    {{ number_format((float)$kpiData['meses'][$m], 1, ',', '.') }}%
                                                @else
                                                    {{ number_format((float)$kpiData['meses'][$m], 0, ',', '.') }}
                                                @endif
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    @endfor
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif

</div>
@endsection
