<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmStagesV2Seeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            ['name' => 'Lead Novo',   'slug' => 'lead-novo',   'color' => '#3b82f6', 'order' => 1, 'is_won' => false, 'is_lost' => false],
            ['name' => 'Em Contato',  'slug' => 'em-contato',  'color' => '#8b5cf6', 'order' => 2, 'is_won' => false, 'is_lost' => false],
            ['name' => 'Proposta',    'slug' => 'proposta',    'color' => '#f59e0b', 'order' => 3, 'is_won' => false, 'is_lost' => false],
            ['name' => 'Negociação',  'slug' => 'negociacao',  'color' => '#f97316', 'order' => 4, 'is_won' => false, 'is_lost' => false],
            ['name' => 'Ganho',       'slug' => 'ganho',       'color' => '#22c55e', 'order' => 5, 'is_won' => true,  'is_lost' => false],
            ['name' => 'Perdido',     'slug' => 'perdido',     'color' => '#ef4444', 'order' => 6, 'is_won' => false, 'is_lost' => true],
        ];

        foreach ($stages as $stage) {
            DB::table('crm_stages')->updateOrInsert(
                ['slug' => $stage['slug']],
                array_merge($stage, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
