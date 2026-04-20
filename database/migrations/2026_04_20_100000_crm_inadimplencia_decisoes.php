<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_inadimplencia_decisoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->enum('decisao', ['aguardar', 'renegociar', 'sinistrar']);
            $table->text('justificativa');
            $table->date('prazo_revisao')->nullable()->comment('Preenchido apenas para aguardar — 30 dias');
            $table->unsignedBigInteger('created_by_user_id');
            $table->enum('status', ['ativa', 'expirada', 'encerrada'])->default('ativa');
            $table->unsignedBigInteger('oportunidade_id')->nullable()->comment('FK crm_opportunities, preenchido ao renegociar');
            $table->text('sinistro_notas')->nullable()->comment('Detalhes do contrato sinistrado');
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('crm_accounts')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users');
            $table->foreign('oportunidade_id')->references('id')->on('crm_opportunities')->nullOnDelete();

            $table->index(['account_id', 'status']);
            $table->index('prazo_revisao');
        });

        // Tarefa de cobrança formal precisa de evidência obrigatória
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->boolean('requires_evidence')->default(false)->after('purpose');
        });

        // Evidências vinculadas à atividade de cobrança
        Schema::table('crm_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('activity_id')->nullable()->after('account_id');
            $table->foreign('activity_id')->references('id')->on('crm_activities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_documents', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
            $table->dropColumn('activity_id');
        });

        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropColumn('requires_evidence');
        });

        Schema::dropIfExists('crm_inadimplencia_decisoes');
    }
};
