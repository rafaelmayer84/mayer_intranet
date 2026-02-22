<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexo_qa_sampled_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('nexo_qa_campaigns')->cascadeOnDelete();
            $table->enum('source_type', ['DATAJURI', 'NEXO']);
            $table->unsignedBigInteger('source_id')->comment('ID do cliente/contato na tabela de origem');
            $table->string('phone_e164', 20)->comment('Telefone E.164: 55DDNNNNNNNNN');
            $table->string('phone_hash', 64)->comment('SHA-256 do phone_e164');
            $table->unsignedBigInteger('responsible_user_id')->nullable();
            $table->dateTime('last_interaction_at')->nullable();
            $table->dateTime('sampled_at');
            $table->enum('send_status', ['PENDING', 'SENT', 'FAILED', 'SKIPPED'])->default('PENDING');
            $table->string('skip_reason', 255)->nullable();
            $table->string('sendpulse_message_id', 100)->nullable();
            $table->uuid('token')->unique();
            $table->timestamps();

            $table->index('phone_hash');
            $table->index('send_status');
            $table->index(['campaign_id', 'send_status']);
            $table->index('responsible_user_id');

            $table->foreign('responsible_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexo_qa_sampled_targets');
    }
};
