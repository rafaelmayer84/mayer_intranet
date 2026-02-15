@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Cabeçalho --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Classificação de Movimentos</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Gerencie regras de classificação automática de movimentos financeiros
        </p>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
    <div class="mb-6 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4">
        <div class="flex items-center">
            <span class="text-green-600 dark:text-green-400 text-xl mr-3">✓</span>
            <p class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</p>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4">
        <div class="flex items-center">
            <span class="text-red-600 dark:text-red-400 text-xl mr-3">✕</span>
            <p class="text-sm text-red-800 dark:text-red-200">{{ session('error') }}</p>
        </div>
    </div>
    @endif

    @if(session('warning'))
    <div class="mb-6 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 p-4">
        <div class="flex items-center">
            <span class="text-yellow-600 dark:text-yellow-400 text-xl mr-3">⚠</span>
            <p class="text-sm text-yellow-800 dark:text-yellow-200">{{ session('warning') }}</p>
        </div>
    </div>
    @endif

    {{-- Ações e Filtros --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        {{-- Ações Principais --}}
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.classificacao-regras.create') }}" 
               class="btn-mayer">
                <span class="mr-2">+</span> Nova Regra
            </a>

            <form method="POST" action="{{ route('admin.classificacao-regras.importar') }}" class="inline">
                @csrf
                <button type="submit" 
                        class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition">
                    <span class="mr-2">📥</span> Importar do DataJuri
                </button>
            </form>

            <form method="POST" action="{{ route('admin.classificacao-regras.reclassificar') }}" class="inline">
                @csrf
                <button type="submit" 
                        onclick="return confirm('Isso reclassificará TODOS os movimentos pendentes. Continuar?')"
                        class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition">
                    <span class="mr-2">🔄</span> Reclassificar Tudo
                </button>
            </form>

            <a href="{{ route('admin.classificacao-regras.exportar') }}" 
               class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg transition">
                <span class="mr-2">📤</span> Exportar CSV
            </a>
        </div>

        {{-- Busca --}}
        <form method="GET" class="flex gap-2">
            <input type="text" 
                   name="busca" 
                   value="{{ request('busca') }}"
                   placeholder="Buscar código ou nome..."
                   class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-[#385776] text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
            <button type="submit" 
                    class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-medium transition">
                🔍 Buscar
            </button>
            @if(request('busca'))
            <a href="{{ route('admin.classificacao-regras.index') }}" 
               class="px-4 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-sm transition">
                Limpar
            </a>
            @endif
        </form>
    </div>

    {{-- Filtros Avançados --}}
    <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
        <form method="GET" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Classificação</label>
                <select name="classificacao" 
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                    <option value="">Todas</option>
                    @foreach($classificacoes as $key => $label)
                    <option value="{{ $key }}" {{ request('classificacao') === $key ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                <select name="ativo" 
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                    <option value="">Todos</option>
                    <option value="1" {{ request('ativo') === '1' ? 'selected' : '' }}>Ativos</option>
                    <option value="0" {{ request('ativo') === '0' ? 'selected' : '' }}>Inativos</option>
                </select>
            </div>

            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Origem</label>
                <select name="origem" 
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                    <option value="">Todas</option>
                    @foreach($origens as $key => $label)
                    <option value="{{ $key }}" {{ request('origem') === $key ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit" 
                        class="btn-mayer">
                    Filtrar
                </button>
            </div>
        </form>
    </div>

    {{-- Tabela de Regras --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Código
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Nome do Plano
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Classificação
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Tipo
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Prioridade
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Origem
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Ações
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($regras as $regra)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-mono text-gray-900 dark:text-gray-100">
                                {{ $regra->codigo_plano }}
                            </span>
                            @if($regra->isWildcard())
                            <span class="ml-2 text-xs text-purple-600 dark:text-purple-400">★ Wildcard</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm text-gray-900 dark:text-gray-100">{{ $regra->nome_plano }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-semibold rounded {{ $regra->badge_color }}">
                                {{ $regra->icon }} {{ ClassificacaoRegra::CLASSIFICACOES[$regra->classificacao] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                            {{ ClassificacaoRegra::TIPOS_MOVIMENTO[$regra->tipo_movimento] }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ $regra->prioridade }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <form method="POST" action="{{ route('admin.classificacao-regras.toggle', $regra) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="flex items-center text-sm">
                                    @if($regra->ativo)
                                    <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                    <span class="text-green-600 dark:text-green-400">Ativo</span>
                                    @else
                                    <span class="w-2 h-2 bg-gray-400 rounded-full mr-2"></span>
                                    <span class="text-gray-500 dark:text-gray-400">Inativo</span>
                                    @endif
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                            {{ ClassificacaoRegra::ORIGENS[$regra->origem] ?? $regra->origem }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.classificacao-regras.edit', $regra) }}" 
                                   class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                    Editar
                                </a>
                                <form method="POST" 
                                      action="{{ route('admin.classificacao-regras.destroy', $regra) }}" 
                                      class="inline"
                                      onsubmit="return confirm('Confirma exclusão desta regra?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                        Excluir
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <div class="text-gray-400 dark:text-gray-500">
                                <p class="text-lg mb-2">📋</p>
                                <p class="text-sm">Nenhuma regra encontrada</p>
                                <a href="{{ route('admin.classificacao-regras.create') }}" 
                                   class="mt-4 inline-block text-blue-600 hover:text-blue-700 dark:text-blue-400 text-sm">
                                    Criar primeira regra
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginação --}}
        @if($regras->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $regras->links() }}
        </div>
        @endif
    </div>

    {{-- Estatísticas --}}
    <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Total de Regras</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $regras->total() }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Regras Ativas</div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">
                {{ ClassificacaoRegra::ativas()->count() }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Importadas</div>
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">
                {{ ClassificacaoRegra::where('origem', 'datajuri')->count() }}
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Manuais</div>
            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">
                {{ ClassificacaoRegra::where('origem', 'manual')->count() }}
            </div>
        </div>
    </div>
</div>
@endsection
