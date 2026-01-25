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
        Schema::create('oportunidades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('nome');
            $table->enum('estagio', ['prospectando', 'qualificacao', 'proposta', 'negociacao', 'ganha', 'perdida'])->default('prospectando');
            $table->decimal('valor', 15, 2)->default(0);
            $table->enum('tipo', ['PF', 'PJ', 'Misto'])->default('PF');
            $table->unsignedBigInteger('responsavel_id')->nullable();
            $table->string('espocrm_id')->unique()->nullable();
            $table->integer('datajuri_contrato_id')->nullable();
            $table->date('data_criacao')->nullable();
            $table->date('data_fechamento')->nullable();
            $table->text('observacoes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('set null');
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
            $table->foreign('responsavel_id')->references('id')->on('users')->onDelete('set null');
            
            // Índices
            $table->index(['estagio', 'tipo']);
            $table->index('estagio');
            $table->index('tipo');
            $table->index('valor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oportunidades');
    }
};
