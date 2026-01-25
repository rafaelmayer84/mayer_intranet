<?php

// Script para popular lançamentos de teste
require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Cliente;
use App\Models\Lancamento;

echo "Iniciando população de lançamentos de teste...\n";

$clientes = Cliente::limit(20)->get();
echo "Encontrados " . $clientes->count() . " clientes\n";

$total = 0;
foreach ($clientes as $cliente) {
    for ($i = 0; $i < 10; $i++) {
        Lancamento::create([
            'cliente_id' => $cliente->id,
            'tipo' => rand(0, 1) ? 'receita' : 'despesa',
            'valor' => rand(5000, 100000) / 100,
            'descricao' => 'Lançamento de teste #' . ($i + 1),
            'data' => now()->subDays(rand(0, 365)),
            'referencia' => 'REF-' . uniqid(),
            'status' => 'pago',
        ]);
        $total++;
    }
}

echo "✅ {$total} lançamentos criados com sucesso!\n";
