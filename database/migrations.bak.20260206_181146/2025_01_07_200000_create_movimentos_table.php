<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remover tabela contas_receber se existir
        Schema::dropIfExists('contas_receber');
        
        // Criar tabela movimentos
        Schema::dropIfExists('movimentos');
        Schema::create('movimentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('datajuri_id')->unique();
            $table->date('data');
            $table->integer('mes'); // 1-12
            $table->integer('ano'); // 2025, 2026, etc
            $table->decimal('valor', 15, 2);
            $table->string('plano_contas', 500)->nullable();
            $table->string('codigo_plano', 50)->nullable(); // Ex: 3.01.01.01
            $table->enum('classificacao', [
                'RECEITA_PF',
                'RECEITA_PJ', 
                'RECEITA_FINANCEIRA',
                'PENDENTE_CLASSIFICACAO',
                'DESPESA'
            ])->default('PENDENTE_CLASSIFICACAO');
            $table->boolean('classificacao_manual')->default(false); // Se foi classificado manualmente
            $table->string('pessoa', 255)->nullable();
            $table->string('descricao', 500)->nullable();
            $table->string('observacao', 1000)->nullable();
            $table->string('conta', 100)->nullable();
            $table->boolean('conciliado')->default(false);
            $table->timestamps();
            
            // Índices para consultas rápidas
            $table->index(['ano', 'mes']);
            $table->index('classificacao');
            $table->index(['ano', 'mes', 'classificacao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentos');
    }
};
