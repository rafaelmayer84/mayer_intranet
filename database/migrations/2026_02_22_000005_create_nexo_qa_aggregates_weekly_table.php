<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexo_qa_aggregates_weekly', function (Blueprint $table) {
            $table->id();
            $table->date('week_start');
            $table->date('week_end');
            $table->foreignId('responsible_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('responses_count')->default(0);
            $table->decimal('avg_score', 4, 2)->nullable();
            $table->decimal('nps_score', 6, 2)->nullable();
            $table->unsignedInteger('detractors')->default(0);
            $table->unsignedInteger('passives')->default(0);
            $table->unsignedInteger('promoters')->default(0);
            $table->unsignedInteger('targets_sent')->default(0)->comment('Total enviados na semana');
            $table->timestamp('created_at')->nullable();

            $table->unique(['week_start', 'responsible_user_id'], 'uq_qa_agg_week_user');
            $table->index('week_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexo_qa_aggregates_weekly');
    }
};
