<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kpi_monthly_targets', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year')->unsigned()->index();
            $table->tinyInteger('month')->unsigned()->index();
            $table->string('kpi_key', 120)->index();
            $table->string('target_value', 80)->nullable();
            $table->timestamps();
            $table->unique(['year', 'month', 'kpi_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_monthly_targets');
    }
};
