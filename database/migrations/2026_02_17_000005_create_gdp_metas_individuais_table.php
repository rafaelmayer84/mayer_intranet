<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdp_metas_individuais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciclo_id')->constrained('gdp_ciclos')->cascadeOnDelete();
            $table->foreignId('indicador_id')->constrained('gdp_indicadores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('ano');
            $table->decimal('valor_meta', 15, 2);
            $table->text('justificativa')->nullable();
            $table->unsignedBigInteger('definido_por')->nullable();
            $table->timestamps();
            $table->unique(['ciclo_id', 'indicador_id', 'user_id', 'mes', 'ano'], 'uk_gdp_meta_ind');
            $table->foreign('definido_por')->references('id')->on('users')->nullOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('gdp_metas_individuais'); }
};
