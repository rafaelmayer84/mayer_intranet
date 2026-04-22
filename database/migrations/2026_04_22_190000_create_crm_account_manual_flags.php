<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_account_manual_flags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('codigo', 40);
            $table->text('nota')->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamp('removed_at')->nullable();
            $table->unsignedBigInteger('removed_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'removed_at']);
            $table->foreign('account_id')->references('id')->on('crm_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_account_manual_flags');
    }
};
