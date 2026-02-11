<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_proposals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('Advogado que gerou');
            $table->unsignedBigInteger('lead_id')->nullable()->comment('Lead vinculado');
            $table->unsignedBigInteger('cliente_id')->nullable()->comment('Cliente vinculado');
            $table->unsignedBigInteger('oportunidade_id')->nullable()->comment('Oportunidade vinculada');

            // Dados coletados para a IA
            $table->string('nome_proponente', 255)->nullable();
            $table->string('documento_proponente', 20)->nullable();
            $table->string('tipo_pessoa', 5)->nullable()->comment('PF ou PJ');
            $table->string('area_direito', 100)->nullable();
            $table->text('descricao_demanda')->nullable();
            $table->decimal('valor_causa', 15, 2)->nullable();
            $table->decimal('valor_economico', 15, 2)->nullable();
            $table->text('contexto_adicional')->nullable()->comment('Input livre do advogado');

            // Dados do SIRIC consumidos
            $table->string('siric_score', 10)->nullable();
            $table->string('siric_rating', 5)->nullable();
            $table->decimal('siric_limite', 15, 2)->nullable();
            $table->text('siric_recomendacao')->nullable();

            // Snapshot dos parâmetros de calibração no momento da geração
            $table->json('calibracao_snapshot')->nullable();

            // Dados históricos agregados usados
            $table->json('historico_agregado')->nullable()->comment('Métricas de casos similares');

            // Resultado da IA
            $table->json('proposta_rapida')->nullable()->comment('Proposta fechamento rápido');
            $table->json('proposta_equilibrada')->nullable()->comment('Proposta equilibrada');
            $table->json('proposta_premium')->nullable()->comment('Proposta premium');
            $table->string('recomendacao_ia', 20)->nullable()->comment('rapida|equilibrada|premium');
            $table->text('justificativa_ia')->nullable()->comment('Raciocínio da IA');

            // Decisão do advogado
            $table->string('proposta_escolhida', 20)->nullable()->comment('rapida|equilibrada|premium|nenhuma');
            $table->decimal('valor_final', 15, 2)->nullable()->comment('Valor efetivamente proposto');
            $table->string('status', 30)->default('gerada')->comment('gerada|enviada|aceita|recusada|expirada');
            $table->text('observacao_advogado')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
            $table->foreign('cliente_id')->references('id')->on('clientes')->nullOnDelete();
            $table->index(['status', 'created_at']);
            $table->index('area_direito');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_proposals');
    }
};
