<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_account_data_gate_cientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gate_id');
            $table->unsignedBigInteger('user_id');
            $table->date('given_date');
            $table->timestamp('given_at');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('compromisso_texto')->nullable();
            $table->timestamps();

            $table->unique(['gate_id', 'user_id', 'given_date'], 'unq_gate_user_date');
            $table->index(['user_id', 'given_date']);
            $table->foreign('gate_id')->references('id')->on('crm_account_data_gates')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_account_data_gate_cientes');
    }
};
