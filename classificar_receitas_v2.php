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
$dataMinima = null;
$dataMaxima = null;

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
    
    // Buscar 1000 registros para amostra mais completa
    if (count($allMovimentos) >= 1000) {
        echo "\nLimitando a 1000 registros para amostra...\n";
        break;
    }
    
} while ($pagina <= $totalPaginas);

// Determinar período da amostra
foreach ($allMovimentos as $mov) {
    $dataStr = $mov['data'] ?? '';
    if (!empty($dataStr)) {
        // Converter DD/MM/YYYY para timestamp
        $partes = explode('/', $dataStr);
        if (count($partes) == 3) {
            $timestamp = mktime(0, 0, 0, $partes[1], $partes[0], $partes[2]);
            if ($dataMinima === null || $timestamp < $dataMinima) {
                $dataMinima = $timestamp;
            }
            if ($dataMaxima === null || $timestamp > $dataMaxima) {
                $dataMaxima = $timestamp;
            }
        }
    }
}

echo "\n========================================\n";
echo "CLASSIFICAÇÃO DE RECEITAS - AMOSTRA\n";
echo "========================================\n";
echo "Total de movimentos analisados: " . count($allMovimentos) . "\n";
echo "Período da amostra: " . date('d/m/Y', $dataMinima) . " até " . date('d/m/Y', $dataMaxima) . "\n\n";

// Classificar movimentos conforme regras
$receitasPF = [];
$receitasPJ = [];
$receitasFinanceiras = [];
$classificacaoManual = [];
$semPlanoContas = [];
$despesas = [];

foreach ($allMovimentos as $mov) {
    $planoContas = $mov['planoConta.nomeCompleto'] ?? '';
    $valor = strip_tags($mov['valorComSinal'] ?? '0');
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
    $valorNum = floatval($valor);
    
    $data = $mov['data'] ?? 'N/A';
    $mes = substr($data, 3, 2);
    $ano = substr($data, 6, 4);
    $chave = "{$ano}-{$mes}";
    
    $registro = [
        'id' => $mov['id'],
        'data' => $data,
        'valor' => $valorNum,
        'pessoa' => $mov['pessoa.nome'] ?? 'N/A',
        'descricao' => $mov['descricao'] ?? 'N/A',
        'planoContas' => $planoContas
    ];
    
    // Valores negativos são despesas (ignorar por enquanto)
    if ($valorNum < 0) {
        $despesas[] = $registro;
        continue;
    }
    
    // Sem plano de contas = desprezar
    if (empty($planoContas)) {
        $semPlanoContas[] = $registro;
        continue;
    }
    
    // RECEITA PF: 3.01.01.01 ou 3.01.01.03
    if (strpos($planoContas, '3.01.01.01') !== false || 
        strpos($planoContas, 'Receita bruta - Contrato PF') !== false ||
        strpos($planoContas, '3.01.01.03') !== false ||
        strpos($planoContas, 'Sucumbência PF') !== false) {
        
        if (!isset($receitasPF[$chave])) {
            $receitasPF[$chave] = ['total' => 0, 'quantidade' => 0, 'registros' => []];
        }
        $receitasPF[$chave]['total'] += $valorNum;
        $receitasPF[$chave]['quantidade']++;
        $receitasPF[$chave]['registros'][] = $registro;
    }
    // RECEITA PJ: 3.01.01.02 ou 3.01.01.05
    elseif (strpos($planoContas, '3.01.01.02') !== false || 
            strpos($planoContas, 'Receita Bruta - Contrato PJ') !== false ||
            strpos($planoContas, '3.01.01.05') !== false ||
            strpos($planoContas, 'Sucumbência PJ') !== false) {
        
        if (!isset($receitasPJ[$chave])) {
            $receitasPJ[$chave] = ['total' => 0, 'quantidade' => 0, 'registros' => []];
        }
        $receitasPJ[$chave]['total'] += $valorNum;
        $receitasPJ[$chave]['quantidade']++;
        $receitasPJ[$chave]['registros'][] = $registro;
    }
    // RECEITA FINANCEIRA: 3.01.02.05
    elseif (strpos($planoContas, '3.01.02.05') !== false || 
            strpos($planoContas, 'Receita financeira') !== false) {
        
        if (!isset($receitasFinanceiras[$chave])) {
            $receitasFinanceiras[$chave] = ['total' => 0, 'quantidade' => 0, 'registros' => []];
        }
        $receitasFinanceiras[$chave]['total'] += $valorNum;
        $receitasFinanceiras[$chave]['quantidade']++;
        $receitasFinanceiras[$chave]['registros'][] = $registro;
    }
    // CLASSIFICAÇÃO MANUAL: 3.01.01.06, 3.01.02.01, 3.01.02.03, 3.01.02.04, 3.01.02.06, 3.01.02.07
    elseif (strpos($planoContas, '3.01.01.06') !== false ||
            strpos($planoContas, '3.01.02.01') !== false ||
            strpos($planoContas, '3.01.02.03') !== false ||
            strpos($planoContas, '3.01.02.04') !== false ||
            strpos($planoContas, '3.01.02.06') !== false ||
            strpos($planoContas, '3.01.02.07') !== false ||
            strpos($planoContas, 'Receitas a Apurar') !== false ||
            strpos($planoContas, 'ROMID') !== false ||
            strpos($planoContas, 'Multas') !== false ||
            strpos($planoContas, 'Outras Receitas') !== false ||
            strpos($planoContas, 'exercícios anteriores') !== false) {
        
        $classificacaoManual[] = $registro;
    }
}

// Ordenar por data
krsort($receitasPF);
krsort($receitasPJ);
krsort($receitasFinanceiras);

// Exibir resultados
echo "┌─────────────────────────────────────────────────────────────────────┐\n";
echo "│                    RECEITAS PESSOA FÍSICA (PF)                      │\n";
echo "│         (3.01.01.01 Contrato PF + 3.01.01.03 Sucumbência PF)        │\n";
echo "├─────────────────────────────────────────────────────────────────────┤\n";
echo "│ Período      │ Quantidade │ Total (R$)                             │\n";
echo "├─────────────────────────────────────────────────────────────────────┤\n";

$totalGeralPF = 0;
$qtdGeralPF = 0;
foreach ($receitasPF as $periodo => $dados) {
    $totalFormatado = number_format($dados['total'], 2, ',', '.');
    echo "│ " . str_pad($periodo, 12) . " │ " . str_pad($dados['quantidade'], 10) . " │ R$ " . str_pad($totalFormatado, 35) . "│\n";
    $totalGeralPF += $dados['total'];
    $qtdGeralPF += $dados['quantidade'];
}
echo "├─────────────────────────────────────────────────────────────────────┤\n";
$totalGeralPFFormatado = number_format($totalGeralPF, 2, ',', '.');
echo "│ TOTAL PF     │ " . str_pad($qtdGeralPF, 10) . " │ R$ " . str_pad($totalGeralPFFormatado, 35) . "│\n";
echo "└─────────────────────────────────────────────────────────────────────┘\n\n";

echo "┌─────────────────────────────────────────────────────────────────────┐\n";
echo "│                   RECEITAS PESSOA JURÍDICA (PJ)                     │\n";
echo "│         (3.01.01.02 Contrato PJ + 3.01.01.05 Sucumbência PJ)        │\n";
echo "├─────────────────────────────────────────────────────────────────────┤\n";
echo "│ Período      │ Quantidade │ Total (R$)                             │\n";
echo "├─────────────────────────────────────────────────────────────────────┤\n";

$totalGeralPJ = 0;
$qtdGeralPJ = 0;
foreach ($receitasPJ as $periodo => $dados) {
    $totalFormatado = number_format($dados['total'], 2, ',', '.');
    echo "│ " . str_pad($periodo, 12) . " │ " . str_pad($dados['quantidade'], 10) . " │ R$ " . str_pad($totalFormatado, 35) . "│\n";
    $totalGeralPJ += $dados['total'];
    $qtdGeralPJ += $dados['quantidade'];
}
if (empty($receitasPJ)) {
    echo "│ Nenhuma receita PJ encontrada na amostra                           │\n";
}
echo "├─────────────────────────────────────────────────────────────────────┤\n";
$totalGeralPJFormatado = number_format($totalGeralPJ, 2, ',', '.');
echo "│ TOTAL PJ     │ " . str_pad($qtdGeralPJ, 10) . " │ R$ " . str_pad($totalGeralPJFormatado, 35) . "│\n";
echo "└─────────────────────────────────────────────────────────────────────┘\n\n";

echo "┌─────────────────────────────────────────────────────────────────────┐\n";
echo "│                      RECEITAS FINANCEIRAS                           │\n";
echo "│                        (3.01.02.05)                                 │\n";
echo "├─────────────────────────────────────────────────────────────────────┤\n";
echo "│ Período      │ Quantidade │ Total (R$)                             │\n";
echo "├─────────────────────────────────────────────────────────────────────┤\n";

$totalGeralFin = 0;
$qtdGeralFin = 0;
foreach ($receitasFinanceiras as $periodo => $dados) {
    $totalFormatado = number_format($dados['total'], 2, ',', '.');
    echo "│ " . str_pad($periodo, 12) . " │ " . str_pad($dados['quantidade'], 10) . " │ R$ " . str_pad($totalFormatado, 35) . "│\n";
    $totalGeralFin += $dados['total'];
    $qtdGeralFin += $dados['quantidade'];
}
if (empty($receitasFinanceiras)) {
    echo "│ Nenhuma receita financeira encontrada na amostra                   │\n";
}
echo "├─────────────────────────────────────────────────────────────────────┤\n";
$totalGeralFinFormatado = number_format($totalGeralFin, 2, ',', '.');
echo "│ TOTAL FIN    │ " . str_pad($qtdGeralFin, 10) . " │ R$ " . str_pad($totalGeralFinFormatado, 35) . "│\n";
echo "└─────────────────────────────────────────────────────────────────────┘\n\n";

echo "┌─────────────────────────────────────────────────────────────────────┐\n";
echo "│                    EXIGEM CLASSIFICAÇÃO MANUAL                      │\n";
echo "├─────────────────────────────────────────────────────────────────────┤\n";
$totalManual = 0;
foreach ($classificacaoManual as $reg) {
    $totalManual += $reg['valor'];
}
echo "│ Quantidade: " . str_pad(count($classificacaoManual), 55) . "│\n";
echo "│ Total: R$ " . str_pad(number_format($totalManual, 2, ',', '.'), 57) . "│\n";
echo "└─────────────────────────────────────────────────────────────────────┘\n\n";

if (count($classificacaoManual) > 0) {
    echo "=== DETALHES - CLASSIFICAÇÃO MANUAL (primeiros 10) ===\n";
    foreach (array_slice($classificacaoManual, 0, 10) as $reg) {
        echo "ID: {$reg['id']} | Data: {$reg['data']} | Valor: R$ " . number_format($reg['valor'], 2, ',', '.') . "\n";
        echo "Plano: " . substr($reg['planoContas'], 0, 80) . "\n";
        echo "Pessoa: {$reg['pessoa']}\n";
        echo "---\n";
    }
}

echo "\n┌─────────────────────────────────────────────────────────────────────┐\n";
echo "│                         RESUMO GERAL                                │\n";
echo "├─────────────────────────────────────────────────────────────────────┤\n";
echo "│ Receitas PF: " . str_pad($qtdGeralPF . " registros = R$ " . $totalGeralPFFormatado, 55) . "│\n";
echo "│ Receitas PJ: " . str_pad($qtdGeralPJ . " registros = R$ " . $totalGeralPJFormatado, 55) . "│\n";
echo "│ Receitas Financeiras: " . str_pad($qtdGeralFin . " registros = R$ " . $totalGeralFinFormatado, 45) . "│\n";
echo "│ Classificação Manual: " . str_pad(count($classificacaoManual) . " registros = R$ " . number_format($totalManual, 2, ',', '.'), 45) . "│\n";
echo "│ Sem plano de contas (desprezados): " . str_pad(count($semPlanoContas) . " registros", 31) . "│\n";
echo "│ Despesas (valores negativos): " . str_pad(count($despesas) . " registros", 37) . "│\n";
echo "└─────────────────────────────────────────────────────────────────────┘\n";
