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

// Buscar todos os Movimentos (várias páginas)
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
    $allMovimentos = array_merge($allMovimentos, $rows);
    
    $listSize = $data['listSize'] ?? 0;
    $totalPaginas = ceil($listSize / $porPagina);
    
    echo "Página {$pagina}/{$totalPaginas} - " . count($rows) . " registros\n";
    
    $pagina++;
    
    // Limitar a 500 registros para teste
    if (count($allMovimentos) >= 500) {
        echo "\nLimitando a 500 registros para teste...\n";
        break;
    }
    
} while ($pagina <= $totalPaginas);

echo "\n========================================\n";
echo "CLASSIFICAÇÃO DE RECEITAS PF/PJ\n";
echo "========================================\n";
echo "Total de movimentos analisados: " . count($allMovimentos) . "\n\n";

// Classificar movimentos
$receitasPF = [];
$receitasPJ = [];
$semPlanoContas = [];
$desprezados = 0;

foreach ($allMovimentos as $mov) {
    $planoContas = $mov['planoConta.nomeCompleto'] ?? '';
    $valor = strip_tags($mov['valorComSinal'] ?? '0');
    $valor = str_replace('.', '', $valor); // Remove separador de milhar
    $valor = str_replace(',', '.', $valor); // Troca vírgula por ponto
    $valorNum = floatval($valor);
    
    // Ignorar valores negativos (não são receitas)
    if ($valorNum < 0) {
        continue;
    }
    
    // Sem plano de contas = desprezar
    if (empty($planoContas)) {
        $semPlanoContas[] = $mov;
        $desprezados++;
        continue;
    }
    
    // Verificar se é Receita PF
    if (stripos($planoContas, 'Contrato PF') !== false || 
        stripos($planoContas, 'Receita bruta - Contrato PF') !== false) {
        
        $data = $mov['data'] ?? 'N/A';
        $mes = substr($data, 3, 2); // Extrair mês (formato DD/MM/YYYY)
        $ano = substr($data, 6, 4); // Extrair ano
        $chave = "{$ano}-{$mes}";
        
        if (!isset($receitasPF[$chave])) {
            $receitasPF[$chave] = ['total' => 0, 'quantidade' => 0, 'registros' => []];
        }
        $receitasPF[$chave]['total'] += $valorNum;
        $receitasPF[$chave]['quantidade']++;
        $receitasPF[$chave]['registros'][] = [
            'id' => $mov['id'],
            'data' => $data,
            'valor' => $valorNum,
            'pessoa' => $mov['pessoa.nome'] ?? 'N/A'
        ];
    }
    // Verificar se é Receita PJ
    elseif (stripos($planoContas, 'Contrato PJ') !== false || 
            stripos($planoContas, 'Receita bruta - Contrato PJ') !== false ||
            stripos($planoContas, 'Receita Bruta - Contrato PJ') !== false) {
        
        $data = $mov['data'] ?? 'N/A';
        $mes = substr($data, 3, 2);
        $ano = substr($data, 6, 4);
        $chave = "{$ano}-{$mes}";
        
        if (!isset($receitasPJ[$chave])) {
            $receitasPJ[$chave] = ['total' => 0, 'quantidade' => 0, 'registros' => []];
        }
        $receitasPJ[$chave]['total'] += $valorNum;
        $receitasPJ[$chave]['quantidade']++;
        $receitasPJ[$chave]['registros'][] = [
            'id' => $mov['id'],
            'data' => $data,
            'valor' => $valorNum,
            'pessoa' => $mov['pessoa.nome'] ?? 'N/A'
        ];
    }
}

// Ordenar por data
krsort($receitasPF);
krsort($receitasPJ);

echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│                    RECEITAS PESSOA FÍSICA (PF)                 │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";
echo "│ Período      │ Quantidade │ Total (R$)                        │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";

$totalGeralPF = 0;
$qtdGeralPF = 0;
foreach ($receitasPF as $periodo => $dados) {
    $totalFormatado = number_format($dados['total'], 2, ',', '.');
    echo "│ " . str_pad($periodo, 12) . " │ " . str_pad($dados['quantidade'], 10) . " │ R$ " . str_pad($totalFormatado, 30) . "│\n";
    $totalGeralPF += $dados['total'];
    $qtdGeralPF += $dados['quantidade'];
}
echo "├────────────────────────────────────────────────────────────────┤\n";
$totalGeralPFFormatado = number_format($totalGeralPF, 2, ',', '.');
echo "│ TOTAL PF     │ " . str_pad($qtdGeralPF, 10) . " │ R$ " . str_pad($totalGeralPFFormatado, 30) . "│\n";
echo "└────────────────────────────────────────────────────────────────┘\n\n";

echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│                   RECEITAS PESSOA JURÍDICA (PJ)                │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";
echo "│ Período      │ Quantidade │ Total (R$)                        │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";

$totalGeralPJ = 0;
$qtdGeralPJ = 0;
foreach ($receitasPJ as $periodo => $dados) {
    $totalFormatado = number_format($dados['total'], 2, ',', '.');
    echo "│ " . str_pad($periodo, 12) . " │ " . str_pad($dados['quantidade'], 10) . " │ R$ " . str_pad($totalFormatado, 30) . "│\n";
    $totalGeralPJ += $dados['total'];
    $qtdGeralPJ += $dados['quantidade'];
}
if (empty($receitasPJ)) {
    echo "│ Nenhuma receita PJ encontrada na amostra                      │\n";
}
echo "├────────────────────────────────────────────────────────────────┤\n";
$totalGeralPJFormatado = number_format($totalGeralPJ, 2, ',', '.');
echo "│ TOTAL PJ     │ " . str_pad($qtdGeralPJ, 10) . " │ R$ " . str_pad($totalGeralPJFormatado, 30) . "│\n";
echo "└────────────────────────────────────────────────────────────────┘\n\n";

echo "┌────────────────────────────────────────────────────────────────┐\n";
echo "│                         RESUMO GERAL                           │\n";
echo "├────────────────────────────────────────────────────────────────┤\n";
echo "│ Receitas PF: " . str_pad($qtdGeralPF . " registros = R$ " . $totalGeralPFFormatado, 50) . "│\n";
echo "│ Receitas PJ: " . str_pad($qtdGeralPJ . " registros = R$ " . $totalGeralPJFormatado, 50) . "│\n";
echo "│ Sem plano de contas (desprezados): " . str_pad($desprezados . " registros", 26) . "│\n";
echo "└────────────────────────────────────────────────────────────────┘\n\n";

// Mostrar alguns exemplos de receitas PF
echo "=== EXEMPLOS DE RECEITAS PF (últimos 5) ===\n";
$exemplos = 0;
foreach ($receitasPF as $periodo => $dados) {
    foreach (array_slice($dados['registros'], 0, 2) as $reg) {
        echo "ID: {$reg['id']} | Data: {$reg['data']} | Valor: R$ " . number_format($reg['valor'], 2, ',', '.') . " | Pessoa: {$reg['pessoa']}\n";
        $exemplos++;
        if ($exemplos >= 5) break 2;
    }
}

echo "\n=== EXEMPLOS DE RECEITAS PJ (últimos 5) ===\n";
$exemplos = 0;
foreach ($receitasPJ as $periodo => $dados) {
    foreach (array_slice($dados['registros'], 0, 2) as $reg) {
        echo "ID: {$reg['id']} | Data: {$reg['data']} | Valor: R$ " . number_format($reg['valor'], 2, ',', '.') . " | Pessoa: {$reg['pessoa']}\n";
        $exemplos++;
        if ($exemplos >= 5) break 2;
    }
}
if ($exemplos == 0) {
    echo "Nenhuma receita PJ encontrada na amostra\n";
}
