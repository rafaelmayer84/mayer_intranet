<?php
require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = new App\Services\DataJuriService();

echo "=== CAPTURANDO AMOSTRA DE DADOS DA API DATAJURI ===\n\n";

// 1. Módulos
echo "1. Buscando módulos...\n";
$modulos = $service->getModulos();
$modulosSample = array_slice($modulos, 0, 5);
file_put_contents('/tmp/datajuri_modulos_sample.json', json_encode($modulosSample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "   ✅ " . count($modulos) . " módulos encontrados\n";
echo "   📄 Amostra salva: /tmp/datajuri_modulos_sample.json\n\n";

// 2. Pessoas
echo "2. Buscando pessoas...\n";
$pessoas = $service->buscarModuloPagina('Pessoa', 1, 3);
file_put_contents('/tmp/datajuri_pessoas_sample.json', json_encode($pessoas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "   ✅ " . $pessoas['listSize'] . " pessoas disponíveis\n";
echo "   📄 Amostra salva: /tmp/datajuri_pessoas_sample.json\n\n";

// 3. Processos
echo "3. Buscando processos...\n";
$processos = $service->buscarModuloPagina('Processo', 1, 3);
file_put_contents('/tmp/datajuri_processos_sample.json', json_encode($processos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "   ✅ " . $processos['listSize'] . " processos disponíveis\n";
echo "   📄 Amostra salva: /tmp/datajuri_processos_sample.json\n\n";

// 4. Movimentos
echo "4. Buscando movimentos...\n";
$movimentos = $service->buscarModuloPagina('Movimento', 1, 3);
file_put_contents('/tmp/datajuri_movimentos_sample.json', json_encode($movimentos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "   ✅ " . $movimentos['listSize'] . " movimentos disponíveis\n";
echo "   📄 Amostra salva: /tmp/datajuri_movimentos_sample.json\n\n";

echo "=== CONCLUÍDO ===\n";
echo "Todos os arquivos JSON foram salvos em /tmp/\n";
