<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_accounts', function (Blueprint $table) {
            $table->string('segment', 50)->nullable()->after('notes');
            $table->text('segment_summary')->nullable()->after('segment');
            $table->timestamp('segment_cached_at')->nullable()->after('segment_summary');
        });
    }

    public function down(): void
    {
        Schema::table('crm_accounts', function (Blueprint $table) {
            $table->dropColumn(['segment', 'segment_summary', 'segment_cached_at']);
        });
    }
};
