@extends('layouts.app')

@section('title', 'Classificação Manual')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-white">Classificação Manual</h1>
            <p class="text-gray-400 mt-1">Classifique os movimentos pendentes como PF, PJ ou Receita Financeira.</p>
        </div>
        <a href="{{ route('sync.index') }}" class="bg-gray-700 hover:bg-gray-600 text-white font-medium px-6 py-2 rounded-lg transition-colors">
            Voltar para Sincronização
        </a>
    </div>

    <!-- Mensagem de Sucesso -->
    @if(session('success'))
        <div class="bg-green-600/20 border border-green-500 text-green-400 px-4 py-3 rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <!-- Filtro de Período -->
    <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        <form method="get" class="flex items-center space-x-4">
            <div>
                <label class="block text-gray-400 text-sm mb-1">Mês</label>
                <select name="mes" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ $m == $mes ? 'selected' : '' }}>
                            {{ str_pad($m, 2, '0', STR_PAD_LEFT) }} - {{ ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'][$m-1] }}
                        </option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-gray-400 text-sm mb-1">Ano</label>
                <select name="ano" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500">
                    @for($y = 2020; $y <= date('Y') + 1; $y++)
                        <option value="{{ $y }}" {{ $y == $ano ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="pt-6">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2 rounded-lg transition-colors">
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    <!-- Resumo do Período -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-gray-800 rounded-xl p-5 border border-gray-700">
            <h3 class="text-green-400 text-sm font-medium mb-2">Receita PF</h3>
            <p class="text-xl font-bold text-white">R$ {{ number_format($resumo['receita_pf'] ?? 0, 2, ',', '.') }}</p>
            <p class="text-xs text-gray-500">{{ $resumo['qtd_pf'] ?? 0 }} registros</p>
        </div>
        <div class="bg-gray-800 rounded-xl p-5 border border-gray-700">
            <h3 class="text-blue-400 text-sm font-medium mb-2">Receita PJ</h3>
            <p class="text-xl font-bold text-white">R$ {{ number_format($resumo['receita_pj'] ?? 0, 2, ',', '.') }}</p>
            <p class="text-xs text-gray-500">{{ $resumo['qtd_pj'] ?? 0 }} registros</p>
        </div>
        <div class="bg-gray-800 rounded-xl p-5 border border-gray-700">
            <h3 class="text-purple-400 text-sm font-medium mb-2">Receita Financeira</h3>
            <p class="text-xl font-bold text-white">R$ {{ number_format($resumo['receita_financeira'] ?? 0, 2, ',', '.') }}</p>
            <p class="text-xs text-gray-500">{{ $resumo['qtd_financeira'] ?? 0 }} registros</p>
        </div>
        <div class="bg-gray-800 rounded-xl p-5 border border-gray-700">
            <h3 class="text-yellow-400 text-sm font-medium mb-2">Pendentes</h3>
            <p class="text-xl font-bold text-white">{{ $resumo['qtd_pendentes_classificacao'] ?? 0 }}</p>
            <p class="text-xs text-gray-500">R$ {{ number_format($resumo['pendentes_classificacao'] ?? 0, 2, ',', '.') }}</p>
        </div>
    </div>

    <!-- Tabela de Movimentos Pendentes -->
    <div class="bg-gray-800 rounded-xl border border-gray-700">
        <div class="p-4 border-b border-gray-700">
            <h3 class="text-white font-semibold">Movimentos Pendentes de Classificação</h3>
        </div>
        
        @if($pendentes->count() > 0)
        <form method="post" action="{{ route('classificacao.aplicar') }}">
            @csrf
            <input type="hidden" name="mes" value="{{ $mes }}">
            <input type="hidden" name="ano" value="{{ $ano }}">
            
            <!-- Ações em Lote -->
            <div class="p-4 bg-gray-900 border-b border-gray-700 flex items-center space-x-4">
                <select name="classificacao" required class="bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Selecione a classificação...</option>
                    <option value="RECEITA_PF">Receita PF</option>
                    <option value="RECEITA_PJ">Receita PJ</option>
                    <option value="RECEITA_FINANCEIRA">Receita Financeira</option>
                </select>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-medium px-6 py-2 rounded-lg transition-colors">
                    Aplicar aos Selecionados
                </button>
                <button type="button" onclick="toggleAll()" class="bg-gray-600 hover:bg-gray-500 text-white font-medium px-4 py-2 rounded-lg transition-colors">
                    Selecionar Todos
                </button>
            </div>

            <!-- Tabela -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider w-12">
                                <input type="checkbox" id="checkAll" onclick="toggleAll()" class="rounded bg-gray-700 border-gray-600 text-blue-600 focus:ring-blue-500">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Data</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Pessoa</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Valor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Plano de Contas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach($pendentes as $m)
                        <tr class="hover:bg-gray-700/50">
                            <td class="px-4 py-3">
                                <input type="checkbox" name="ids[]" value="{{ $m->id }}" class="item-check rounded bg-gray-700 border-gray-600 text-blue-600 focus:ring-blue-500">
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-300">
                                {{ $m->data ? $m->data->format('d/m/Y') : '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-white">
                                {{ $m->pessoa ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-medium {{ $m->valor >= 0 ? 'text-green-400' : 'text-red-400' }}">
                                R$ {{ number_format($m->valor, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-400">
                                {{ $m->codigo_plano }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-400 max-w-xs truncate" title="{{ $m->plano_contas }}">
                                {{ Str::limit($m->plano_contas, 50) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
        @else
        <div class="p-8 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-gray-400 text-lg">Nenhum movimento pendente neste período.</p>
            <p class="text-gray-500 text-sm mt-2">Todos os movimentos já foram classificados ou não há dados para o período selecionado.</p>
        </div>
        @endif
    </div>
</div>

<script>
    function toggleAll() {
        const checkAll = document.getElementById('checkAll');
        const items = document.querySelectorAll('.item-check');
        const newState = !checkAll.checked;
        
        checkAll.checked = newState;
        items.forEach(item => item.checked = newState);
    }
</script>
@endsection
