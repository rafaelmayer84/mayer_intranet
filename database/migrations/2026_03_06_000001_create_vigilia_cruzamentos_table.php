<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vigilia_cruzamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('atividade_datajuri_id')->index();
            $table->unsignedBigInteger('andamento_fase_id')->nullable();
            $table->string('status_cruzamento', 30)->default('pendente');
            // verificado, suspeito, sem_acao, nao_aplicavel, pendente, futuro
            $table->integer('dias_gap')->nullable();
            $table->date('data_ultimo_andamento')->nullable();
            $table->string('observacao', 500)->nullable();
            $table->timestamps();

            $table->unique('atividade_datajuri_id', 'vig_ativ_unique');
            $table->index('status_cruzamento', 'vig_status_idx');
            $table->index('created_at', 'vig_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vigilia_cruzamentos');
    }
};
