<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexo_qa_responses_content', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->unique()->constrained('nexo_qa_sampled_targets')->cascadeOnDelete();
            $table->unsignedTinyInteger('score_1_5')->nullable()->comment('Nota 1-5');
            $table->unsignedTinyInteger('nps')->nullable()->comment('NPS 0-10');
            $table->json('tags')->nullable();
            $table->text('free_text')->nullable()->comment('Criptografado via Crypt::encryptString');
            $table->json('raw_payload')->nullable()->comment('Payload bruto do webhook');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexo_qa_responses_content');
    }
};
