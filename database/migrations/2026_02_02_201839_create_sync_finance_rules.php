<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_finance_rules', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->enum('tipo_classificacao', ['receita', 'despesa']);
            $table->string('campo');
            $table->string('operador');
            $table->string('valor');
            $table->integer('prioridade')->default(100);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index(['ativo', 'prioridade']);
        });

        // Inserir regras padrão
        DB::table('sync_finance_rules')->insert([
            ['nome' => 'Receita PF - Código 3.01.01.01', 'tipo_classificacao' => 'receita', 'campo' => 'plano_contas_codigo', 'operador' => 'igual', 'valor' => '3.01.01.01', 'prioridade' => 10, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Receita PF - Código 3.01.01.03', 'tipo_classificacao' => 'receita', 'campo' => 'plano_contas_codigo', 'operador' => 'igual', 'valor' => '3.01.01.03', 'prioridade' => 10, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Receita PF - Código 3.01.01.06', 'tipo_classificacao' => 'receita', 'campo' => 'plano_contas_codigo', 'operador' => 'igual', 'valor' => '3.01.01.06', 'prioridade' => 10, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Receita PF - Contrato PF', 'tipo_classificacao' => 'receita', 'campo' => 'descricao', 'operador' => 'contem', 'valor' => 'Contrato PF', 'prioridade' => 20, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Receita PJ - Código 3.01.01.02', 'tipo_classificacao' => 'receita', 'campo' => 'plano_contas_codigo', 'operador' => 'igual', 'valor' => '3.01.01.02', 'prioridade' => 10, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Receita PJ - Código 3.01.01.05', 'tipo_classificacao' => 'receita', 'campo' => 'plano_contas_codigo', 'operador' => 'igual', 'valor' => '3.01.01.05', 'prioridade' => 10, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Receita PJ - Código 3.01.01.07', 'tipo_classificacao' => 'receita', 'campo' => 'plano_contas_codigo', 'operador' => 'igual', 'valor' => '3.01.01.07', 'prioridade' => 10, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Receita PJ - Contrato PJ', 'tipo_classificacao' => 'receita', 'campo' => 'descricao', 'operador' => 'contem', 'valor' => 'Contrato PJ', 'prioridade' => 20, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nome' => 'Despesa - Códigos 4.x', 'tipo_classificacao' => 'despesa', 'campo' => 'plano_contas_codigo', 'operador' => 'comeca_com', 'valor' => '4.', 'prioridade' => 50, 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_finance_rules');
    }
};
