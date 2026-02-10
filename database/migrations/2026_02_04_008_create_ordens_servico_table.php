<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ordens_servico')) {
            Schema::create('ordens_servico', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('datajuri_id')->unique();
                $table->string('numero', 20)->nullable()->index();
                $table->string('situacao', 50)->nullable()->index();
                $table->date('data_conclusao')->nullable()->index();
                $table->date('data_ultimo_andamento')->nullable();
                $table->string('advogado_nome', 150)->nullable();
                $table->unsignedBigInteger('advogado_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ordens_servico');
    }
};
