<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_ai_insights', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->nullable()->index();
            $table->enum('tipo', ['weekly_digest', 'account_action', 'alert'])->index();
            $table->string('titulo', 255);
            $table->text('insight_text');
            $table->text('action_suggested')->nullable();
            $table->enum('priority', ['alta', 'media', 'baixa'])->default('media');
            $table->enum('status', ['active', 'dismissed', 'acted'])->default('active');
            $table->unsignedBigInteger('generated_by_user_id')->nullable();
            $table->json('context_snapshot')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('crm_accounts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_ai_insights');
    }
};
