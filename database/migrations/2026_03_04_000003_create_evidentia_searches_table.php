<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidentia_searches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('query');
            $table->json('filters_json')->nullable();
            $table->json('expanded_terms_json')->nullable();
            $table->unsignedSmallInteger('topk')->default(10);
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('status', 30)->default('pending');
            $table->text('error_message')->nullable();
            $table->boolean('degraded_mode')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidentia_searches');
    }
};
