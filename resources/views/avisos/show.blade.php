@extends('layouts.app')

@section('title', 'Aviso')

@section('content')
@php
    $cor = $aviso->getPrioridadeCorHex();
    $lidos = (int) ($aviso->usuarios_lidos_count ?? 0);
    $total = max(1, (int) $totalUsuarios);
    $pct = (int) round(($lidos / $total) * 100);
@endphp

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('avisos.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-500">← Voltar</a>

        <div class="flex gap-2">
                <a href="{{ route('admin.avisos.edit', $aviso) }}" class="inline-flex items-center rounded-lg btn-mayer">
                    Editar
                </a>
                <form method="POST" action="{{ route('admin.avisos.destroy', $aviso) }}" onsubmit="return confirm('Remover este aviso?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-500">
                        Deletar
                    </button>
                </form>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-900/20 dark:text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-800">
        <div class="flex gap-4">
            <div class="w-1.5 rounded-full" style="background: {{ $cor }}"></div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">{{ $aviso->getPrioridadeIcone() }}</span>
                            <h1 class="text-2xl font-semibold leading-tight">{{ $aviso->titulo }}</h1>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-gray-700 dark:bg-slate-900 dark:text-slate-100">
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
                        <div>Autor: {{ $aviso->autor->name ?? '—' }}</div>
                    </div>
                </div>

                <div class="mt-4 text-xs text-gray-600 dark:text-slate-300">
                    Lido por {{ $lidos }} de {{ $totalUsuarios }} pessoas ({{ $pct }}%)
                    <div class="mt-2 h-2 w-full rounded-full bg-gray-100 dark:bg-slate-900">
                        <div class="h-2 rounded-full" style="width: {{ $pct }}%; background: {{ $cor }}"></div>
                    </div>
                </div>

                <div class="prose prose-slate mt-6 max-w-none dark:prose-invert">
@php
                        $descricao = (string) ($aviso->descricao ?? '');
                    @endphp

                    @if(trim($descricao) === '')
                        <p class="text-sm text-slate-500 dark:text-slate-300">Este aviso não possui descrição.</p>
                    @else
                        <div class="prose max-w-none dark:prose-invert">
                            {!! nl2br(e($descricao)) !!}
                        </div>
                    @endif
                </div>

                <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-xs text-gray-500 dark:text-slate-400">
                        Status: <span class="font-semibold">{{ ucfirst($aviso->status) }}</span>
                        @if($aviso->data_inicio)
                            · Início: <span class="font-semibold">{{ $aviso->data_inicio->format('d/m/Y H:i') }}</span>
                        @endif
                    </div>

                    @if(!$jaLeu)
                        <button type="button" data-marcar-lido class="inline-flex items-center justify-center rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover-bg-brand-dark">
                            Marcar como lido
                        </button>
                    @else
                        <span class="inline-flex items-center rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-200">Lido ✅</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const btn = document.querySelector('[data-marcar-lido]');
    if (!btn) return;

    btn.addEventListener('click', async () => {
        btn.disabled = true;
        try {
            const res = await fetch(@json(route('avisos.lido', $aviso)), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
            });
            if (!res.ok) throw new Error('Falha');
            window.location.reload();
        } catch (e) {
            btn.disabled = false;
            alert('Não foi possível registrar como lido.');
        }
    });
})();
</script>
@endpush

@endsection