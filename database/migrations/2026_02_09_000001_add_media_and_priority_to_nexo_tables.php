<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->string('media_url', 1024)->nullable()->after('body');
            $table->string('media_mime_type', 100)->nullable()->after('media_url');
            $table->string('media_filename', 255)->nullable()->after('media_mime_type');
            $table->text('media_caption')->nullable()->after('media_filename');
        });

        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->enum('priority', ['normal', 'alta', 'urgente', 'critica'])
                  ->default('normal')
                  ->after('unread_count');
            $table->index('priority', 'idx_wa_conversations_priority');
        });

        Schema::create('wa_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->text('content');
            $table->timestamps();

            $table->foreign('conversation_id')
                  ->references('id')->on('wa_conversations')
                  ->onDelete('cascade');
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_notes');

        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->dropIndex('idx_wa_conversations_priority');
            $table->dropColumn('priority');
        });

        Schema::table('wa_messages', function (Blueprint $table) {
            $table->dropColumn(['media_url', 'media_mime_type', 'media_filename', 'media_caption']);
        });
    }
};
