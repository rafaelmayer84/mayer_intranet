{{-- resources/views/components/page-header.blade.php --}}
@props([
    'title' => '',
    'subtitle' => null,
    'class' => '',
])

<div class="ma-page-header {{ $class }}">
    <div>
        <h1 class="ma-page-title">{{ $title }}</h1>
        @if($subtitle)
            <p class="ma-page-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if($slot->isNotEmpty())
        <div class="flex items-center gap-2 flex-wrap">
            {{ $slot }}
        </div>
    @endif
</div>
