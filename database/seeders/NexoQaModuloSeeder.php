<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NexoQaModuloSeeder extends Seeder
{
    public function run(): void
    {
        $slug = 'operacional.nexo-qualidade';

        // Idempotente: não duplicar
        if (DB::table('modulos')->where('slug', $slug)->exists()) {
            $this->command->info("Módulo '{$slug}' já existe. Skipped.");
            return;
        }

        DB::table('modulos')->insert([
            'nome' => 'NEXO Qualidade (Pesquisa)',
            'slug' => $slug,
            'descricao' => 'Pesquisa de qualidade de atendimento — amostragem, disparo, respostas e relatórios',
            'grupo' => 'operacional',
            'icone' => 'clipboard-check',
            'ordem' => 65,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info("Módulo '{$slug}' registrado com sucesso.");
    }
}
