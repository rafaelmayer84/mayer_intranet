<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmStagesSeeder extends Seeder
{
    public function run(): void
    {
        // Só insere se tabela estiver vazia
        if (DB::table('crm_stages')->count() > 0) {
            $this->command->info('CRM Stages já existem. Pulando seed.');
            return;
        }

        $stages = [
            ['name' => 'Lead Novo',   'sort' => 10, 'is_won' => false, 'is_lost' => false, 'color' => '#9CA3AF'],
            ['name' => 'Em Contato',  'sort' => 20, 'is_won' => false, 'is_lost' => false, 'color' => '#3B82F6'],
            ['name' => 'Proposta',    'sort' => 30, 'is_won' => false, 'is_lost' => false, 'color' => '#F59E0B'],
            ['name' => 'Negociação',  'sort' => 40, 'is_won' => false, 'is_lost' => false, 'color' => '#8B5CF6'],
            ['name' => 'Ganho',       'sort' => 90, 'is_won' => true,  'is_lost' => false, 'color' => '#10B981'],
            ['name' => 'Perdido',     'sort' => 99, 'is_won' => false, 'is_lost' => true,  'color' => '#EF4444'],
        ];

        $now = now();
        foreach ($stages as &$stage) {
            $stage['is_active'] = true;
            $stage['created_at'] = $now;
            $stage['updated_at'] = $now;
        }

        DB::table('crm_stages')->insert($stages);
        $this->command->info('6 CRM Stages criados com sucesso.');
    }
}
