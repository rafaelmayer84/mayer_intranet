<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('opportunity_id')->nullable();
            $table->string('type', 80)->comment('lead_qualified, stage_changed, won, lost, nexo_opened_chat, etc.');
            $table->json('payload')->nullable();
            $table->dateTime('happened_at');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('crm_accounts')->cascadeOnDelete();
            $table->foreign('opportunity_id')->references('id')->on('crm_opportunities')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('happened_at');
            $table->index(['account_id', 'happened_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_events');
    }
};
