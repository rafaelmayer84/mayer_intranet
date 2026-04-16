@extends('layouts.app')

@section('title', 'Aviso')

@section('content')
@php
    $cor = $aviso->getPrioridadeCorHex();
    $lidos = (int) ($aviso->usuarios_lidos_count ?? 0);
    $total = max(1, (int) $totalUsuarios);
    $pct = (int) round(($lidos / $total) * 100);
@endphp

<div class="ds">
<div class="ds-page w-full space-y-6">

    {{-- Top bar: back + actions --}}
    <div class="flex items-center justify-between ds-a ds-a1">
        <a href="{{ route('avisos.index') }}" class="ds-btn ds-btn--ghost ds-btn--sm">
            &larr; Voltar
        </a>

        <div class="flex gap-2">
            <a href="{{ route('admin.avisos.edit', $aviso) }}" class="ds-btn ds-btn--primary ds-btn--sm">
                Editar
            </a>
            <form method="POST" action="{{ route('admin.avisos.destroy', $aviso) }}" onsubmit="return confirm('Remover este aviso?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="ds-btn ds-btn--sm" style="background:var(--ds-danger,#dc2626);color:#fff;">
                    Deletar
                </button>
            </form>
        </div>
    </div>

    {{-- Flash success --}}
    @if(session('success'))
        <div class="ds-badge ds-badge--success px-4 py-3 text-sm w-full ds-a ds-a2" style="display:block;border-radius:.75rem;">
            {{ session('success') }}
        </div>
    @endif

    {{-- Main card --}}
    <div class="ds-card ds-card--accent ds-a ds-a3">
        <div class="ds-card-body">
            <div class="flex gap-4">
                {{-- Priority color strip --}}
                <div class="w-1.5 rounded-full shrink-0" style="background: {{ $cor }}"></div>

                <div class="min-w-0 flex-1 space-y-5">
                    {{-- Header row --}}
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-2xl">{{ $aviso->getPrioridadeIcone() }}</span>
                                <h1 class="text-2xl font-semibold leading-tight">{{ $aviso->titulo }}</h1>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
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

                        <div class="text-xs text-gray-500 dark:text-slate-400 shrink-0">
                            <div>Criado em: {{ optional($aviso->created_at)->format('d/m/Y H:i') }}</div>
                            <div>V&aacute;lido at&eacute;: {{ $aviso->data_fim ? $aviso->data_fim->format('d/m/Y H:i') : '—' }}</div>
                            <div>Autor: {{ $aviso->autor->name ?? '—' }}</div>
                        </div>
                    </div>

                    {{-- Read progress --}}
                    <div class="text-xs text-gray-600 dark:text-slate-300">
                        Lido por {{ $lidos }} de {{ $totalUsuarios }} pessoas ({{ $pct }}%)
                        <div class="ds-progress mt-2">
                            <div class="ds-progress-fill--primary" style="width: {{ $pct }}%; background: {{ $cor }};"></div>
                        </div>
                    </div>

                    {{-- Description --}}
                    <div class="prose prose-slate max-w-none dark:prose-invert">
                        @php
                            $descricao = (string) ($aviso->descricao ?? '');
                        @endphp

                        @if(trim($descricao) === '')
                            <p class="text-sm text-slate-500 dark:text-slate-300">Este aviso n&atilde;o possui descri&ccedil;&atilde;o.</p>
                        @else
                            <div class="prose max-w-none dark:prose-invert">
                                {!! nl2br(e($descricao)) !!}
                            </div>
                        @endif
                    </div>

                    {{-- Footer: status + mark as read --}}
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-xs text-gray-500 dark:text-slate-400">
                            Status: <span class="font-semibold">{{ ucfirst($aviso->status) }}</span>
                            @if($aviso->data_inicio)
                                &middot; In&iacute;cio: <span class="font-semibold">{{ $aviso->data_inicio->format('d/m/Y H:i') }}</span>
                            @endif
                        </div>

                        @if(!$jaLeu)
                            <button type="button" data-marcar-lido class="ds-btn ds-btn--primary ds-btn--sm">
                                Marcar como lido
                            </button>
                        @else
                            <span class="ds-badge ds-badge--success px-3 py-2 text-sm">Lido &#10004;</span>
                        @endif
                    </div>
                </div>
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
