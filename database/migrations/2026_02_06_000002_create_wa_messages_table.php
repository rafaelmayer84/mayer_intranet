<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('provider_message_id')->nullable()->index()->comment('ID da msg no SendPulse/WhatsApp');
            $table->tinyInteger('direction')->comment('1=incoming, 2=outgoing');
            $table->boolean('is_human')->default(false)->comment('true se enviada por advogado humano');
            $table->string('message_type', 30)->default('text')->comment('text, image, document, etc.');
            $table->longText('body')->nullable()->comment('Texto da mensagem');
            $table->json('raw_payload')->nullable()->comment('Payload original para auditoria');
            $table->dateTime('sent_at')->index()->comment('Quando a msg foi enviada/recebida');
            $table->timestamps();

            $table->foreign('conversation_id')
                  ->references('id')->on('wa_conversations')
                  ->cascadeOnDelete();

            $table->index(['conversation_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_messages');
    }
};
