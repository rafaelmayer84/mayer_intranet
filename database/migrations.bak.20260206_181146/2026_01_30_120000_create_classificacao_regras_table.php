<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('classificacao_regras', function (Blueprint $table) {
            $table->id();
            
            // Identificação do plano de contas
            $table->string('codigo_plano', 50)->index();
            $table->string('nome_plano', 255);
            
            // Classificação e tipo
            $table->string('classificacao', 50)->index();
            $table->string('tipo_movimento', 20);
            
            // Status e prioridade
            $table->boolean('ativo')->default(true)->index();
            $table->integer('prioridade')->default(0)->comment('Quanto maior, mais prioridade');
            
            // Metadados
            $table->string('origem', 50)->default('manual')->comment('manual, datajuri, importado');
            $table->text('observacoes')->nullable();
            
            // Auditoria
            $table->unsignedBigInteger('criado_por')->nullable();
            $table->unsignedBigInteger('modificado_por')->nullable();
            
            $table->timestamps();
            
            // Índices compostos para performance
            $table->index(['ativo', 'prioridade']);
            $table->index(['classificacao', 'ativo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classificacao_regras');
    }
};
