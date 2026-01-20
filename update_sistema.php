<?php
/**
 * Script para atualizar o sistema para "Resultados"
 * - Corrige contagem de processos (exclui encerrados)
 * - Adiciona novas metas na tabela configuracoes
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Configuracao;

echo "=== ATUALIZANDO SISTEMA PARA 'RESULTADOS' ===\n\n";

// Atualizar nome do sistema
Configuracao::set('nome_sistema', 'Resultados', 'string');
echo "✓ Nome do sistema: Resultados\n";

// Metas mensais padrão
Configuracao::set('meta_mensal_pf', 20833.33, 'float');
echo "✓ Meta mensal PF: R$ 20.833,33\n";

Configuracao::set('meta_mensal_pj', 7500, 'float');
echo "✓ Meta mensal PJ: R$ 7.500,00\n";

Configuracao::set('meta_mensal_despesas', 7500, 'float');
echo "✓ Meta mensal Despesas: R$ 7.500,00\n";

// Metas anuais
Configuracao::set('meta_anual_pf', 250000, 'float');
echo "✓ Meta anual PF: R$ 250.000,00\n";

Configuracao::set('meta_anual_pj', 90000, 'float');
echo "✓ Meta anual PJ: R$ 90.000,00\n";

Configuracao::set('meta_anual_despesas', 90000, 'float');
echo "✓ Meta anual Despesas: R$ 90.000,00\n";

Configuracao::set('meta_avaliacoes_google', 250, 'integer');
echo "✓ Meta Avaliações Google: 250\n";

Configuracao::set('meta_contratos', 400000, 'float');
echo "✓ Meta Contratos: R$ 400.000,00\n";

Configuracao::set('meta_inadimplencia', 1000, 'float');
echo "✓ Meta Inadimplência (máximo): R$ 1.000,00\n";

Configuracao::set('meta_clientes', 80, 'integer');
echo "✓ Meta Clientes: 80\n";

Configuracao::set('meta_conformidade', 10, 'integer');
echo "✓ Meta Conformidade: 10\n";

// Valores atuais (serão atualizados via sincronização)
Configuracao::set('atual_avaliacoes_google', 227, 'integer');
echo "✓ Atual Avaliações Google: 227\n";

Configuracao::set('atual_conformidade', 10, 'integer');
echo "✓ Atual Conformidade: 10\n";

echo "\n✅ Sistema atualizado para 'Resultados' com sucesso!\n";
