<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justus_usage_monthly', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedSmallInteger('mes');
            $table->unsignedSmallInteger('ano');
            $table->unsignedInteger('total_input_tokens')->default(0);
            $table->unsignedInteger('total_output_tokens')->default(0);
            $table->decimal('total_cost_brl', 10, 4)->default(0);
            $table->unsignedInteger('total_requests')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'mes', 'ano']);
            $table->index(['mes', 'ano']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justus_usage_monthly');
    }
};
