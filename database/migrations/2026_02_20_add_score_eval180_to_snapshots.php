<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gdp_snapshots', function (Blueprint $table) {
            $table->decimal('score_eval180', 5, 2)->nullable()->after('score_atendimento');
            $table->decimal('score_total_original', 7, 2)->nullable()->after('score_total')
                  ->comment('Score antes do guardrail Eval180');
        });
    }

    public function down(): void
    {
        Schema::table('gdp_snapshots', function (Blueprint $table) {
            $table->dropColumn(['score_eval180', 'score_total_original']);
        });
    }
};
