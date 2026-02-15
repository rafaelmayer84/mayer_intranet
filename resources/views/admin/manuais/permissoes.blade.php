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
               class="px-4 py-2 text-sm font-medium rounded-t-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Documentos</a>
            <a href="{{ route('admin.manuais.permissoes.index') }}"
               class="px-4 py-2 text-sm font-medium rounded-t-lg bg-brand text-white">Permissões</a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif

    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
        Admins sempre têm acesso a todos os grupos. Abaixo, configure o acesso dos demais usuários.
    </p>

    @if($grupos->isEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center text-sm text-gray-400">
            Nenhum grupo cadastrado. <a href="{{ route('admin.manuais.grupos.create') }}" class="text-blue-600 hover:underline">Crie um grupo primeiro.</a>
        </div>
    @elseif($users->isEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 text-center text-sm text-gray-400">
            Nenhum usuário não-admin encontrado.
        </div>
    @else
        <form action="{{ route('admin.manuais.permissoes.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase sticky left-0 bg-gray-50 dark:bg-gray-900">Usuário</th>
                            @foreach($grupos as $grupo)
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase whitespace-nowrap">{{ $grupo->nome }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($users as $user)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100 sticky left-0 bg-white dark:bg-gray-800">
                                    {{ $user->name }}
                                    <span class="text-xs text-gray-400 block">{{ $user->email }}</span>
                                </td>
                                @foreach($grupos as $grupo)
                                    <td class="px-3 py-3 text-center">
                                        <input type="checkbox"
                                               name="permissoes[{{ $user->id }}][]"
                                               value="{{ $grupo->id }}"
                                               {{ in_array($grupo->id, $userGrupos[$user->id] ?? []) ? 'checked' : '' }}
                                               class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between mt-6">
                <a href="{{ route('manuais-normativos.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">&larr; Voltar aos Manuais</a>
                <button type="submit"
                        class="btn-mayer">
                    Salvar Permissões
                </button>
            </div>
        </form>
    @endif
</div>
@endsection
