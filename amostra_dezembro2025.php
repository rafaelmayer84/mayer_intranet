<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\DataJuriService;

$dataJuri = new DataJuriService();
$token = $dataJuri->getToken();

if (!$token) {
    echo "Erro: Não foi possível obter token\n";
    exit(1);
}

echo "Token obtido com sucesso!\n\n";

// Buscar TODOS os Movimentos
$allMovimentos = [];
$pagina = 1;
$porPagina = 100;

do {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.datajuri.com.br/v1/entidades/Movimento?pagina={$pagina}&porPagina={$porPagina}");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token}",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        echo "Erro HTTP: {$httpCode} na página {$pagina}\n";
        break;
    }

    $data = json_decode($response, true);
    $rows = $data['rows'] ?? [];
    
    // Filtrar apenas dezembro 2025
    foreach ($rows as $mov) {
        $dataStr = $mov['data'] ?? '';
        if (strpos($dataStr, '/12/2025') !== false) {
            $allMovimentos[] = $mov;
        }
    }
    
    $listSize = $data['listSize'] ?? 0;
    $totalPaginas = ceil($listSize / $porPagina);
    
    echo "Página {$pagina}/{$totalPaginas} - Encontrados " . count($allMovimentos) . " registros de DEZ/2025\n";
    
    $pagina++;
    
} while ($pagina <= $totalPaginas && $pagina <= 130); // Limitar a 130 páginas

echo "\n========================================\n";
echo "AMOSTRA - DEZEMBRO 2025\n";
echo "========================================\n";
echo "Total de movimentos de DEZ/2025: " . count($allMovimentos) . "\n\n";

// Separar receitas PF
$receitasPF = [];
$totalPF = 0;

foreach ($allMovimentos as $mov) {
    $planoContas = $mov['planoConta.nomeCompleto'] ?? '';
    $valor = strip_tags($mov['valorComSinal'] ?? '0');
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
    $valorNum = floatval($valor);
    
    // Ignorar valores negativos
    if ($valorNum <= 0) {
        continue;
    }
    
    // RECEITA PF: 3.01.01.01 ou 3.01.01.03
    if (strpos($planoContas, '3.01.01.01') !== false || 
        strpos($planoContas, '3.01.01.03') !== false) {
        
        $receitasPF[] = [
            'id' => $mov['id'],
            'data' => $mov['data'],
            'valor' => $valorNum,
            'pessoa' => $mov['pessoa.nome'] ?? 'N/A',
            'plano' => $planoContas
        ];
        $totalPF += $valorNum;
    }
}

echo "┌─────────────────────────────────────────────────────────────────────┐\n";
echo "│              RECEITAS PF - DEZEMBRO 2025 (DETALHADO)                │\n";
echo "├─────────────────────────────────────────────────────────────────────┤\n";

foreach ($receitasPF as $i => $rec) {
    echo "│ " . str_pad(($i+1) . ". ID: " . $rec['id'], 67) . "│\n";
    echo "│    Data: " . str_pad($rec['data'], 58) . "│\n";
    echo "│    Valor: R$ " . str_pad(number_format($rec['valor'], 2, ',', '.'), 54) . "│\n";
    echo "│    Pessoa: " . str_pad(substr($rec['pessoa'], 0, 55), 56) . "│\n";
    echo "│    Plano: " . str_pad(substr($rec['plano'], -55), 56) . "│\n";
    echo "├─────────────────────────────────────────────────────────────────────┤\n";
}

echo "│ TOTAL RECEITAS PF DEZ/2025: R$ " . str_pad(number_format($totalPF, 2, ',', '.'), 36) . "│\n";
echo "│ Quantidade de registros: " . str_pad(count($receitasPF), 42) . "│\n";
echo "└─────────────────────────────────────────────────────────────────────┘\n";
