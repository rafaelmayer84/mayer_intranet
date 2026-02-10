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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('cpf_cnpj')->unique()->nullable();
            $table->enum('tipo', ['PF', 'PJ'])->default('PF');
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();
            $table->text('endereco')->nullable();
            $table->integer('datajuri_id')->unique()->nullable();
            $table->string('espocrm_id')->unique()->nullable();
            $table->enum('status', ['ativo', 'inativo', 'prospecto'])->default('ativo');
            $table->decimal('valor_carteira', 15, 2)->default(0);
            $table->integer('total_processos')->default(0);
            $table->integer('total_contratos')->default(0);
            $table->date('data_primeiro_contato')->nullable();
            $table->date('data_ultimo_contato')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para melhor performance
            $table->index(['nome', 'status']);
            $table->index('tipo');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
