<?php
require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = new App\Services\DataJuriService();
$token = $service->getToken();

echo "Token: " . (strlen($token) > 50 ? "OK" : "FAIL") . "\n";
echo "Testando /v1/modulos com timeout 120s...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.datajuri.com.br/v1/modulos");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) echo "Error: $error\n";

if ($httpCode == 200 && $response) {
    $data = json_decode($response, true);
    $sample = array_slice($data, 0, 3);
    file_put_contents('/tmp/datajuri_modulos_sample.json', json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "✅ Dados recebidos! Sample salvo\n";
    echo "Total módulos: " . count($data) . "\n";
} else {
    echo "❌ Falha na requisição\n";
}
