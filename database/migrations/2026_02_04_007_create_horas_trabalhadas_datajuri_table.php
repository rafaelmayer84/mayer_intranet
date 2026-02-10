<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('horas_trabalhadas_datajuri')) {
            Schema::create('horas_trabalhadas_datajuri', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('datajuri_id')->unique();
                $table->date('data')->nullable()->index();
                $table->string('duracao_original', 10)->nullable();
                $table->string('total_hora_trabalhada', 10)->nullable();
                $table->time('hora_inicial')->nullable();
                $table->time('hora_final')->nullable();
                $table->decimal('valor_hora', 15, 2)->default(0);
                $table->decimal('valor_total_original', 15, 2)->default(0);
                $table->string('assunto', 255)->nullable();
                $table->string('tipo', 150)->nullable();
                $table->string('status', 50)->nullable()->index();
                $table->unsignedBigInteger('proprietario_id')->nullable()->index();
                $table->boolean('particular')->default(false);
                $table->date('data_faturado')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('horas_trabalhadas_datajuri');
    }
};
