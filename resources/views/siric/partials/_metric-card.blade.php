{{-- Metric Card for SIRIC --}}
{{-- Usage: @include('siric.partials._metric-card', ['label' => '...', 'valor' => '...', 'icon' => '...', 'cor' => 'blue']) --}}

@php
    $borderColors = [
        'blue'   => 'border-blue-500',
        'green'  => 'border-green-500',
        'orange' => 'border-orange-500',
        'red'    => 'border-red-500',
        'purple' => 'border-purple-500',
        'yellow' => 'border-yellow-500',
        'gray'   => 'border-gray-400',
    ];
    $border = $borderColors[$cor ?? 'gray'] ?? 'border-gray-400';
@endphp

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 border-t-4 {{ $border }} p-4">
    <div class="flex items-center gap-2 mb-1">
        <span class="text-lg">{{ $icon ?? '📊' }}</span>
        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ $label }}</span>
    </div>
    <div class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ $valor }}</div>
</div>
