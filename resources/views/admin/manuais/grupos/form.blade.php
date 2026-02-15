@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-6">
        {{ $grupo ? 'Editar Grupo' : 'Novo Grupo' }}
    </h1>

    @if($errors->any())
        <div class="mb-4 px-4 py-3 bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-300 rounded-lg text-sm">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form action="{{ $grupo ? route('admin.manuais.grupos.update', $grupo) : route('admin.manuais.grupos.store') }}"
          method="POST"
          class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 space-y-4">
        @csrf
        @if($grupo) @method('PUT') @endif

        <div>
            <label for="nome" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome</label>
            <input type="text" name="nome" id="nome"
                   value="{{ old('nome', $grupo->nome ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   placeholder="Ex: AD - Administração" required>
        </div>

        <div>
            <label for="slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Slug <span class="text-gray-400">(auto se vazio)</span></label>
            <input type="text" name="slug" id="slug"
                   value="{{ old('slug', $grupo->slug ?? '') }}"
                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   placeholder="ad-administracao">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="ordem" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ordem</label>
                <input type="number" name="ordem" id="ordem"
                       value="{{ old('ordem', $grupo->ordem ?? 0) }}"
                       class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       min="0">
            </div>
            <div class="flex items-end pb-2">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="ativo" value="1"
                           {{ old('ativo', $grupo->ativo ?? true) ? 'checked' : '' }}
                           class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Ativo</span>
                </label>
            </div>
        </div>

        <div class="flex items-center justify-between pt-4">
            <a href="{{ route('admin.manuais.grupos.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">&larr; Cancelar</a>
            <button type="submit"
                    class="btn-mayer">
                {{ $grupo ? 'Salvar' : 'Criar' }}
            </button>
        </div>
    </form>
</div>
@endsection
