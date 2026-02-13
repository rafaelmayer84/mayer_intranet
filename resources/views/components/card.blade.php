{{-- resources/views/components/card.blade.php --}}
@props([
    'title' => null,
    'subtitle' => null,
    'padding' => true,
    'footer' => null,
    'headerActions' => null,
    'accent' => null,
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'ma-card ' . $class]) }}
    @if($accent) style="border-top: 3px solid {{ $accent }}" @endif
>
    @if($title || $headerActions)
        <div class="ma-card-header flex items-center justify-between">
            <div>
                @if($title)
                    <h3 class="text-sm font-semibold" style="color: var(--navy)">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="text-xs mt-0.5" style="color: var(--text-muted)">{{ $subtitle }}</p>
                @endif
            </div>
            @if($headerActions)
                <div class="flex items-center gap-2">
                    {{ $headerActions }}
                </div>
            @endif
        </div>
    @endif

    <div class="{{ $padding ? 'ma-card-body' : '' }}">
        {{ $slot }}
    </div>

    @if($footer)
        <div class="ma-card-footer">
            {{ $footer }}
        </div>
    @endif
</div>
