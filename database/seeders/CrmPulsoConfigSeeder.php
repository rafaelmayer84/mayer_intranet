<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmPulsoConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            [
                'chave'     => 'max_contatos_dia',
                'valor'     => '5',
                'descricao' => 'Máximo de contatos (todas as fontes) por dia antes de alerta. Ref: Cláusula Uso Excessivo, item (i).',
            ],
            [
                'chave'     => 'max_atualizacao_semana_sem_mov',
                'valor'     => '3',
                'descricao' => 'Máximo de pedidos de atualização por semana quando não há movimentação processual nova. Ref: item (iv).',
            ],
            [
                'chave'     => 'reiteracao_horas',
                'valor'     => '48',
                'descricao' => 'Horas úteis mínimas entre demandas idênticas (mesma conversa, já respondida). Ref: item (ii).',
            ],
            [
                'chave'     => 'fora_horario_tolerancia',
                'valor'     => '3',
                'descricao' => 'Contatos fora do horário (antes 8h ou após 18h seg-sex, ou fins de semana) por semana antes de alerta.',
            ],
        ];

        foreach ($configs as $c) {
            DB::table('crm_pulso_config')->updateOrInsert(
                ['chave' => $c['chave']],
                $c
            );
        }
    }
}
