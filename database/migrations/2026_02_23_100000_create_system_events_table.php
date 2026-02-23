<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_events', function (Blueprint $table) {
            $table->id();
            $table->enum('category', ['gdp', 'financeiro', 'crm', 'sistema'])->index();
            $table->enum('severity', ['info', 'warning', 'error', 'critical'])->index();
            $table->string('event_type', 100)->index();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->string('related_model', 100)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['category', 'created_at']);
            $table->index(['severity', 'created_at']);
            $table->index(['related_model', 'related_id']);
            $table->index('created_at');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_events');
    }
};
