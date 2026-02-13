{{-- resources/views/components/insights.blade.php --}}
@props([
    'items' => [],
    'class' => '',
])

<div class="space-y-2 {{ $class }}">
    @forelse($items as $item)
        @php
            $level = $item['level'] ?? 'positive';
            $levelClass = match($level) {
                'positive', 'success' => 'ma-insight--positive',
                'attention', 'warning' => 'ma-insight--attention',
                'critical', 'danger'  => 'ma-insight--critical',
                default => 'ma-insight--positive',
            };
            $iconPath = match($level) {
                'positive', 'success' => 'M5 13l4 4L19 7',
                'attention', 'warning' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z',
                'critical', 'danger'  => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                default => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            };
        @endphp
        <div class="ma-insight {{ $levelClass }}">
            <svg class="ma-insight-icon flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPath }}"/>
            </svg>
            <span>{{ $item['text'] ?? $item }}</span>
        </div>
    @empty
        {{ $slot }}
    @endforelse
</div>
