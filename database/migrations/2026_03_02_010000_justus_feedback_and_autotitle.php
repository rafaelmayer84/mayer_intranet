<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('justus_messages', function (Blueprint $table) {
            $table->enum('feedback', ['positive', 'negative'])->nullable()->after('metadata');
            $table->text('feedback_note')->nullable()->after('feedback');
            $table->timestamp('feedback_at')->nullable()->after('feedback_note');
        });
    }

    public function down(): void
    {
        Schema::table('justus_messages', function (Blueprint $table) {
            $table->dropColumn(['feedback', 'feedback_note', 'feedback_at']);
        });
    }
};
