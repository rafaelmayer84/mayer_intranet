<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexo_qa_responses_identity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained('nexo_qa_sampled_targets')->cascadeOnDelete();
            $table->string('phone_hash', 64);
            $table->dateTime('answered_at');
            $table->boolean('opted_out')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index('phone_hash');
            $table->index('target_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexo_qa_responses_identity');
    }
};
