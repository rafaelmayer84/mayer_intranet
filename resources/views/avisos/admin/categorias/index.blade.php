@extends('layouts.app')

@section('title', 'Categorias de Avisos')
            @include('avisos.partials.back', ['fallback' => 'admin.avisos.index'])

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Categorias de Avisos</h1>
            <p class="text-sm text-gray-600 dark:text-slate-300">Gerencie categorias, cores e ícones.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.avisos.index') }}" class="inline-flex items-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm hover:bg-gray-50 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700">Avisos</a>
            <a href="{{ route('admin.categorias-avisos.create') }}" class="inline-flex items-center rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover-bg-brand-dark">Nova categoria</a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-200">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">{{ session('error') }}</div>
    @endif

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm dark:bg-slate-800">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
            <thead class="bg-gray-50 dark:bg-slate-900">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300">Nome</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300">Descrição</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300">Cor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300">Ícone</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300">Ordem</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300">Ativo</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-slate-300">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                @forelse($categorias as $c)
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-900/40">
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-slate-100">{{ $c->nome }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-slate-200">{{ \Illuminate\Support\Str::limit($c->descricao ?? '', 80) }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex items-center gap-2">
                                <span class="inline-block h-4 w-4 rounded" style="background: {{ $c->cor_hexadecimal }}"></span>
                                <span class="text-xs text-gray-600 dark:text-slate-300">{{ $c->cor_hexadecimal }}</span>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-slate-200">{{ $c->icone ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-slate-200">{{ $c->ordem }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-slate-200">{{ $c->ativo ? 'Sim' : 'Não' }}</td>
                        <td class="px-4 py-3 text-right text-sm">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.categorias-avisos.edit', $c) }}" class="rounded-lg btn-mayer-sm">Editar</a>
                                <form method="POST" action="{{ route('admin.categorias-avisos.destroy', $c) }}" onsubmit="return confirm('Remover esta categoria?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500" type="submit">Excluir</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-600 dark:text-slate-300">Nenhuma categoria.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $categorias->links() }}
    </div>
</div>
@endsection