<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bsc_insight_cards_v2', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id');
            $table->string('perspectiva', 20);
            $table->string('severidade', 10);
            $table->string('titulo', 200);
            $table->text('descricao');
            $table->text('recomendacao');
            $table->string('acao_sugerida', 200)->nullable();
            $table->text('metricas_referenciadas_json')->nullable();
            $table->text('evidencias_json')->nullable();
            $table->unsignedTinyInteger('confidence')->default(50);
            $table->decimal('impact_score', 3, 1)->default(0);
            $table->unsignedTinyInteger('ordem')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('run_id')
                  ->references('id')
                  ->on('bsc_insight_runs')
                  ->onDelete('cascade');

            $table->unique(['run_id', 'perspectiva', 'titulo'], 'uq_run_persp_titulo');
            $table->index(['run_id', 'perspectiva']);
            $table->index(['severidade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bsc_insight_cards_v2');
    }
};
