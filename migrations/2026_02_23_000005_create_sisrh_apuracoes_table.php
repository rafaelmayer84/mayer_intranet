<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sisrh_apuracoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->unsignedBigInteger('ciclo_id');

            // Inputs
            $table->decimal('rb_valor', 12, 2)->comment('RB vigente no mês (do nível ou override)');
            $table->decimal('captacao_valor', 12, 2)->default(0)->comment('Receita efetivamente recebida (lógica F1 GDP)');
            $table->decimal('gdp_score', 5, 2)->default(0)->comment('Score GDP do mês (score_total_original ?? score_total)');
            $table->decimal('percentual_faixa', 5, 2)->default(0)->comment('% da faixa GDP aplicada sobre captação');

            // Cálculos
            $table->decimal('rv_bruta', 12, 2)->default(0)->comment('captacao * percentual_faixa / 100');
            $table->decimal('reducao_conformidade_pct', 5, 2)->default(0)->comment('% redução por penalidades');
            $table->decimal('reducao_acompanhamento_pct', 5, 2)->default(0)->comment('% redução por falta acompanhamento bimestral');
            $table->decimal('reducao_total_pct', 5, 2)->default(0)->comment('min(conf+acomp, 40.00) cap');
            $table->decimal('rv_pos_reducoes', 12, 2)->default(0)->comment('rv_bruta * (1 - reducao_total/100)');
            $table->decimal('teto_rv_valor', 12, 2)->default(0)->comment('rb_valor * 0.50');
            $table->decimal('rv_aplicada', 12, 2)->default(0)->comment('min(rv_pos_reducoes, teto_rv_valor)');
            $table->decimal('rv_excedente_credito', 12, 2)->default(0)->comment('max(0, rv_pos_reducoes - teto_rv_valor)');
            $table->decimal('credito_utilizado', 12, 2)->default(0)->comment('Crédito do banco usado para completar até teto');

            // Status e auditoria
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->string('bloqueio_motivo', 100)->nullable()->comment('blocked_by_plan, blocked_by_score, etc');
            $table->json('snapshot_json')->nullable()->comment('Todos os inputs e IDs usados no cálculo');
            $table->string('snapshot_hash', 64)->nullable()->comment('SHA-256 do snapshot');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('ciclo_id')->references('id')->on('gdp_ciclos')->onDelete('cascade');
            $table->foreign('closed_by')->references('id')->on('users')->onDelete('set null');

            $table->unique(['user_id', 'ano', 'mes'], 'sisrh_apuracao_unique');
            $table->index(['ano', 'mes', 'status'], 'sisrh_apuracao_periodo_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sisrh_apuracoes');
    }
};
