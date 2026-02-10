@php
    $display = $value ?? '';
    $metric = (float) ($statusValue ?? 0);
    $rule = (string) ($statusRule ?? '');

    $label = 'OK';
    $dot = '🟢';
    $pillClass = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200';

    if ($rule === 'atrasoPercent') {
        if ($metric > 15) { $label = 'CRÍTICO'; $dot = '🔴'; $pillClass = 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'; }
        elseif ($metric >= 10) { $label = 'ATENÇÃO'; $dot = '🟠'; $pillClass = 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200'; }
    }

    if ($rule === 'diasAtraso') {
        if ($metric > 45) { $label = 'CRÍTICO'; $dot = '🔴'; $pillClass = 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'; }
        elseif ($metric >= 30) { $label = 'AVISO'; $dot = '🟡'; $pillClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200'; }
    }

    if ($rule === 'taxaCobranca') {
        if ($metric < 80) { $label = 'CRÍTICO'; $dot = '🔴'; $pillClass = 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200'; }
        elseif ($metric < 90) { $label = 'AVISO'; $dot = '🟡'; $pillClass = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200'; }
    }
@endphp

<div class="rounded-2xl border border-gray-200 bg-gradient-to-b from-white to-gray-50 p-4 shadow-sm transition hover:shadow-md dark:border-gray-700 dark:from-gray-900 dark:to-gray-950">
    <div class="flex items-start justify-between">
        <div>
            <p class="text-xs text-gray-600 dark:text-gray-400">{{ $title ?? '' }}</p>
            <p id="health-{{ $id }}-value" class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $display }}</p>
            <p id="health-{{ $id }}-sub" class="mt-1 text-[11px] text-gray-600 dark:text-gray-400">{{ $sub ?? '' }}</p>
        </div>

        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-xl dark:bg-gray-800">
            <span aria-hidden="true">{{ $icon ?? '' }}</span>
        </div>
    </div>

    <div class="mt-3 flex items-center justify-between">
        <span id="health-{{ $id }}-status" class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium {{ $pillClass }}">
            <span aria-hidden="true">{{ $dot }}</span>
            <span>{{ $label }}</span>
        </span>
        <span id="health-{{ $id }}-trend" class="text-xs"></span>
    </div>
</div>
