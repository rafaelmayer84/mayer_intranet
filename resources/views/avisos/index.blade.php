@extends('layouts.app')

@section('title', 'Quadro de Avisos')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Quadro de Avisos</h1>
            @include('avisos.partials.back', ['fallback' => 'home'])
            <p class="text-sm text-gray-600 dark:text-slate-300">Mensagens importantes e alertas do escritório.</p>
        </div>

            <div class="flex gap-2">
                <a href="{{ route('admin.avisos.index') }}" class="btn-mayer">
                    Gerenciar
                </a>
                <a href="{{ route('admin.avisos.create') }}" class="btn-mayer">
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

    <form method="GET" action="{{ route('avisos.index') }}" class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-800">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300">Categoria</label>
                <select name="categoria_id" class="mt-1 w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900">
                    <option value="">Todas</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" @selected((string)($filtros['categoria_id'] ?? '') === (string)$cat->id)>
                            {{ $cat->nome }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300">Ordenar por</label>
                <select name="ordenar" class="mt-1 w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900">
                    <option value="prioridade" @selected(($filtros['ordenar'] ?? 'prioridade') === 'prioridade')>Prioridade</option>
                    <option value="data" @selected(($filtros['ordenar'] ?? '') === 'data')>Mais recentes</option>
                    <option value="validade" @selected(($filtros['ordenar'] ?? '') === 'validade')>Validade (mais próxima)</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 dark:text-slate-300">Buscar</label>
                <div class="mt-1 flex gap-2">
                    <input name="busca" value="{{ $filtros['busca'] ?? '' }}" class="w-full rounded-lg border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900" placeholder="Título ou descrição" />
                    <button class="btn-mayer" type="submit">Filtrar</button>
                </div>
            </div>
        </div>
    </form>

    <div class="space-y-4">
        @forelse($avisos as $aviso)
            @php
                $cor = $aviso->getPrioridadeCorHex();
                $lidos = (int) ($aviso->usuarios_lidos_count ?? 0);
                $total = max(1, (int) $totalUsuarios);
                $pct = (int) round(($lidos / $total) * 100);
            @endphp

            <div class="group rounded-xl bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:bg-slate-800">
                <div class="flex gap-4">
                    <div class="w-1.5 rounded-full" style="background: {{ $cor }}"></div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">{{ $aviso->getPrioridadeIcone() }}</span>
                                    <h2 class="text-lg font-semibold leading-tight">
                                        <a href="{{ route('avisos.show', $aviso) }}" class="hover:underline">
                                            {{ $aviso->titulo }}
                                        </a>
                                    </h2>
                                </div>

                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-slate-300">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 dark:bg-slate-900">
                                        {{ $aviso->getPrioridadeLabel() }}
                                    </span>
                                    @if($aviso->categoria)
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-white" style="background: {{ $aviso->categoria->cor_hexadecimal ?? '#3B82F6' }}">
                                            {{ $aviso->categoria->icone ? $aviso->categoria->icone . ' ' : '' }}{{ $aviso->categoria->nome }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="text-xs text-gray-500 dark:text-slate-400">
                                <div>Criado em: {{ optional($aviso->created_at)->format('d/m/Y H:i') }}</div>
                                <div>Válido até: {{ $aviso->data_fim ? $aviso->data_fim->format('d/m/Y H:i') : '—' }}</div>
                            </div>
                        </div>

                        <p class="mt-3 text-sm text-gray-700 dark:text-slate-200">
                            {{ \Illuminate\Support\Str::limit(strip_tags($aviso->descricao), 180) }}
                        </p>

                        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-xs text-gray-600 dark:text-slate-300">
                                Lido por {{ $lidos }} de {{ $totalUsuarios }} pessoas ({{ $pct }}%)
                            </div>
                            <a href="{{ route('avisos.show', $aviso) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-500">Ver detalhes</a>
                        </div>

                        <div class="mt-2 h-2 w-full rounded-full bg-gray-100 dark:bg-slate-900">
                            <div class="h-2 rounded-full" style="width: {{ $pct }}%; background: {{ $cor }}"></div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                Nenhum aviso ativo no momento.
            </div>
        @endforelse
    </div>
</div>
@endsection