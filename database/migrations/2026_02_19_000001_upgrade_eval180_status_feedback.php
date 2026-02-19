<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gdp_eval180_forms', function (Blueprint $table) {
            $table->string('status', 30)->default('pending_self')->change();
        });

        Schema::table('gdp_eval180_forms', function (Blueprint $table) {
            $table->timestamp('feedback_at')->nullable()->after('status');
            $table->unsignedBigInteger('feedback_by')->nullable()->after('feedback_at');
        });

        DB::table('gdp_eval180_forms')->where('status', 'draft')->update(['status' => 'pending_self']);
        DB::table('gdp_eval180_forms')->where('status', 'submitted')->update(['status' => 'pending_feedback']);

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('gdp_eval180_forms', function (Blueprint $table) {
            $table->dropColumn(['feedback_at', 'feedback_by']);
        });
    }
};
