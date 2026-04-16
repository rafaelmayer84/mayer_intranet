@extends('layouts.app')

@section('content')
<div class="w-full px-4 py-8 max-w-3xl">
    {{-- Cabeçalho --}}
    <div class="mb-8">
        <a href="{{ route('admin.classificacao-regras.index') }}" 
           class="inline-flex items-center text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 mb-4">
            ← Voltar para lista
        </a>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
            {{ isset($regra) ? 'Editar Regra' : 'Nova Regra de Classificação' }}
        </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            {{ isset($regra) ? 'Atualize as informações da regra de classificação' : 'Crie uma nova regra de classificação automática' }}
        </p>
    </div>

    {{-- Erros de Validação --}}
    @if($errors->any())
    <div class="mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4">
        <div class="flex items-start">
            <span class="text-red-600 dark:text-red-400 text-xl mr-3">✕</span>
            <div class="flex-1">
                <p class="text-sm font-semibold text-red-800 dark:text-red-200 mb-2">Erros de validação:</p>
                <ul class="list-disc list-inside text-sm text-red-700 dark:text-red-300">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif

    {{-- Formulário --}}
    <form method="POST" 
          action="{{ isset($regra) ? route('admin.classificacao-regras.update', $regra) : route('admin.classificacao-regras.store') }}"
          class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
        
        @csrf
        @if(isset($regra))
            @method('PUT')
        @endif

        {{-- Código do Plano --}}
        <div class="mb-6">
            <label for="codigo_plano" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Código do Plano de Contas <span class="text-red-500">*</span>
            </label>
            <input type="text" 
                   id="codigo_plano" 
                   name="codigo_plano" 
                   value="{{ old('codigo_plano', $regra->codigo_plano ?? '') }}"
                   required
                   placeholder="Ex: 3.01.01.01 ou 3.01.02.% (wildcard)"
                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Use % como wildcard. Ex: 3.01.02.% classifica TODOS os códigos que começam com 3.01.02
            </p>
        </div>

        {{-- Nome do Plano --}}
        <div class="mb-6">
            <label for="nome_plano" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Nome do Plano <span class="text-red-500">*</span>
            </label>
            <input type="text" 
                   id="nome_plano" 
                   name="nome_plano" 
                   value="{{ old('nome_plano', $regra->nome_plano ?? '') }}"
                   required
                   placeholder="Ex: Receita de Honorários - Pessoa Física"
                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
        </div>

        {{-- Classificação --}}
        <div class="mb-6">
            <label for="classificacao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Classificação <span class="text-red-500">*</span>
            </label>
            <select id="classificacao" 
                    name="classificacao" 
                    required
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                <option value="">Selecione uma classificação</option>
                @foreach($classificacoes as $key => $label)
                <option value="{{ $key }}" 
                        {{ old('classificacao', $regra->classificacao ?? '') === $key ? 'selected' : '' }}>
                    {{ $label }}
                </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Define como o movimento será classificado no dashboard
            </p>
        </div>

        {{-- Tipo de Movimento --}}
        <div class="mb-6">
            <label for="tipo_movimento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Tipo de Movimento <span class="text-red-500">*</span>
            </label>
            <select id="tipo_movimento" 
                    name="tipo_movimento" 
                    required
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                <option value="">Selecione um tipo</option>
                @foreach($tiposMovimento as $key => $label)
                <option value="{{ $key }}" 
                        {{ old('tipo_movimento', $regra->tipo_movimento ?? '') === $key ? 'selected' : '' }}>
                    {{ $label }}
                </option>
                @endforeach
            </select>
        </div>

        {{-- Prioridade --}}
        <div class="mb-6">
            <label for="prioridade" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Prioridade (0-100)
            </label>
            <input type="number" 
                   id="prioridade" 
                   name="prioridade" 
                   value="{{ old('prioridade', $regra->prioridade ?? 0) }}"
                   min="0"
                   max="100"
                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Quanto maior, mais prioridade. Regras específicas devem ter prioridade maior que wildcards
            </p>
        </div>

        {{-- Ativo --}}
        <div class="mb-6">
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" 
                       id="ativo" 
                       name="ativo" 
                       value="1"
                       {{ old('ativo', $regra->ativo ?? true) ? 'checked' : '' }}
                       class="w-5 h-5 text-blue-600 border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500">
                <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                    Regra ativa
                </span>
            </label>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 ml-8">
                Apenas regras ativas são aplicadas na classificação automática
            </p>
        </div>

        {{-- Observações --}}
        <div class="mb-6">
            <label for="observacoes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Observações
            </label>
            <textarea id="observacoes" 
                      name="observacoes" 
                      rows="3"
                      placeholder="Notas ou comentários sobre esta regra..."
                      class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">{{ old('observacoes', $regra->observacoes ?? '') }}</textarea>
        </div>

        {{-- Informações de Auditoria (somente edição) --}}
        @if(isset($regra))
        <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Informações de Auditoria</h3>
            <div class="grid grid-cols-2 gap-4 text-xs text-gray-600 dark:text-gray-400">
                <div>
                    <span class="font-medium">Origem:</span> 
                    {{ ClassificacaoRegra::ORIGENS[$regra->origem] ?? $regra->origem }}
                </div>
                <div>
                    <span class="font-medium">Criado em:</span> 
                    {{ $regra->created_at->format('d/m/Y H:i') }}
                </div>
                @if($regra->criador)
                <div>
                    <span class="font-medium">Criado por:</span> 
                    {{ $regra->criador->name }}
                </div>
                @endif
                @if($regra->updated_at && $regra->updated_at != $regra->created_at)
                <div>
                    <span class="font-medium">Modificado em:</span> 
                    {{ $regra->updated_at->format('d/m/Y H:i') }}
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Botões --}}
        <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('admin.classificacao-regras.index') }}" 
               class="px-6 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                Cancelar
            </a>
            <button type="submit" 
                    class="px-6 py-2 bg-brand hover-bg-brand-dark text-white rounded-lg font-medium transition">
                {{ isset($regra) ? 'Atualizar Regra' : 'Criar Regra' }}
            </button>
        </div>
    </form>

    {{-- Dicas --}}
    <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
        <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-200 mb-2">💡 Dicas</h3>
        <ul class="text-xs text-blue-700 dark:text-blue-300 space-y-1">
            <li>• Use wildcards (%) para criar regras que cubram múltiplos códigos: <code class="bg-blue-100 dark:bg-blue-900 px-1 rounded">3.01.02.%</code></li>
            <li>• Regras específicas devem ter prioridade maior que wildcards para evitar conflitos</li>
            <li>• Regras inativas não são aplicadas, mas permanecem no sistema para histórico</li>
            <li>• Após criar regras, use "Reclassificar Tudo" para aplicar aos movimentos existentes</li>
        </ul>
    </div>
</div>
@endsection
