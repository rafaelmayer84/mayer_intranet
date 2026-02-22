<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexo_qa_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->enum('status', ['DRAFT', 'ACTIVE', 'PAUSED', 'ARCHIVED'])->default('DRAFT');
            $table->unsignedSmallInteger('sample_size')->default(10);
            $table->unsignedSmallInteger('lookback_days')->default(21);
            $table->unsignedSmallInteger('cooldown_days')->default(60);
            $table->json('channels')->nullable()->comment('ex: ["sendpulse"]');
            $table->json('survey_questions')->nullable()->comment('Perguntas da pesquisa em JSON');
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexo_qa_campaigns');
    }
};
