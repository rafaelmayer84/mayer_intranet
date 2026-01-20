<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horas_trabalhadas', function (Blueprint $table) {
            $table->id();
            $table->string('datajuri_id')->unique();
            $table->string('descricao')->nullable();
            $table->decimal('horas', 10, 2)->default(0);
            $table->decimal('valor_hora', 10, 2)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->string('status')->nullable();
            $table->string('tipo_atividade')->nullable();
            $table->string('responsavel_nome')->nullable();
            $table->string('responsavel_id')->nullable();
            $table->string('processo_id')->nullable();
            $table->date('data_lancamento')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horas_trabalhadas');
    }
};
