<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('avisos')) {
            return;
        }

        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 255);
            $table->longText('descricao');
            $table->foreignId('categoria_id')->constrained('categorias_avisos');
            $table->enum('prioridade', ['baixa', 'media', 'alta', 'critica'])->default('media');
            $table->enum('status', ['ativo', 'inativo', 'agendado'])->default('ativo');
            $table->dateTime('data_inicio')->nullable();
            $table->dateTime('data_fim')->nullable();
            $table->foreignId('criado_por')->constrained('users');
            $table->timestamps();

            $table->index(['status', 'data_inicio', 'data_fim']);
            $table->index('prioridade');
            $table->index('categoria_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos');
    }
};
