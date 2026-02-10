<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fases_processo')) {
            Schema::create('fases_processo', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('datajuri_id')->unique();
                $table->string('processo_pasta', 50)->nullable()->index();
                $table->unsignedBigInteger('processo_id_datajuri')->nullable()->index();
                $table->string('tipo_fase', 50)->nullable()->index();
                $table->string('localidade', 100)->nullable();
                $table->string('instancia', 50)->nullable()->index();
                $table->date('data')->nullable();
                $table->boolean('fase_atual')->default(false)->index();
                $table->integer('dias_fase_ativa')->default(0);
                $table->date('data_ultimo_andamento')->nullable();
                $table->string('proprietario_nome', 150)->nullable();
                $table->unsignedBigInteger('proprietario_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fases_processo');
    }
};
