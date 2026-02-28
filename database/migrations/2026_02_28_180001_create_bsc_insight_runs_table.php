<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bsc_insight_runs', function (Blueprint $table) {
            $table->id();
            $table->string('snapshot_hash', 64)->index();
            $table->longText('snapshot_json');
            $table->date('periodo_inicio');
            $table->date('periodo_fim');
            $table->string('status', 30)->default('queued')->index();
            $table->string('model_used', 60)->nullable();
            $table->string('prompt_version', 20)->default('2.0');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd_estimated', 8, 5)->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('validator_issues_json')->nullable();
            $table->text('normalizer_log_json')->nullable();
            $table->longText('derived_metrics_json')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('cache_hit')->default(false);
            $table->boolean('force_requested')->default(false);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['snapshot_hash', 'status']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bsc_insight_runs');
    }
};
