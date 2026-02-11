<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siric_evidencias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consulta_id');
            $table->string('fonte', 50);         // interno, asaas_serasa, web_intel
            $table->string('tipo', 50)->nullable(); // contas_receber, movimentos, processos, leads, serasa_report, web_mencao
            $table->json('payload')->nullable();
            $table->string('impacto', 20)->nullable(); // positivo, neutro, negativo, risco
            $table->text('resumo')->nullable();
            $table->timestamps();

            $table->foreign('consulta_id')
                  ->references('id')->on('siric_consultas')
                  ->onDelete('cascade');

            $table->index('consulta_id');
            $table->index('fonte');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siric_evidencias');
    }
};
