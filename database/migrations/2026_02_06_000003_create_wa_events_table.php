<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('type', 50)->index()->comment('webhook_received, sync_run, send_message, error');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('conversation_id')
                  ->references('id')->on('wa_conversations')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_events');
    }
};
