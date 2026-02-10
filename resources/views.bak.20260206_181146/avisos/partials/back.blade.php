@php
    $fallback = $fallback ?? null;
    $label = $label ?? 'Voltar';

    $prev = url()->previous();
    $current = url()->current();

    // Evita loop (prev == current) e garante fallback
    if (!$prev || $prev === $current) {
        $prev = $fallback ? route($fallback) : route('avisos.index');
    }
@endphp

<a href="{{ $prev }}"
   class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
    <span aria-hidden="true">←</span>
    <span>{{ $label }}</span>
</a>
