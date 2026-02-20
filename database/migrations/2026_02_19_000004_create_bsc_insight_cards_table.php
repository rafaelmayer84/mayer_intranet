<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bsc_insight_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id');
            $table->unsignedBigInteger('snapshot_id');
            $table->string('universo', 30);
            $table->string('severidade', 10);
            $table->unsignedTinyInteger('confidence')->default(50);
            $table->string('title', 100);
            $table->string('what_changed', 300);
            $table->string('why_it_matters', 300);
            $table->json('evidences_json')->nullable();
            $table->string('recommendation', 280);
            $table->string('next_step', 280);
            $table->json('questions_json')->nullable();
            $table->json('dependencies_json')->nullable();
            $table->json('evidence_keys_json')->nullable();
            $table->decimal('impact_score', 4, 2)->default(0);
            $table->timestamps();

            $table->foreign('run_id')
                  ->references('id')->on('ai_runs')
                  ->cascadeOnDelete();

            $table->foreign('snapshot_id')
                  ->references('id')->on('bsc_insight_snapshots')
                  ->cascadeOnDelete();

            $table->index(['run_id', 'universo', 'impact_score']);
            $table->index('universo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bsc_insight_cards');
    }
};
