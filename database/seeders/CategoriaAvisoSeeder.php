<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CategoriaAvisoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Evita quebrar em ambientes onde as migrations ainda não rodaram.
        if (!Schema::hasTable('categorias_avisos')) {
            return;
        }

        $categorias = [
            [
                'nome' => 'Crítica',
                'descricao' => 'Avisos críticos que requerem ação imediata',
                'cor_hexadecimal' => '#dc2626',
                'icone' => 'alert-triangle',
                'ordem' => 1,
                'ativo' => true,
            ],
            [
                'nome' => 'Alta',
                'descricao' => 'Avisos importantes que devem ser lidos em breve',
                'cor_hexadecimal' => '#ea580c',
                'icone' => 'alert-circle',
                'ordem' => 2,
                'ativo' => true,
            ],
            [
                'nome' => 'Média',
                'descricao' => 'Avisos normais de rotina',
                'cor_hexadecimal' => '#eab308',
                'icone' => 'info',
                'ordem' => 3,
                'ativo' => true,
            ],
            [
                'nome' => 'Baixa',
                'descricao' => 'Avisos informativos de baixa prioridade',
                'cor_hexadecimal' => '#22c55e',
                'icone' => 'check-circle',
                'ordem' => 4,
                'ativo' => true,
            ],
        ];

        foreach ($categorias as $cat) {
            DB::table('categorias_avisos')->updateOrInsert(
                ['nome' => $cat['nome']],
                [
                    'descricao' => $cat['descricao'],
                    'cor_hexadecimal' => $cat['cor_hexadecimal'],
                    'icone' => $cat['icone'],
                    'ordem' => $cat['ordem'],
                    'ativo' => $cat['ativo'],
                    'updated_at' => now(),
                    // Para não sobrescrever created_at em runs posteriores
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }
    }
}
