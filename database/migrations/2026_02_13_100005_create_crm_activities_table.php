<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opportunity_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->enum('type', ['task', 'call', 'meeting', 'whatsapp', 'note']);
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('done_at')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('opportunity_id')->references('id')->on('crm_opportunities')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('crm_accounts')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('due_at');
            $table->index(['opportunity_id', 'done_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
