@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-6">
        {{ $documento ? 'Editar Documento' : 'Novo Documento' }}
    </h1>

    @if($errors->any())
        <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-lg text-sm">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form action="{{ $documento ? route('admin.manuais.documentos.update', $documento) : route('admin.manuais.documentos.store') }}"
          method="POST"
          class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
        @csrf
        @if($documento) @method('PUT') @endif

        <div>
            <label for="grupo_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grupo</label>
            <select name="grupo_id" id="grupo_id" required
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Selecione...</option>
                @foreach($grupos as $g)
                    <option value="{{ $g->id }}" {{ old('grupo_id', $documento->grupo_id ?? '') == $g->id ? 'selected' : '' }}>
                        {{ $g->nome }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="titulo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Título</label>
            <input type="text" name="titulo" id="titulo"
                   value="{{ old('titulo', $documento->titulo ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   required>
        </div>

        <div>
            <label for="descricao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descrição <span class="text-gray-400">(opcional)</span></label>
            <textarea name="descricao" id="descricao" rows="2"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('descricao', $documento->descricao ?? '') }}</textarea>
        </div>

        <div>
            <label for="url_onedrive" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">URL OneDrive</label>
            <input type="url" name="url_onedrive" id="url_onedrive"
                   value="{{ old('url_onedrive', $documento->url_onedrive ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   placeholder="https://..." required>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label for="data_publicacao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data Publicação</label>
                <input type="date" name="data_publicacao" id="data_publicacao"
                       value="{{ old('data_publicacao', $documento && $documento->data_publicacao ? $documento->data_publicacao->format('Y-m-d') : '') }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="ordem" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ordem</label>
                <input type="number" name="ordem" id="ordem"
                       value="{{ old('ordem', $documento->ordem ?? 0) }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       min="0">
            </div>
            <div class="flex items-end pb-2">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="ativo" value="1"
                           {{ old('ativo', $documento->ativo ?? true) ? 'checked' : '' }}
                           class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Ativo</span>
                </label>
            </div>
        </div>

        <div class="flex items-center justify-between pt-4">
            <a href="{{ route('admin.manuais.documentos.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">&larr; Cancelar</a>
            <button type="submit"
                    class="btn-mayer">
                {{ $documento ? 'Salvar' : 'Criar' }}
            </button>
        </div>
    </form>
</div>
@endsection
