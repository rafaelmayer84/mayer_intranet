<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Configuracao;

echo "Populando configurações padrão...\n\n";

Configuracao::set('ano_filtro', 2025, 'integer');
echo "✓ ano_filtro = 2025\n";

Configuracao::set('nome_escritorio', 'Mayer Advogados', 'string');
echo "✓ nome_escritorio = Mayer Advogados\n";

Configuracao::set('meta_faturamento', 100000, 'float');
echo "✓ meta_faturamento = 100000\n";

Configuracao::set('meta_horas', 1200, 'float');
echo "✓ meta_horas = 1200\n";

Configuracao::set('meta_processos', 50, 'integer');
echo "✓ meta_processos = 50\n";

Configuracao::set('peso_financeiro', 25, 'integer');
echo "✓ peso_financeiro = 25\n";

Configuracao::set('peso_clientes', 25, 'integer');
echo "✓ peso_clientes = 25\n";

Configuracao::set('peso_processos', 25, 'integer');
echo "✓ peso_processos = 25\n";

Configuracao::set('peso_aprendizado', 25, 'integer');
echo "✓ peso_aprendizado = 25\n";

echo "\n✅ Configurações padrão criadas com sucesso!\n";
