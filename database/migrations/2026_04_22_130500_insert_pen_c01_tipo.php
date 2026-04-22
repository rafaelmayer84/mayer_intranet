<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('gdp_penalizacao_tipos')->where('codigo', 'PEN-C01')->exists();
        if ($exists) return;

        DB::table('gdp_penalizacao_tipos')->insert([
            'codigo'            => 'PEN-C01',
            'eixo_id'           => 4,
            'nome'              => 'Gate CRM nao sanado',
            'descricao'         => 'Divergencia entre DataJuri e realidade (contrato/processo/contas) foi sinalizada ao advogado responsavel, que nao ajustou o DJ em 7 dias apos revisao obrigatoria.',
            'gravidade'         => 'moderada',
            'pontos_desconto'   => 3,
            'threshold_valor'   => 7,
            'threshold_unidade' => 'dias',
            'fonte_tabela'      => 'crm_account_data_gates',
            'ativo'             => 1,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('gdp_penalizacao_tipos')->where('codigo', 'PEN-C01')->delete();
    }
};
