<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\DataJuriService;

$dataJuri = new DataJuriService();
$token = $dataJuri->getToken();

// Buscar Movimentos - primeira página
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.datajuri.com.br/v1/entidades/Movimento?pagina=1&porPagina=100");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

echo "TOTAL DE REGISTROS NA API: " . $data['listSize'] . "\n\n";

// Filtrar janeiro 2026
$jan2026PF = [];
$jan2026PJ = [];

foreach ($data['rows'] as $mov) {
    $dataStr = $mov['data'] ?? '';
    if (strpos($dataStr, '/01/2026') !== false) {
        $plano = $mov['planoConta.nomeCompleto'] ?? '';
        $valor = strip_tags($mov['valorComSinal'] ?? '0');
        $valorLimpo = str_replace('.', '', $valor);
        $valorLimpo = str_replace(',', '.', $valorLimpo);
        $valorNum = floatval($valorLimpo);
        
        $registro = [
            'id' => $mov['id'],
            'data' => $dataStr,
            'valor' => $valor,
            'valorNum' => $valorNum,
            'pessoa' => $mov['pessoa.nome'] ?? 'N/A',
            'plano' => $plano
        ];
        
        // Apenas valores positivos (receitas)
        if ($valorNum > 0) {
            // Receitas PF
            if (strpos($plano, '3.01.01.01') !== false || strpos($plano, '3.01.01.03') !== false) {
                $jan2026PF[] = $registro;
            }
            // Receitas PJ
            elseif (strpos($plano, '3.01.01.02') !== false || strpos($plano, '3.01.01.05') !== false) {
                $jan2026PJ[] = $registro;
            }
        }
    }
}

echo "=== RECEITAS PF - JANEIRO 2026 (primeira página da API) ===\n";
echo "Quantidade: " . count($jan2026PF) . "\n\n";

$totalPF = 0;
foreach ($jan2026PF as $r) {
    echo "ID: " . $r['id'] . " | Data: " . $r['data'] . " | Valor: " . $r['valor'] . "\n";
    echo "   Pessoa: " . substr($r['pessoa'], 0, 50) . "\n";
    $totalPF += $r['valorNum'];
}

echo "\nTOTAL PF: R$ " . number_format($totalPF, 2, ',', '.') . "\n";

echo "\n=== RECEITAS PJ - JANEIRO 2026 (primeira página da API) ===\n";
echo "Quantidade: " . count($jan2026PJ) . "\n\n";

$totalPJ = 0;
foreach ($jan2026PJ as $r) {
    echo "ID: " . $r['id'] . " | Data: " . $r['data'] . " | Valor: " . $r['valor'] . "\n";
    echo "   Pessoa: " . substr($r['pessoa'], 0, 50) . "\n";
    $totalPJ += $r['valorNum'];
}

echo "\nTOTAL PJ: R$ " . number_format($totalPJ, 2, ',', '.') . "\n";
