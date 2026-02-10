<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->default('sendpulse');
            $table->string('contact_id')->nullable()->index()->comment('ID do contato no SendPulse');
            $table->string('chat_id')->nullable()->unique()->comment('ID do chat no SendPulse');
            $table->string('phone', 30)->index()->comment('Telefone normalizado');
            $table->string('name')->nullable()->comment('Nome do contato');
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
            $table->dateTime('last_message_at')->nullable()->index();
            $table->dateTime('last_incoming_at')->nullable()->index();
            $table->dateTime('first_response_at')->nullable()->comment('Timestamp da 1a resposta humana');
            $table->unsignedInteger('unread_count')->default(0);
            $table->unsignedBigInteger('linked_lead_id')->nullable()->index();
            $table->unsignedBigInteger('linked_cliente_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('assigned_user_id')
                  ->references('id')->on('users')
                  ->nullOnDelete();

            $table->foreign('linked_lead_id')
                  ->references('id')->on('leads')
                  ->nullOnDelete();

            $table->foreign('linked_cliente_id')
                  ->references('id')->on('clientes')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_conversations');
    }
};
