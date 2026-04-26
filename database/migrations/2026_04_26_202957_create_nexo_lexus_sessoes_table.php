<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexo_lexus_sessoes', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id', 100);
            $table->string('phone', 20);
            $table->string('contato', 150)->nullable();
            $table->enum('etapa', [
                'inicial', 'triagem', 'qualificado', 'desqualificado',
                'spam', 'ja_cliente', 'encerrado', 'erro',
            ])->default('inicial');
            $table->string('area_provavel', 50)->nullable();
            $table->enum('intencao_contratar', ['alta', 'media', 'baixa'])->nullable();
            $table->enum('urgencia', ['alta', 'media', 'baixa'])->nullable();
            $table->string('nome_cliente', 150)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->text('resumo_caso')->nullable();
            $table->text('briefing_operador')->nullable();
            $table->json('contexto_json')->nullable();
            $table->unsignedInteger('total_interacoes')->default(0);
            $table->unsignedInteger('input_tokens_total')->default(0);
            $table->unsignedInteger('output_tokens_total')->default(0);
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->timestamp('ultima_atividade')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'phone'], 'uk_conv_phone');
            $table->index('phone', 'idx_phone');
            $table->index('etapa', 'idx_etapa');
            $table->index('ultima_atividade', 'idx_ultima_atividade');
            $table->index('lead_id', 'idx_lead');
            $table->index('cliente_id', 'idx_cliente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexo_lexus_sessoes');
    }
};
