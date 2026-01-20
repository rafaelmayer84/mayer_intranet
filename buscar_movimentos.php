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

// Buscar Movimentos
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.datajuri.com.br/v1/entidades/Movimento?pagina=1&porPagina=10");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$token}",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "Erro HTTP: {$httpCode}\n";
    exit(1);
}

$data = json_decode($response, true);

echo "========================================\n";
echo "MÓDULO MOVIMENTO - AMOSTRA DE DADOS\n";
echo "========================================\n";
echo "Total de registros no sistema: " . $data['listSize'] . "\n";
echo "Registros nesta página: " . count($data['rows']) . "\n";
echo "========================================\n\n";

foreach ($data['rows'] as $i => $mov) {
    echo "┌─────────────────────────────────────────────────────────────┐\n";
    echo "│ REGISTRO " . ($i + 1) . "                                                    │\n";
    echo "├─────────────────────────────────────────────────────────────┤\n";
    echo "│ ID: " . str_pad($mov['id'] ?? 'N/A', 54) . "│\n";
    echo "│ Data: " . str_pad($mov['data'] ?? 'N/A', 52) . "│\n";
    echo "│ Valor: " . str_pad(strip_tags($mov['valorComSinal'] ?? 'N/A'), 51) . "│\n";
    echo "├─────────────────────────────────────────────────────────────┤\n";
    echo "│ PLANO DE CONTAS:                                            │\n";
    $plano = $mov['planoConta.nomeCompleto'] ?? 'N/A';
    $planoLinhas = wordwrap($plano, 57, "\n", true);
    foreach (explode("\n", $planoLinhas) as $linha) {
        echo "│ " . str_pad($linha, 58) . "│\n";
    }
    echo "├─────────────────────────────────────────────────────────────┤\n";
    echo "│ Descrição: " . str_pad(substr($mov['descricao'] ?? 'N/A', 0, 47), 47) . "│\n";
    echo "│ Pessoa: " . str_pad(substr($mov['pessoa.nome'] ?? 'N/A', 0, 50), 50) . "│\n";
    $obs = substr($mov['observacao'] ?? 'N/A', 0, 50);
    echo "│ Observação: " . str_pad($obs, 46) . "│\n";
    echo "└─────────────────────────────────────────────────────────────┘\n\n";
}
