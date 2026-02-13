{{-- resources/views/components/kpi-card.blade.php --}}
@props([
    'label' => '',
    'value' => '—',
    'meta' => null,
    'delta' => null,
    'deltaType' => null,
    'accent' => 'primary',
    'icon' => null,
    'format' => null,
    'tooltip' => null,
    'class' => '',
])

@php
    $accentMap = [
        'primary' => '',
        'success' => 'ma-kpi--success',
        'warning' => 'ma-kpi--warning',
        'danger'  => 'ma-kpi--danger',
        'info'    => 'ma-kpi--info',
    ];
    $accentClass = $accentMap[$accent] ?? '';

    // Auto-detect delta type if not provided
    if ($delta !== null && $deltaType === null) {
        if (is_numeric(str_replace(['%', '+', '-', ',', '.'], '', $delta))) {
            $numDelta = floatval(str_replace(['+', '%', ','], ['', '', '.'], $delta));
            $deltaType = $numDelta >= 0 ? 'positive' : 'negative';
        }
    }
@endphp

<div class="ma-kpi {{ $accentClass }} {{ $class }}" @if($tooltip) title="{{ $tooltip }}" @endif>
    <div class="flex items-start justify-between">
        <div class="flex-1 min-w-0">
            <p class="ma-kpi-label">{{ $label }}</p>
            <p class="ma-kpi-value">{{ $value }}</p>

            @if($delta !== null)
                <div class="flex items-center gap-2 mt-1">
                    @if($deltaType === 'positive')
                        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full"
                              style="background: var(--success-bg); color: var(--success); border: 1px solid var(--success-border)">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
                            {{ $delta }}
                        </span>
                    @elseif($deltaType === 'negative')
                        <span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full"
                              style="background: var(--danger-bg); color: var(--danger); border: 1px solid var(--danger-border)">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            {{ $delta }}
                        </span>
                    @else
                        <span class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded-full"
                              style="background: var(--surface-3); color: var(--text-muted); border: 1px solid var(--border)">
                            {{ $delta }}
                        </span>
                    @endif
                </div>
            @endif

            @if($meta)
                <p class="ma-kpi-meta mt-1">{{ $meta }}</p>
            @endif
        </div>

        @if($icon)
            <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center ml-3"
                 style="background: var(--surface-3)">
                {!! $icon !!}
            </div>
        @endif
    </div>

    {{-- Slot for sparkline or extra content --}}
    @if($slot->isNotEmpty())
        <div class="mt-3 pt-3" style="border-top: 1px solid var(--border-light)">
            {{ $slot }}
        </div>
    @endif
</div>
