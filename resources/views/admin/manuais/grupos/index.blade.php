@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">
    {{-- Navegação Admin --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-4">Admin — Manuais Normativos</h1>
        <div class="flex space-x-2 border-b border-gray-200 dark:border-gray-700 pb-2">
            <a href="{{ route('admin.manuais.grupos.index') }}"
               class="px-4 py-2 text-sm font-medium rounded-t-lg bg-brand text-white">Grupos</a>
            <a href="{{ route('admin.manuais.documentos.index') }}"
               class="px-4 py-2 text-sm font-medium rounded-t-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Documentos</a>
            <a href="{{ route('admin.manuais.permissoes.index') }}"
               class="px-4 py-2 text-sm font-medium rounded-t-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Permissões</a>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Botão criar --}}
    <div class="flex justify-end mb-4">
        <a href="{{ route('admin.manuais.grupos.create') }}"
           class="btn-mayer">
            + Novo Grupo
        </a>
    </div>

    {{-- Tabela --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ordem</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nome</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Slug</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Docs</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ativo</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($grupos as $grupo)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $grupo->ordem }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100">{{ $grupo->nome }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $grupo->slug }}</td>
                        <td class="px-4 py-3 text-sm text-center text-gray-600 dark:text-gray-300">{{ $grupo->documentos_count }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($grupo->ativo)
                                <span class="inline-flex px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 rounded-full">Sim</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 rounded-full">Não</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <a href="{{ route('admin.manuais.grupos.edit', $grupo) }}"
                               class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Editar</a>
                            <form action="{{ route('admin.manuais.grupos.destroy', $grupo) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Excluir grupo {{ $grupo->nome }}? Todos os documentos serão removidos.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:underline">Excluir</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-sm text-gray-400 text-center">Nenhum grupo cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Voltar --}}
    <div class="mt-6">
        <a href="{{ route('manuais-normativos.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">&larr; Voltar aos Manuais</a>
    </div>
</div>
@endsection
