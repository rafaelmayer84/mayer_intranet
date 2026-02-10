<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Campo category na wa_conversations
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->string('category', 20)->nullable()->after('priority');
        });

        // 2. Tabela wa_tags (espelho do SendPulse)
        Schema::create('wa_tags', function (Blueprint $table) {
            $table->id();
            $table->string('provider_id', 50)->unique();
            $table->string('name', 100);
            $table->string('color', 20)->default('#6b7280');
            $table->integer('contact_count')->default(0);
            $table->timestamps();
        });

        // 3. Pivot wa_conversation_tag
        Schema::create('wa_conversation_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('wa_conversations')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('wa_tags')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['conversation_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_conversation_tag');
        Schema::dropIfExists('wa_tags');
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
