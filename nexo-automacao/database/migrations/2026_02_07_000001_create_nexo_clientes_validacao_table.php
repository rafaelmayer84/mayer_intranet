<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexo_clientes_validacao', function (Blueprint $table) {
            $table->id();
            $table->string('telefone', 20)->unique()->index();
            $table->string('cpf_cnpj', 20)->nullable();
            $table->string('numero_processo', 50)->nullable();
            $table->string('nome_mae', 100)->nullable();
            $table->string('cidade_nascimento', 100)->nullable();
            $table->string('cidade_primeiro_processo', 100)->nullable();
            $table->integer('ano_inicio_processo')->nullable();
            $table->decimal('valor_causa', 15, 2)->nullable();
            $table->string('tipo_acao', 50)->nullable();
            $table->integer('tentativas_falhas')->default(0);
            $table->timestamp('bloqueado_ate')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexo_clientes_validacao');
    }
};
