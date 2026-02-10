<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClassificacaoRegra;
use Illuminate\Support\Facades\DB;

class ClassificacaoRegraSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpar tabela (se necessário)
        // ClassificacaoRegra::truncate();

        $regras = [
            // ========== RECEITAS PESSOA FÍSICA ==========
            [
                'codigo_plano' => '3.01.01.01',
                'nome_plano' => 'Receita de Honorários - Pessoa Física',
                'classificacao' => 'RECEITA_PF',
                'tipo_movimento' => 'RECEITA',
                'ativo' => true,
                'prioridade' => 50,
                'origem' => 'manual',
                'observacoes' => 'Regra padrão para honorários PF',
            ],
            [
                'codigo_plano' => '3.01.01.03',
                'nome_plano' => 'Receita de Honorários PF - Quota Litis',
                'classificacao' => 'RECEITA_PF',
                'tipo_movimento' => 'RECEITA',
                'ativo' => true,
                'prioridade' => 50,
                'origem' => 'manual',
                'observacoes' => 'Regra padrão para quota litis PF',
            ],

            // ========== RECEITAS PESSOA JURÍDICA ==========
            [
                'codigo_plano' => '3.01.01.02',
                'nome_plano' => 'Receita de Honorários - Pessoa Jurídica',
                'classificacao' => 'RECEITA_PJ',
                'tipo_movimento' => 'RECEITA',
                'ativo' => true,
                'prioridade' => 50,
                'origem' => 'manual',
                'observacoes' => 'Regra padrão para honorários PJ',
            ],
            [
                'codigo_plano' => '3.01.01.05',
                'nome_plano' => 'Receita de Honorários PJ - Quota Litis',
                'classificacao' => 'RECEITA_PJ',
                'tipo_movimento' => 'RECEITA',
                'ativo' => true,
                'prioridade' => 50,
                'origem' => 'manual',
                'observacoes' => 'Regra padrão para quota litis PJ',
            ],

            // ========== DESPESAS (WILDCARDS) ==========
            [
                'codigo_plano' => '3.01.02.%',
                'nome_plano' => 'Despesas Operacionais (Genérico)',
                'classificacao' => 'DESPESA',
                'tipo_movimento' => 'DESPESA',
                'ativo' => true,
                'prioridade' => 10, // Prioridade baixa pois é wildcard
                'origem' => 'manual',
                'observacoes' => 'Wildcard para todas as despesas operacionais iniciadas com 3.01.02',
            ],

            // ========== EXEMPLOS DE REGRAS ESPECÍFICAS DE DESPESAS ==========
            [
                'codigo_plano' => '3.01.02.01',
                'nome_plano' => 'Despesas com Aluguel',
                'classificacao' => 'DESPESA',
                'tipo_movimento' => 'DESPESA',
                'ativo' => true,
                'prioridade' => 40,
                'origem' => 'manual',
                'observacoes' => 'Aluguel do escritório',
            ],
            [
                'codigo_plano' => '3.01.02.05',
                'nome_plano' => 'Despesas com Sistemas e Software',
                'classificacao' => 'DESPESA',
                'tipo_movimento' => 'DESPESA',
                'ativo' => true,
                'prioridade' => 40,
                'origem' => 'manual',
                'observacoes' => 'Sistemas de gestão, software, TI',
            ],
        ];

        foreach ($regras as $regra) {
            ClassificacaoRegra::create($regra);
        }

        $this->command->info('✓ ' . count($regras) . ' regras de classificação criadas com sucesso!');
        $this->command->info('  - 4 regras de RECEITA (2 PF + 2 PJ)');
        $this->command->info('  - 3 regras de DESPESA (1 wildcard + 2 específicas)');
        $this->command->info('');
        $this->command->info('💡 Acesse /admin/classificacao-regras para gerenciar');
    }
}
