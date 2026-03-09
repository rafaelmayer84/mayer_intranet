<?php
require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = new App\Services\DataJuriService();

echo "=== EXTRAINDO AMOSTRAS DE TODOS OS MÓDULOS ===\n\n";

// 1. Buscar lista de módulos
echo "1. Buscando lista de módulos...\n";
$modulos = $service->getModulos();
echo "   ✅ " . count($modulos) . " módulos encontrados\n\n";

$outputDir = '/tmp/datajuri_amostras';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$resumo = [];
$sucessos = 0;
$falhas = 0;

// 2. Para cada módulo, tentar extrair amostra
foreach ($modulos as $index => $modulo) {
    $nomeModulo = $modulo['nome'] ?? 'Desconhecido';
    $rotuloModulo = $modulo['rotulo'] ?? $nomeModulo;
    
    echo sprintf("[%d/%d] Processando: %s (%s)...\n", $index + 1, count($modulos), $nomeModulo, $rotuloModulo);
    
    try {
        $resultado = $service->buscarModuloPagina($nomeModulo, 1, 10);
        
        $rows = $resultado['rows'] ?? [];
        $listSize = $resultado['listSize'] ?? 0;
        
        if (!empty($rows)) {
            $filename = $outputDir . '/' . preg_replace('/[^a-zA-Z0-9_]/', '_', $nomeModulo) . '.json';
            file_put_contents($filename, json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            echo "   ✅ Sucesso: {$listSize} registros disponíveis, " . count($rows) . " na amostra\n";
            
            $resumo[] = [
                'modulo' => $nomeModulo,
                'rotulo' => $rotuloModulo,
                'total' => $listSize,
                'amostra' => count($rows),
                'status' => 'sucesso',
                'arquivo' => basename($filename)
            ];
            $sucessos++;
        } else {
            echo "   ⚠️  Vazio: 0 registros\n";
            $resumo[] = [
                'modulo' => $nomeModulo,
                'rotulo' => $rotuloModulo,
                'total' => 0,
                'amostra' => 0,
                'status' => 'vazio',
                'arquivo' => null
            ];
        }
    } catch (Exception $e) {
        echo "   ❌ Erro: " . $e->getMessage() . "\n";
        $resumo[] = [
            'modulo' => $nomeModulo,
            'rotulo' => $rotuloModulo,
            'total' => 0,
            'amostra' => 0,
            'status' => 'erro',
            'erro' => $e->getMessage(),
            'arquivo' => null
        ];
        $falhas++;
    }
    
    echo "\n";
    
    // Pequeno delay para não sobrecarregar a API
    usleep(500000); // 0.5 segundo
}

// 3. Salvar resumo
file_put_contents($outputDir . '/_RESUMO.json', json_encode($resumo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "=== CONCLUÍDO ===\n";
echo "Total de módulos: " . count($modulos) . "\n";
echo "Sucessos: $sucessos\n";
echo "Vazios/Falhas: " . (count($modulos) - $sucessos) . "\n";
echo "Arquivos salvos em: $outputDir\n";
