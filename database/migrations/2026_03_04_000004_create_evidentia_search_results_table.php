<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidentia_search_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('search_id');
            $table->unsignedBigInteger('jurisprudence_id');
            $table->string('tribunal', 20);
            $table->string('source_db', 50);
            $table->double('score_text')->default(0);
            $table->double('score_semantic')->default(0);
            $table->double('score_rerank')->default(0);
            $table->double('final_score')->default(0);
            $table->json('highlights_json')->nullable();
            $table->text('rerank_justification')->nullable();
            $table->unsignedSmallInteger('final_rank')->default(0);
            $table->timestamps();

            $table->foreign('search_id')
                  ->references('id')
                  ->on('evidentia_searches')
                  ->cascadeOnDelete();

            $table->index(['search_id', 'final_rank'], 'idx_sr_search_rank');
            $table->index(['jurisprudence_id', 'tribunal'], 'idx_sr_juris_trib');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidentia_search_results');
    }
};
