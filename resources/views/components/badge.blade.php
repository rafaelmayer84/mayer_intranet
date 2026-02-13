{{-- resources/views/components/badge.blade.php --}}
@props([
    'type' => 'neutral',
    'size' => 'md',
    'dot' => false,
    'class' => '',
])

@php
    $typeClass = match($type) {
        'success', 'ok', 'positive' => 'ma-badge--success',
        'warning', 'attention'      => 'ma-badge--warning',
        'danger', 'critical', 'error' => 'ma-badge--danger',
        'info', 'primary'           => 'ma-badge--info',
        default                      => 'ma-badge--neutral',
    };
    $sizeClass = $size === 'sm' ? 'text-[10px] px-1.5 py-0.5' : '';
@endphp

<span {{ $attributes->merge(['class' => "ma-badge {$typeClass} {$sizeClass} {$class}"]) }}>
    @if($dot)
        <span class="w-1.5 h-1.5 rounded-full" style="background: currentColor"></span>
    @endif
    {{ $slot }}
</span>
