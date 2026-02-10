<?php

namespace Database\Seeders;

use App\Models\Modulo;
use Illuminate\Database\Seeder;

class ModulosSeeder extends Seeder
{
    public function run(): void
    {
        $modulos = [
            ['slug' => 'visao-gerencial', 'nome' => 'Visão Gerencial', 'grupo' => 'RESULTADOS', 'icone' => '📊', 'ordem' => 1],
            ['slug' => 'clientes-mercado', 'nome' => 'Clientes & Mercado', 'grupo' => 'RESULTADOS', 'icone' => '👥', 'ordem' => 2],
            ['slug' => 'metas-kpi', 'nome' => 'Metas de KPIs', 'grupo' => 'RESULTADOS', 'icone' => '🎯', 'ordem' => 3],
            ['slug' => 'avisos.listar', 'nome' => 'Quadro de Avisos', 'grupo' => 'COMUNICAÇÃO', 'icone' => '📢', 'ordem' => 1],
            ['slug' => 'avisos.gerenciar', 'nome' => 'Gerenciar Avisos', 'grupo' => 'COMUNICAÇÃO', 'icone' => '✏️', 'ordem' => 2],
            ['slug' => 'minha-performance', 'nome' => 'Minha Performance', 'grupo' => 'GDP', 'icone' => '📈', 'ordem' => 1],
            ['slug' => 'equipe', 'nome' => 'Performance Equipe', 'grupo' => 'GDP', 'icone' => '👨‍👩‍👧‍👦', 'ordem' => 2],
            ['slug' => 'usuarios', 'nome' => 'Usuários', 'grupo' => 'ADMINISTRAÇÃO', 'icone' => '👤', 'ordem' => 1],
            ['slug' => 'sincronizacao', 'nome' => 'Sincronização', 'grupo' => 'ADMINISTRAÇÃO', 'icone' => '🔄', 'ordem' => 2],
            ['slug' => 'integracoes', 'nome' => 'Integrações', 'grupo' => 'ADMINISTRAÇÃO', 'icone' => '🔗', 'ordem' => 3],
            ['slug' => 'configuracoes', 'nome' => 'Configurações', 'grupo' => 'ADMINISTRAÇÃO', 'icone' => '⚙️', 'ordem' => 4],
        ];

        foreach ($modulos as $m) {
            Modulo::updateOrCreate(['slug' => $m['slug']], array_merge($m, ['ativo' => true]));
        }

        echo "✅ " . count($modulos) . " módulos criados/atualizados!\n";
    }
}
