<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_account_data_gates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('tipo', 60);
            $table->string('dj_valor_snapshot')->nullable();
            $table->json('evidencia_local')->nullable();
            $table->enum('status', [
                'aberto', 'em_revisao', 'resolvido_auto',
                'resolvido_manual', 'escalado', 'cancelado'
            ])->default('aberto');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('first_seen_by_owner_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->string('dj_valor_no_fechamento')->nullable();
            $table->boolean('penalidade_registrada')->default(false);
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['owner_user_id', 'status']);
            $table->index('tipo');
            $table->foreign('account_id')->references('id')->on('crm_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_account_data_gates');
    }
};
