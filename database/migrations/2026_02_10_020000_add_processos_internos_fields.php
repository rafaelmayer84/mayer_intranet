<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona campos extras necessários para o dashboard Processos Internos:
 *
 * atividades_datajuri:
 *   - tipo_atividade: Tipo textual da atividade (audiência, petição, diligência etc.)
 *   - data_vencimento: Alias para data_prazo_fatal, útil para SLA
 *   - responsavel_nome: Nome legível do proprietário
 *
 * processos:
 *   - area_atuacao: Área (trabalhista, cível, família, etc.) para filtro
 *   - grupo_responsavel: Time/grupo do advogado responsável
 *
 * fases_processo:
 *   - descricao_fase: Descrição textual da fase
 */
return new class extends Migration
{
    public function up(): void
    {
        // atividades_datajuri - campos extras
        Schema::table('atividades_datajuri', function (Blueprint $table) {
            if (!Schema::hasColumn('atividades_datajuri', 'tipo_atividade')) {
                $table->string('tipo_atividade', 100)->nullable()->after('status');
            }
            if (!Schema::hasColumn('atividades_datajuri', 'data_vencimento')) {
                $table->dateTime('data_vencimento')->nullable()->after('data_conclusao');
            }
            if (!Schema::hasColumn('atividades_datajuri', 'responsavel_nome')) {
                $table->string('responsavel_nome', 255)->nullable()->after('proprietario_id');
            }
        });

        // processos - campos extras
        Schema::table('processos', function (Blueprint $table) {
            if (!Schema::hasColumn('processos', 'area_atuacao')) {
                $table->string('area_atuacao', 100)->nullable()->after('natureza');
            }
            if (!Schema::hasColumn('processos', 'grupo_responsavel')) {
                $table->string('grupo_responsavel', 100)->nullable()->after('proprietario_nome');
            }
        });

        // fases_processo - campo extra
        Schema::table('fases_processo', function (Blueprint $table) {
            if (!Schema::hasColumn('fases_processo', 'descricao_fase')) {
                $table->string('descricao_fase', 255)->nullable()->after('tipo_fase');
            }
        });
    }

    public function down(): void
    {
        Schema::table('atividades_datajuri', function (Blueprint $table) {
            $table->dropColumn(['tipo_atividade', 'data_vencimento', 'responsavel_nome']);
        });
        Schema::table('processos', function (Blueprint $table) {
            $table->dropColumn(['area_atuacao', 'grupo_responsavel']);
        });
        Schema::table('fases_processo', function (Blueprint $table) {
            $table->dropColumn(['descricao_fase']);
        });
    }
};
