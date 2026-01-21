@php
    $accentMap = [
        'green' => 'border-t-4 border-emerald-500',
        'blue' => 'border-t-4 border-blue-500',
        'orange' => 'border-t-4 border-orange-500',
        'purple' => 'border-t-4 border-purple-500',
    ];
    $accentClass = $accentMap[$accent ?? 'blue'] ?? $accentMap['blue'];

    $colorMap = [
        'green' => 'bg-emerald-500',
        'blue' => 'bg-blue-500',
        'orange' => 'bg-orange-500',
        'purple' => 'bg-purple-500',
    ];
    $barColor = $colorMap[$accent ?? 'blue'] ?? $colorMap['blue'];

    $p = (float) ($percent ?? 0);
    $p = max(0, min(999, $p));
@endphp

<div class="rounded-2xl {{ $accentClass }} bg-gradient-to-b from-white to-gray-50 p-4 shadow-sm transition hover:shadow-md dark:from-gray-900 dark:to-gray-950">
    <div class="flex items-start justify-between">
        <div>
            <p class="text-xs text-gray-600 dark:text-gray-400">{{ $title ?? '' }}</p>
            <p id="kpi-{{ $id }}-value" class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $value ?? '' }}</p>
            <p class="mt-1 text-[11px] text-gray-600 dark:text-gray-400">
                Meta: <span id="kpi-{{ $id }}-meta">{{ $meta ?? '' }}</span>
                <span class="ml-2">(<span id="kpi-{{ $id }}-percent">{{ number_format($p, 0, ',', '.') }}</span>%)</span>
            </p>
        </div>

        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-xl dark:bg-gray-800">
            <span aria-hidden="true">{{ $icon ?? '' }}</span>
        </div>
    </div>

    <div class="mt-3">
        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
            <div id="kpi-{{ $id }}-progress" class="h-2 rounded-full {{ $barColor }}" style="width: {{ min(100, $p) }}%"></div>
        </div>
        <div class="mt-2 text-xs">
            <span id="kpi-{{ $id }}-trend" class="inline-flex items-center gap-1"></span>
        </div>
    </div>
</div>
