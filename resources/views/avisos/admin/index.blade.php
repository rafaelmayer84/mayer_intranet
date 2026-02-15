@extends('layouts.app')

@section('title', 'Gerenciar Avisos')
            @include('avisos.partials.back', ['fallback' => 'avisos.index'])

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Gerenciar Avisos</h1>
            <p class="text-sm text-gray-600 dark:text-slate-300">CRUD e filtros de avisos.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.categorias-avisos.index') }}" class="inline-flex items-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm hover:bg-gray-50 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700">
                Categorias
            </a>
            <a href="{{ route('admin.avisos.create') }}" class="inline-flex items-center rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover-bg-brand-dark">
                Novo aviso
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-200">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200">
            {{ session('error') }}
        </div>
    @endif

    <form method="GET" class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-800">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300">Buscar</label>
                <input name="busca" value="{{ request('busca') }}" class="mt-1 w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900" placeholder="Título ou descrição" />
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300">Categoria</label>
                <select name="categoria_id" class="mt-1 w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900">
                    <option value="">Todas</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" @selected((string)request('categoria_id') === (string)$cat->id)>{{ $cat->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300">Prioridade</label>
                <select name="prioridade" class="mt-1 w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900">
                    <option value="">Todas</option>
                    @foreach(['critica'=>'Crítica','alta'=>'Alta','media'=>'Média','baixa'=>'Baixa'] as $k=>$v)
                        <option value="{{ $k }}" @selected(request('prioridade')===$k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900">
                    <option value="">Todos</option>
                    @foreach(['ativo'=>'Ativo','agendado'=>'Agendado','inativo'=>'Inativo'] as $k=>$v)
                        <option value="{{ $k }}" @selected(request('status')===$k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2 md:col-span-1">
                <button class="w-full rounded-lg btn-mayer" type="submit">Filtrar</button>
            </div>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm dark:bg-slate-800">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
            <thead class="bg-gray-50 dark:bg-slate-900">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300">Título</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300">Categoria</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300">Prioridade</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300">Validade</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-slate-300">Criado por</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-slate-300">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                @forelse($avisos as $aviso)
                    @php
                        $cor = $aviso->getPrioridadeCorHex();
                        $prio = $aviso->getPrioridadeLabel();
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-slate-900/40">
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-slate-100">
                            <a class="hover:underline" href="{{ route('avisos.show', $aviso) }}">{{ $aviso->titulo }}</a>
                            <div class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit(strip_tags($aviso->descricao), 80) }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-slate-200">{{ $aviso->categoria->nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-white" style="background: {{ $cor }}">{{ $prio }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-slate-200">{{ ucfirst($aviso->status) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-slate-200">{{ $aviso->data_fim ? $aviso->data_fim->format('d/m/Y H:i') : '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-slate-200">{{ $aviso->autor->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-sm">
                            <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.avisos.edit', $aviso) }}" class="rounded-lg btn-mayer-sm">Editar</a>
                                    <form method="POST" action="{{ route('admin.avisos.destroy', $aviso) }}" onsubmit="return confirm('Remover este aviso?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500" type="submit">Excluir</button>
                                    </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-600 dark:text-slate-300">Nenhum aviso encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $avisos->links() }}
    </div>
</div>
@endsection