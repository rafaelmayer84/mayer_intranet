<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('espocrm_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('espocrm_id')->unique();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('account_id')->nullable();
            $table->text('description')->nullable();
            $table->json('raw_data')->nullable();
            $table->string('origem')->default('espocrm');
            $table->string('hash')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            $table->index('espocrm_id');
        });

        Schema::create('espocrm_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('espocrm_id')->unique();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->string('industry')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->json('raw_data')->nullable();
            $table->string('origem')->default('espocrm');
            $table->string('hash')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            $table->index('espocrm_id');
        });

        Schema::create('espocrm_leads', function (Blueprint $table) {
            $table->id();
            $table->string('espocrm_id')->unique();
            $table->string('name')->nullable();
            $table->string('status')->nullable();
            $table->string('source')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('description')->nullable();
            $table->json('raw_data')->nullable();
            $table->string('origem')->default('espocrm');
            $table->string('hash')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            $table->index('espocrm_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('espocrm_leads');
        Schema::dropIfExists('espocrm_accounts');
        Schema::dropIfExists('espocrm_contacts');
    }
};
