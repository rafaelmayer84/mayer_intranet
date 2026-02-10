<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassificacaoRegrasSeeder extends Seeder
{
    public function run(): void
    {
        $regras = [
            ['codigo_plano' => '3.01.01.01', 'nome_plano' => 'Receita bruta - Contrato PF', 'classificacao' => 'RECEITA_PF', 'grupo_dre' => '3.01.01', 'origem' => 
'MANUAL', 'ativo' => true],
            ['codigo_plano' => '3.01.01.02', 'nome_plano' => 'Receita Bruta - Contrato PJ', 'classificacao' => 'RECEITA_PJ', 'grupo_dre' => '3.01.01', 'origem' => 
'MANUAL', 'ativo' => true],
            ['codigo_plano' => '3.01.01.03', 'nome_plano' => 'Receita bruta - Contrato PF (Secundário)', 'classificacao' => 'RECEITA_PF', 'grupo_dre' => '3.01.01', 
'origem' => 'MANUAL', 'ativo' => true],
            ['codigo_plano' => '3.01.01.05', 'nome_plano' => 'Receita bruta - Contrato PJ (Secundário)', 'classificacao' => 'RECEITA_PJ', 'grupo_dre' => '3.01.01', 
'origem' => 'MANUAL', 'ativo' => true],
            ['codigo_plano' => '3.01.02.05', 'nome_plano' => 'Receita Financeira', 'classificacao' => 'RECEITA_FINANCEIRA', 'grupo_dre' => '3.01.02', 'origem' => 
'MANUAL', 'ativo' => true],
            ['codigo_plano' => '3.01.02.06', 'nome_plano' => 'Multas', 'classificacao' => 'RECEITA_FINANCEIRA', 'grupo_dre' => '3.01.02', 'origem' => 'MANUAL', 
'ativo' => true],
            ['codigo_plano' => '3.01.03.01', 'nome_plano' => 'Simples Nacional', 'classificacao' => 'DEDUCAO', 'grupo_dre' => '3.01.03', 'origem' => 'MANUAL', 'ativo' 
=> true],
            ['codigo_plano' => '3.01.03.04', 'nome_plano' => 'Serviços PF sem Vínculo', 'classificacao' => 'DEDUCAO', 'grupo_dre' => '3.01.03', 'origem' => 'MANUAL', 
'ativo' => true],
            ['codigo_plano' => '3.01.03.05', 'nome_plano' => 'INSS', 'classificacao' => 'DEDUCAO', 'grupo_dre' => '3.01.03', 'origem' => 'MANUAL', 'ativo' => true],
            ['codigo_plano' => '3.01.03.08', 'nome_plano' => 'Salários', 'classificacao' => 'DEDUCAO', 'grupo_dre' => '3.01.03', 'origem' => 'MANUAL', 'ativo' => 
true],
            ['codigo_plano' => '3.01.03.09', 'nome_plano' => 'Distribuição de lucros', 'classificacao' => 'DEDUCAO', 'grupo_dre' => '3.01.03', 'origem' => 'MANUAL', 
'ativo' => true],
            ['codigo_plano' => '3.02.01.02', 'nome_plano' => 'Publicidade e Campanhas', 'classificacao' => 'DESPESA', 'grupo_dre' => '3.02.01', 'origem' => 'MANUAL', 
'ativo' => true],
            ['codigo_plano' => '3.02.01.03', 'nome_plano' => 'Aluguéis', 'classificacao' => 'DESPESA', 'grupo_dre' => '3.02.01', 'origem' => 'MANUAL', 'ativo' => 
true],
            ['codigo_plano' => '3.02.01.05', 'nome_plano' => 'Condomínio', 'classificacao' => 'DESPESA', 'grupo_dre' => '3.02.01', 'origem' => 'MANUAL', 'ativo' => 
true],
            ['codigo_plano' => '3.02.01.07', 'nome_plano' => 'Pagamento a fornecedores', 'classificacao' => 'DESPESA', 'grupo_dre' => '3.02.01', 'origem' => 'MANUAL', 
'ativo' => true],
            ['codigo_plano' => '3.02.01.07.01', 'nome_plano' => 'Tarifas Bancárias', 'classificacao' => 'DESPESA', 'grupo_dre' => '3.02.01', 'origem' => 'MANUAL', 
'ativo' => true],
            ['codigo_plano' => '3.02.01.07.04', 'nome_plano' => 'Sistemas TI', 'classificacao' => 'DESPESA', 'grupo_dre' => '3.02.01', 'origem' => 'MANUAL', 'ativo' 
=> true],
            ['codigo_plano' => '3.02.01.07.05', 'nome_plano' => 'PAMS', 'classificacao' => 'DESPESA', 'grupo_dre' => '3.02.01', 'origem' => 'MANUAL', 'ativo' => 
true],
        ];

        foreach ($regras as $regra) {
            DB::table('classificacao_regras')->updateOrInsert(
                ['codigo_plano' => $regra['codigo_plano']],
                array_merge($regra, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
