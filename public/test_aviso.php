<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Aviso;

try {
    $aviso = Aviso::find(1);
    echo "Aviso encontrado: {$aviso->titulo}\n";
    echo "criado_por: {$aviso->criado_por}\n";
    
    $aviso->load(['categoria', 'criadoPor']);
    echo "Load OK\n";
    
    echo "Categoria: " . ($aviso->categoria ? $aviso->categoria->nome : 'NULL') . "\n";
    echo "Criado por: " . ($aviso->criadoPor ? $aviso->criadoPor->name : 'NULL') . "\n";
    
    $aviso->loadCount('usuariosLidos');
    echo "LoadCount OK\n";
    echo "usuariosLidos count: {$aviso->usuarios_lidos_count}\n";
    
} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
}
