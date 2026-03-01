<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justus_process_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->unique();
            $table->string('numero_cnj', 30)->nullable();
            $table->string('classe', 255)->nullable();
            $table->string('orgao', 255)->nullable();
            $table->string('fase_atual', 255)->nullable();
            $table->string('relator_vara', 255)->nullable();
            $table->text('autor')->nullable();
            $table->text('reu')->nullable();
            $table->string('objetivo_analise', 500)->nullable();
            $table->text('tese_principal')->nullable();
            $table->text('limites_restricoes')->nullable();
            $table->date('data_intimacao')->nullable();
            $table->string('prazo_medio', 100)->nullable();
            $table->json('partes_extras')->nullable();
            $table->json('datas_relevantes')->nullable();
            $table->boolean('manual_estilo_aceito')->default(false);
            $table->timestamps();

            $table->index('numero_cnj');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justus_process_profiles');
    }
};
