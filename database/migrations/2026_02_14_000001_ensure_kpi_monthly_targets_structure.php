<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Garante que kpi_monthly_targets suporte metas mensais por KPI.
     * Se a tabela já existir, adiciona colunas faltantes.
     * Se não existir, cria do zero.
     */
    public function up(): void
    {
        if (!Schema::hasTable('kpi_monthly_targets')) {
            Schema::create('kpi_monthly_targets', function (Blueprint $table) {
                $table->id();
                $table->string('modulo', 30)->index();         // financeiro, clientes_mercado, processos_internos
                $table->string('kpi_key', 50)->index();        // receita_total, sla_percentual, etc.
                $table->string('descricao', 100)->nullable();  // Nome legível
                $table->unsignedSmallInteger('ano');
                $table->unsignedTinyInteger('mes');             // 1-12
                $table->decimal('meta_valor', 15, 2)->nullable();
                $table->string('unidade', 10)->default('BRL'); // BRL, QTD, PCT
                $table->string('tipo_meta', 5)->default('min');// min (piso) ou max (teto)
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['modulo', 'kpi_key', 'ano', 'mes'], 'uk_kpi_target');
            });
        } else {
            Schema::table('kpi_monthly_targets', function (Blueprint $table) {
                if (!Schema::hasColumn('kpi_monthly_targets', 'modulo')) {
                    $table->string('modulo', 30)->index()->after('id');
                }
                if (!Schema::hasColumn('kpi_monthly_targets', 'kpi_key')) {
                    $table->string('kpi_key', 50)->index()->after('modulo');
                }
                if (!Schema::hasColumn('kpi_monthly_targets', 'descricao')) {
                    $table->string('descricao', 100)->nullable()->after('kpi_key');
                }
                if (!Schema::hasColumn('kpi_monthly_targets', 'ano')) {
                    $table->unsignedSmallInteger('ano')->after('descricao');
                }
                if (!Schema::hasColumn('kpi_monthly_targets', 'mes')) {
                    $table->unsignedTinyInteger('mes')->after('ano');
                }
                if (!Schema::hasColumn('kpi_monthly_targets', 'meta_valor')) {
                    $table->decimal('meta_valor', 15, 2)->nullable()->after('mes');
                }
                if (!Schema::hasColumn('kpi_monthly_targets', 'unidade')) {
                    $table->string('unidade', 10)->default('BRL')->after('meta_valor');
                }
                if (!Schema::hasColumn('kpi_monthly_targets', 'tipo_meta')) {
                    $table->string('tipo_meta', 5)->default('min')->after('unidade');
                }
                if (!Schema::hasColumn('kpi_monthly_targets', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('tipo_meta');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_monthly_targets');
    }
};
