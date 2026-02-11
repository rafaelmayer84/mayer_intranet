<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siric_consultas', function (Blueprint $table) {
            $table->id();

            // Dados de entrada (formulário)
            $table->string('cpf_cnpj', 20);
            $table->string('nome', 255);
            $table->string('telefone', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->decimal('valor_total', 15, 2);
            $table->integer('parcelas_desejadas');
            $table->decimal('renda_declarada', 15, 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('autorizou_consultas_externas')->default(false);

            // Snapshot interno (dados coletados do BD)
            $table->json('snapshot_interno')->nullable();

            // Resultado IA (Fase 2)
            $table->json('actions_ia')->nullable();
            $table->string('rating', 5)->nullable();            // A, B, C, D, E
            $table->integer('score')->nullable();                // 0-100
            $table->decimal('comprometimento_max', 15, 2)->nullable();
            $table->integer('parcelas_max_sugeridas')->nullable();
            $table->string('recomendacao', 30)->nullable();      // aprova, condiciona, nega
            $table->json('motivos_ia')->nullable();
            $table->json('dados_faltantes_ia')->nullable();

            // Decisão humana
            $table->string('decisao_humana', 30)->nullable();    // aprovado, negado, condicionado
            $table->text('nota_decisao')->nullable();
            $table->unsignedBigInteger('decisao_user_id')->nullable();

            // Status do fluxo
            $table->string('status', 30)->default('rascunho');   // rascunho, coletado, analisado, decidido

            // Vínculo com cliente existente (se encontrado)
            $table->unsignedBigInteger('cliente_id')->nullable();

            // Auditoria
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            // Índices
            $table->index('cpf_cnpj');
            $table->index('status');
            $table->index('rating');
            $table->index('user_id');
            $table->index('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siric_consultas');
    }
};
