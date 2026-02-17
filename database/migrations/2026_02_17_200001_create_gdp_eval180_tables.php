<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Formulários de avaliação 180 ──
        Schema::create('gdp_eval180_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cycle_id');
            $table->unsignedBigInteger('user_id')->comment('Avaliado');
            $table->string('period', 10)->comment('YYYY-MM ou YYYY-Q1/Q2');
            $table->enum('status', ['draft', 'submitted', 'locked'])->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('cycle_id')->references('id')->on('gdp_ciclos')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['cycle_id', 'user_id', 'period'], 'uq_eval180_form');
            $table->index(['user_id', 'status']);
        });

        // ── 2. Respostas (autoavaliação + gestor) ──
        Schema::create('gdp_eval180_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id');
            $table->enum('rater_type', ['self', 'manager']);
            $table->unsignedBigInteger('rater_user_id');
            $table->json('answers_json')->comment('{"1.1":4,"1.2":3,...}');
            $table->json('section_scores_json')->nullable()->comment('{"1":3.4,"2":3.6,...}');
            $table->decimal('total_score', 4, 2)->nullable();
            $table->text('comment_text')->nullable();
            $table->text('evidence_text')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('form_id')->references('id')->on('gdp_eval180_forms')->onDelete('cascade');
            $table->foreign('rater_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['form_id', 'rater_type'], 'uq_eval180_response');
        });

        // ── 3. Itens de plano de ação ──
        Schema::create('gdp_eval180_action_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('owner_user_id')->comment('Avaliado');
            $table->string('title', 255);
            $table->date('due_date');
            $table->enum('status', ['open', 'done'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('form_id')->references('id')->on('gdp_eval180_forms')->onDelete('cascade');
            $table->foreign('owner_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['owner_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdp_eval180_action_items');
        Schema::dropIfExists('gdp_eval180_responses');
        Schema::dropIfExists('gdp_eval180_forms');
    }
};
