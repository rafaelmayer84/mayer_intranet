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
            $table->string('codigo_plano', 50)->unique()->comment('Código do plano de contas (ex: 1.1.001)');
            $table->string('nome_plano')->nullable()->comment('Nome descritivo do plano');
            $table->enum('classificacao', [
                'RECEITA_PF',
                'RECEITA_PJ',
                'DESPESA',
                'PENDENTE_CLASSIFICACAO'
            ])->default('PENDENTE_CLASSIFICACAO')->comment('Classificação para dashboards');
            $table->enum('origem', ['API_DATAJURI', 'MANUAL', 'IMPORTACAO'])->default('MANUAL')->comment('Origem da regra');
            $table->boolean('ativo')->default(true)->comment('Se a regra está ativa');
            $table->timestamps();
            
            $table->index('classificacao');
            $table->index('ativo');
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
