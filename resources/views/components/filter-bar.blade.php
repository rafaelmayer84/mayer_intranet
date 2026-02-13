{{-- resources/views/components/filter-bar.blade.php --}}
@props([
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'ma-filter-bar ' . $class]) }}>
    {{ $slot }}
</div>
