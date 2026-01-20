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
            $table->string('titulo');
            $table->longText('descricao');
            $table->foreignId('categoria_id')->nullable()->constrained('categorias_avisos')->nullOnDelete();

            $table->enum('prioridade', ['baixa', 'media', 'alta', 'critica'])->default('media');
            $table->enum('status', ['ativo', 'inativo', 'agendado'])->default('ativo');

            $table->dateTime('data_inicio')->nullable();
            $table->dateTime('data_fim')->nullable();

            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'data_inicio', 'data_fim']);
            $table->index(['prioridade']);
            $table->index(['categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos');
    }
};
