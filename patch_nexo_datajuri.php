#!/usr/bin/env php
<?php
/**
 * Patcher: NexoConsultaService - Consulta DataJuri em tempo real + OpenAI
 * Uso: php patch_nexo_datajuri.php
 */

$file = __DIR__ . '/app/Services/NexoConsultaService.php';
$snippet = __DIR__ . '/nexo_metodos_datajuri.php';

if (!file_exists($file)) {
    echo "ERRO: Arquivo NexoConsultaService.php não encontrado.\n";
    exit(1);
}
if (!file_exists($snippet)) {
    echo "ERRO: Snippet nexo_metodos_datajuri.php não encontrado.\n";
    exit(1);
}

// Backup
$backup = $file . '.bak_' . date('Ymd_His');
copy($file, $backup);
echo "✅ Backup: {$backup}\n";

$conteudo = file_get_contents($file);
$novosMethods = file_get_contents($snippet);

// 1. Adicionar "use Http" se não existir
if (strpos($conteudo, 'use Illuminate\Support\Facades\Http;') === false) {
    $conteudo = str_replace(
        "use Carbon\\Carbon;",
        "use Carbon\\Carbon;\nuse Illuminate\\Support\\Facades\\Http;",
        $conteudo
    );
    echo "✅ Adicionado: use Illuminate\\Support\\Facades\\Http;\n";
} else {
    echo "⏭️  use Http já existe.\n";
}

// 2. Encontrar e substituir o bloco entre os marcadores
$marcadorInicio = '// MÉTODOS PRIVADOS — CONSULTA PROCESSOS';
$marcadorFim = '// MÉTODOS PRIVADOS — UTILITÁRIOS';

$posInicio = strpos($conteudo, $marcadorInicio);
$posFim = strpos($conteudo, $marcadorFim);

if ($posInicio === false) {
    echo "ERRO: Marcador CONSULTA PROCESSOS não encontrado.\n";
    exit(1);
}
if ($posFim === false) {
    echo "ERRO: Marcador UTILITÁRIOS não encontrado.\n";
    exit(1);
}

// Substituir tudo entre os dois marcadores (incluindo o primeiro, excluindo o segundo)
$antes = substr($conteudo, 0, $posInicio);
$depois = substr($conteudo, $posFim);

$conteudoNovo = $antes . $novosMethods . "\n    " . $depois;

file_put_contents($file, $conteudoNovo);
echo "✅ Métodos substituídos com sucesso.\n";

// 3. Validar sintaxe PHP
$output = [];
$returnCode = 0;
exec("php -l {$file} 2>&1", $output, $returnCode);

if ($returnCode === 0) {
    echo "✅ Sintaxe PHP válida.\n";
    echo "🎉 Patch aplicado com sucesso!\n";
} else {
    echo "❌ ERRO DE SINTAXE! Restaurando backup...\n";
    copy($backup, $file);
    echo "Restaurado de: {$backup}\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}
