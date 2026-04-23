<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Machine C: classificação AI de andamentos
        Schema::table('andamentos_fase', function (Blueprint $table) {
            if (!Schema::hasColumn('andamentos_fase', 'tipo_ai')) {
                $table->string('tipo_ai', 30)->nullable()->after('tipo')->index();
            }
            if (!Schema::hasColumn('andamentos_fase', 'ai_analised_at')) {
                $table->timestamp('ai_analised_at')->nullable()->after('tipo_ai');
            }
        });

        // Machine B: verdict AI em cruzamentos
        Schema::table('vigilia_cruzamentos', function (Blueprint $table) {
            if (!Schema::hasColumn('vigilia_cruzamentos', 'ai_verdict')) {
                $table->string('ai_verdict', 20)->nullable()->after('observacao');
                // VERIFICADO, SUSPEITO, INCONCLUSIVO
            }
            if (!Schema::hasColumn('vigilia_cruzamentos', 'ai_justificativa')) {
                $table->text('ai_justificativa')->nullable()->after('ai_verdict');
            }
            if (!Schema::hasColumn('vigilia_cruzamentos', 'ai_auditado_at')) {
                $table->timestamp('ai_auditado_at')->nullable()->after('ai_justificativa');
            }
        });

        // Tabela de obrigações geradas por eventos significativos
        if (!Schema::hasTable('vigilia_obrigacoes')) {
            Schema::create('vigilia_obrigacoes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('andamento_fase_id')->index();
                $table->string('processo_pasta', 50)->index();
                $table->string('tipo_evento', 30);
                // SENTENÇA, ACÓRDÃO, DECISÃO_SIGNIFICATIVA
                $table->text('descricao_evento');
                $table->date('data_evento');
                $table->unsignedBigInteger('advogado_user_id')->nullable()->index();
                $table->string('status', 20)->default('pendente')->index();
                // pendente, cumprida, justificada, cancelada
                $table->timestamp('data_limite')->nullable();
                $table->timestamp('data_cumprimento')->nullable();
                $table->text('parecer')->nullable();
                $table->timestamps();

                $table->unique(['andamento_fase_id', 'tipo_evento'], 'vig_obrig_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('andamentos_fase', function (Blueprint $table) {
            $table->dropColumn(['tipo_ai', 'ai_analised_at']);
        });
        Schema::table('vigilia_cruzamentos', function (Blueprint $table) {
            $table->dropColumn(['ai_verdict', 'ai_justificativa', 'ai_auditado_at']);
        });
        Schema::dropIfExists('vigilia_obrigacoes');
    }
};
