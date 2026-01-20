<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== STATUS DE PROCESSOS ===\n";
$statuses = DB::table('processos')
    ->select('status', DB::raw('count(*) as total'))
    ->groupBy('status')
    ->orderByDesc('total')
    ->get();

foreach ($statuses as $s) {
    echo "{$s->status}: {$s->total}\n";
}

echo "\n=== TOTAL GERAL ===\n";
$total = DB::table('processos')->count();
echo "Total: {$total}\n";
