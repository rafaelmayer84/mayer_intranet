<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gdp_snapshots', function (Blueprint $table) {
            $table->decimal('qa_avg_score', 4, 2)->nullable()->after('score_atendimento')->comment('QA: média nota 1-5');
            $table->decimal('qa_nps', 6, 2)->nullable()->after('qa_avg_score')->comment('QA: NPS score');
            $table->decimal('qa_response_rate', 5, 2)->nullable()->after('qa_nps')->comment('QA: taxa de resposta %');
            $table->unsignedInteger('qa_responses_count')->nullable()->after('qa_response_rate')->comment('QA: total respostas no período');
        });
    }

    public function down(): void
    {
        Schema::table('gdp_snapshots', function (Blueprint $table) {
            $table->dropColumn(['qa_avg_score', 'qa_nps', 'qa_response_rate', 'qa_responses_count']);
        });
    }
};
