@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">
    {{-- Navegação Admin --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-4">Admin — Manuais Normativos</h1>
        <div class="flex space-x-2 border-b border-gray-200 dark:border-gray-700 pb-2">
            <a href="{{ route('admin.manuais.grupos.index') }}"
               class="px-4 py-2 text-sm font-medium rounded-t-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Grupos</a>
            <a href="{{ route('admin.manuais.documentos.index') }}"
               class="px-4 py-2 text-sm font-medium rounded-t-lg bg-brand text-white">Documentos</a>
            <a href="{{ route('admin.manuais.permissoes.index') }}"
               class="px-4 py-2 text-sm font-medium rounded-t-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Permissões</a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Filtro e Botão --}}
    <div class="flex items-center justify-between mb-4">
        <form method="GET" action="{{ route('admin.manuais.documentos.index') }}" class="flex items-center space-x-2">
            <select name="grupo_id"
                    class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                    onchange="this.form.submit()">
                <option value="">Todos os grupos</option>
                @foreach($grupos as $g)
                    <option value="{{ $g->id }}" {{ $grupoFiltro == $g->id ? 'selected' : '' }}>{{ $g->nome }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('admin.manuais.documentos.create') }}"
           class="btn-mayer">
            + Novo Documento
        </a>
    </div>

    {{-- Tabela --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Grupo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Título</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Data</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ativo</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($documentos as $doc)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $doc->grupo->nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100">
                            {{ $doc->titulo }}
                            @if($doc->descricao)
                                <p class="text-xs text-gray-400 truncate max-w-xs">{{ $doc->descricao }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-center text-gray-500 dark:text-gray-400">
                            {{ $doc->data_publicacao ? $doc->data_publicacao->format('d/m/Y') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($doc->ativo)
                                <span class="inline-flex px-2 py-0.5 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 rounded-full">Sim</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 rounded-full">Não</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <a href="{{ $doc->url_onedrive }}" target="_blank" rel="noopener"
                               class="text-sm text-green-600 dark:text-green-400 hover:underline">Link</a>
                            <a href="{{ route('admin.manuais.documentos.edit', $doc) }}"
                               class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Editar</a>
                            <form action="{{ route('admin.manuais.documentos.destroy', $doc) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Excluir documento {{ $doc->titulo }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:underline">Excluir</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-sm text-gray-400 text-center">Nenhum documento cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        <a href="{{ route('manuais-normativos.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">&larr; Voltar aos Manuais</a>
    </div>
</div>
@endsection
