<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gdp_remuneracao_faixas')) {
            return;
        }
        Schema::create('gdp_remuneracao_faixas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ciclo_id');
            $table->decimal('score_min', 5, 2)->comment('Score GDP mínimo da faixa (inclusive)');
            $table->decimal('score_max', 5, 2)->comment('Score GDP máximo da faixa (exclusive, exceto última)');
            $table->decimal('percentual_remuneracao', 5, 2)->comment('Percentual aplicado sobre captação. Ex: 0.00 a 100.00');
            $table->string('label', 50)->nullable()->comment('Ex: Insuficiente, Regular, Bom, Excelente');
            $table->timestamps();
            $table->foreign('ciclo_id')->references('id')->on('gdp_ciclos')->onDelete('cascade');
            $table->index(['ciclo_id', 'score_min'], 'gdp_rem_faixas_lookup');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('gdp_remuneracao_faixas');
    }
};
