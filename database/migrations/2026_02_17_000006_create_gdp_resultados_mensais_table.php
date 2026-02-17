<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdp_resultados_mensais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciclo_id')->constrained('gdp_ciclos')->cascadeOnDelete();
            $table->foreignId('indicador_id')->constrained('gdp_indicadores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('ano');
            $table->decimal('valor_apurado', 15, 2)->nullable();
            $table->timestamp('apurado_em')->nullable();
            $table->decimal('valor_override', 15, 2)->nullable();
            $table->text('justificativa_override')->nullable();
            $table->unsignedBigInteger('override_por')->nullable();
            $table->boolean('atribuicao_aproximada')->default(false);
            $table->boolean('validado')->default(false);
            $table->decimal('percentual_atingimento', 7, 2)->nullable();
            $table->decimal('score_ponderado', 7, 4)->nullable();
            $table->timestamps();
            $table->unique(['ciclo_id', 'indicador_id', 'user_id', 'mes', 'ano'], 'uk_gdp_resultado');
            $table->foreign('override_por')->references('id')->on('users')->nullOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('gdp_resultados_mensais'); }
};
