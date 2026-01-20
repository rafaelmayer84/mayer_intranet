@extends('layouts.app')

@section('title', $categoria->exists ? 'Editar Categoria' : 'Nova Categoria')
            @include('avisos.partials.back', ['fallback' => 'admin.categorias-avisos.index'])

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">{{ $categoria->exists ? 'Editar Categoria' : 'Nova Categoria' }}</h1>
            <p class="text-sm text-gray-600 dark:text-slate-300">Categorias organizam os avisos e definem cor/ícone.</p>
        </div>
        <a href="{{ route('admin.categorias-avisos.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-500">← Voltar</a>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">Verifique os campos destacados.</div>
    @endif

    <form method="POST" action="{{ $categoria->exists ? route('admin.categorias-avisos.update', $categoria) : route('admin.categorias-avisos.store') }}" class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-800">
        @csrf
        @if($categoria->exists)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300">Nome</label>
                <input name="nome" value="{{ old('nome', $categoria->nome) }}" class="mt-1 w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900" maxlength="100" required />
                @error('nome')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300">Descrição</label>
                <textarea name="descricao" rows="3" class="mt-1 w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900" maxlength="2000">{{ old('descricao', $categoria->descricao) }}</textarea>
                @error('descricao')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300">Cor (hex)</label>
                <input name="cor_hexadecimal" value="{{ old('cor_hexadecimal', $categoria->cor_hexadecimal ?? '#3B82F6') }}" class="mt-1 w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900" placeholder="#3B82F6" />
                @error('cor_hexadecimal')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                <div class="mt-2 inline-flex items-center gap-2 text-xs text-gray-600 dark:text-slate-300">
                    <span class="inline-block h-4 w-4 rounded" style="background: {{ old('cor_hexadecimal', $categoria->cor_hexadecimal ?? '#3B82F6') }}"></span>
                    Pré-visualização
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300">Ícone (texto/emoji)</label>
                <input name="icone" value="{{ old('icone', $categoria->icone) }}" class="mt-1 w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900" maxlength="50" placeholder="📌" />
                @error('icone')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300">Ordem</label>
                <input type="number" name="ordem" value="{{ old('ordem', $categoria->ordem ?? 0) }}" class="mt-1 w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900" min="0" max="9999" />
                @error('ordem')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="ativo" value="1" @checked(old('ativo', $categoria->ativo ?? true)) />
                    Ativo
                </label>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
            <a href="{{ route('admin.categorias-avisos.index') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm hover:bg-gray-50 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">Cancelar</a>
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Salvar</button>
        </div>
    </form>
</div>
@endsection