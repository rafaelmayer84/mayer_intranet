<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_monthly_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('mes', 7)->unique();
            $table->decimal('limit_usd', 8, 2)->default(10.00);
            $table->decimal('spent_usd', 8, 5)->default(0);
            $table->unsignedInteger('total_runs')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_monthly_budgets');
    }
};
