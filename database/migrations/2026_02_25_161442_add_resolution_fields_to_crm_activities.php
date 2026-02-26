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
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->string('resolution_status', 30)->nullable()->after('done_at');
            $table->text('resolution_notes')->nullable()->after('resolution_status');
            $table->unsignedBigInteger('completed_by_user_id')->nullable()->after('resolution_notes');
            $table->foreign('completed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropForeign(['completed_by_user_id']);
            $table->dropColumn(['resolution_status', 'resolution_notes', 'completed_by_user_id']);
        });
    }
};
