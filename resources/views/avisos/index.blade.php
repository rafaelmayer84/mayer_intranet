@extends('layouts.app')

@section('title', 'Quadro de Avisos')

@section('content')
<div class="ds">
<div class="ds-page space-y-6 w-full">

    {{-- Header --}}
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between ds-a ds-a1">
        <div>
            <h1 class="text-2xl font-semibold">Quadro de Avisos</h1>
            @include('avisos.partials.back', ['fallback' => 'home'])
            <p class="text-sm text-gray-600 dark:text-slate-300 mt-1">Mensagens importantes e alertas do escritório.</p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('admin.avisos.index') }}" class="ds-btn ds-btn--secondary">
                Gerenciar
            </a>
            <a href="{{ route('admin.avisos.create') }}" class="ds-btn ds-btn--primary">
                Novo aviso
            </a>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="ds-card ds-card--success ds-a ds-a2">
            <div class="ds-card-body">
                <span class="ds-badge ds-badge--success">{{ session('success') }}</span>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="ds-card ds-a ds-a2">
            <div class="ds-card-body">
                <span class="ds-badge ds-badge--danger">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('avisos.index') }}" class="ds-card ds-a ds-a2">
        <div class="ds-card-body">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <div>
                    <label class="ds-label">Categoria</label>
                    <select name="categoria_id" class="ds-input mt-1 w-full">
                        <option value="">Todas</option>
                        @foreach($categorias as $cat)
                            <option value="{{ $cat->id }}" @selected((string)($filtros['categoria_id'] ?? '') === (string)$cat->id)>
                                {{ $cat->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="ds-label">Ordenar por</label>
                    <select name="ordenar" class="ds-input mt-1 w-full">
                        <option value="prioridade" @selected(($filtros['ordenar'] ?? 'prioridade') === 'prioridade')>Prioridade</option>
                        <option value="data" @selected(($filtros['ordenar'] ?? '') === 'data')>Mais recentes</option>
                        <option value="validade" @selected(($filtros['ordenar'] ?? '') === 'validade')>Validade (mais próxima)</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="ds-label">Buscar</label>
                    <div class="mt-1 flex gap-2">
                        <input name="busca" value="{{ $filtros['busca'] ?? '' }}" class="ds-input w-full" placeholder="Título ou descrição" />
                        <button class="ds-btn ds-btn--primary" type="submit">Filtrar</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Avisos list --}}
    <div class="space-y-4">
        @forelse($avisos as $aviso)
            @php
                $cor = $aviso->getPrioridadeCorHex();
                $lidos = (int) ($aviso->usuarios_lidos_count ?? 0);
                $total = max(1, (int) $totalUsuarios);
                $pct = (int) round(($lidos / $total) * 100);
            @endphp

            <div class="ds-card ds-a ds-a{{ min($loop->iteration + 2, 7) }}">
                <div class="ds-card-body">
                    <div class="flex gap-4">
                        {{-- Priority color bar --}}
                        <div class="w-1.5 rounded-full flex-shrink-0" style="background: {{ $cor }}"></div>

                        <div class="min-w-0 flex-1">
                            {{-- Title row --}}
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

                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                        <span class="ds-badge ds-badge--neutral">
                                            {{ $aviso->getPrioridadeLabel() }}
                                        </span>
                                        @if($aviso->categoria)
                                            <span class="ds-badge" style="background: {{ $aviso->categoria->cor_hexadecimal ?? '#3B82F6' }}; color: #fff;">
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

                            {{-- Description --}}
                            <p class="mt-3 text-sm text-gray-700 dark:text-slate-200">
                                {{ \Illuminate\Support\Str::limit(strip_tags($aviso->descricao), 180) }}
                            </p>

                            {{-- Read progress --}}
                            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="text-xs text-gray-600 dark:text-slate-300">
                                    Lido por {{ $lidos }} de {{ $totalUsuarios }} pessoas ({{ $pct }}%)
                                </div>
                                <a href="{{ route('avisos.show', $aviso) }}" class="ds-btn ds-btn--ghost ds-btn--sm">Ver detalhes</a>
                            </div>

                            <div class="ds-progress mt-2">
                                <div class="ds-progress-fill--primary" style="width: {{ $pct }}%; background: {{ $cor }}"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="ds-card ds-a ds-a3">
                <div class="ds-card-body text-sm text-gray-600 dark:text-slate-200">
                    Nenhum aviso ativo no momento.
                </div>
            </div>
        @endforelse
    </div>

</div>
</div>
@endsection
