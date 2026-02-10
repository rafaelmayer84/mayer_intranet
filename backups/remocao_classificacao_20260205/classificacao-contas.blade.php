@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 dark:bg-gray-900 p-6">
    <div class="max-w-5xl mx-auto">

        <!-- Cabeçalho -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Classificação de Contas</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Defina como cada conta do plano contábil é classificada no dashboard financeiro.</p>
        </div>

        <!-- Alertas de erro de validação -->
        @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 border border-red-200 p-4">
            <p class="text-sm font-semibold text-red-700 mb-1">Erros de validação</p>
            <ul class="list-disc list-inside text-sm text-red-600">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- Flash de sucesso -->
        @if (session('success'))
        <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-200 p-4">
            <p class="text-sm text-emerald-700">{{ session('success') }}</p>
        </div>
        @endif

        <!-- Flash de erro -->
        @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 border border-red-200 p-4">
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
        @endif

        <!-- Card principal -->
        <div class="rounded-2xl bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">

            <!-- Header do card -->
            <div class="border-t-4 border-blue-500 px-6 py-4 bg-gradient-to-b from-white to-gray-50 dark:from-gray-800 dark:to-gray-750">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Plano de Contas</p>
                        <p class="text-lg font-semibold text-gray-800 dark:text-white mt-0.5">
                            {{ count($contas) }} conta{{ count($contas) !== 1 ? 's' : '' }} configurável{{ count($contas) !== 1 ? 'eis' : '' }}
                        </p>
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-100 to-blue-200 dark:from-blue-900 dark:to-blue-800">
                        <span aria-hidden="true">📋</span>
                    </div>
                </div>
            </div>

            <!-- Tabela -->
            <div class="p-6">
                <form method="POST" action="{{ route('configuracoes.salvar-classificacao') }}">
                    @csrf

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Código</th>
                                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Tipo</th>
                                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Subtipo</th>
                                    <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Classificação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($contas as $codigo => $conta)
                                @php
                                    $tipoLower = strtolower($conta['tipo']);
                                    $badgeClass = match(true) {
                                        $tipoLower === 'receita' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300',
                                        $tipoLower === 'manual' => 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
                                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                    };
                                @endphp
                                <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                                    <td class="py-3 px-4">
                                        <input type="hidden" name="classificacoes[{{ $loop->index }}][codigo]" value="{{ $conta['codigo'] }}">
                                        <code class="inline-block bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-mono px-2 py-0.5 rounded">{{ $conta['codigo'] }}</code>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-block text-xs font-medium px-2 py-0.5 rounded-full {{ $badgeClass }}">{{ $conta['tipo'] }}</span>
                                    </td>
                                    <td class="py-3 px-4 text-gray-600 dark:text-gray-400">{{ $conta['subtipo'] }}</td>
                                    <td class="py-3 px-4">
                                        <select name="classificacoes[{{ $loop->index }}][classificacao]"
                                                class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                            @foreach ($opcoes as $valor => $label)
                                                <option value="{{ $valor }}" {{ $conta['classificacao'] === $valor ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-gray-400 dark:text-gray-500 text-sm">
                                        Nenhuma conta configurada no plano de contas.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Botões -->
                    <div class="flex items-center gap-3 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="submit"
                                class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                            💾 Salvar Classificações
                        </button>
                        <a href="{{ route('configuracoes.resetar-classificacoes') }}"
                           onclick="return confirm('Resetar todas as classificações para o padrão original?')"
                           class="inline-flex items-center gap-2 bg-orange-100 hover:bg-orange-200 text-orange-700 dark:bg-orange-900 dark:hover:bg-orange-800 dark:text-orange-300 text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                            🔄 Resetar Padrão
                        </a>
                        <a href="{{ route('configuracoes.index') }}"
                           class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-300 text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                            ← Voltar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Info footer -->
            <div class="mx-6 mb-6 rounded-xl bg-blue-50 dark:bg-blue-900 border border-blue-100 dark:border-blue-800 p-4">
                <p class="text-xs font-semibold text-blue-700 dark:text-blue-300 mb-2">ℹ️ Como funciona</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1">
                    <p class="text-xs text-blue-600 dark:text-blue-400"><strong>RECEITA_PF</strong> — Receita de Pessoa Física</p>
                    <p class="text-xs text-blue-600 dark:text-blue-400"><strong>RECEITA_PJ</strong> — Receita de Pessoa Jurídica</p>
                    <p class="text-xs text-blue-600 dark:text-blue-400"><strong>RECEITA_FINANCEIRA</strong> — Juros, rendimentos, etc.</p>
                    <p class="text-xs text-blue-600 dark:text-blue-400"><strong>DESPESA</strong> — Despesa operacional</p>
                    <p class="text-xs text-blue-600 dark:text-blue-400 sm:col-span-2"><strong>PENDENTE</strong> — Aguarda classificação manual</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
