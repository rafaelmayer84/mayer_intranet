<?php
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
?>

<div class="rounded-2xl <?php echo e($accentClass); ?> bg-gradient-to-b from-white to-gray-50 p-4 shadow-sm transition hover:shadow-md dark:from-gray-900 dark:to-gray-950">
    <div class="flex items-start justify-between">
        <div>
            <p class="text-xs text-gray-600 dark:text-gray-400"><?php echo e($title ?? ''); ?></p>
            <p id="kpi-<?php echo e($id); ?>-value" class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100"><?php echo e($value ?? ''); ?></p>
            <p class="mt-1 text-[11px] text-gray-600 dark:text-gray-400">
                Meta: <span id="kpi-<?php echo e($id); ?>-meta"><?php echo e($meta ?? ''); ?></span>
                <span class="ml-2">(<span id="kpi-<?php echo e($id); ?>-percent"><?php echo e(number_format($p, 0, ',', '.')); ?></span>%)</span>
            </p>
        </div>

        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-xl dark:bg-gray-800">
            <span aria-hidden="true"><?php echo e($icon ?? ''); ?></span>
        </div>
    </div>

    <div class="mt-3">
        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
            <div id="kpi-<?php echo e($id); ?>-progress" class="h-2 rounded-full <?php echo e($barColor); ?>" style="width: <?php echo e(min(100, $p)); ?>%"></div>
        </div>
        <div class="mt-2 text-xs">
            <span id="kpi-<?php echo e($id); ?>-trend" class="inline-flex items-center gap-1"></span>
        </div>
    </div>
</div>
<?php /**PATH /home/u492856976/domains/mayeradvogados.adv.br/public_html/Intranet/resources/views/dashboard/partials/_kpi-card.blade.php ENDPATH**/ ?>