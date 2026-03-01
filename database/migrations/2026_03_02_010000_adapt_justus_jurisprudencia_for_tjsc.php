<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('justus_jurisprudencia', function (Blueprint $table) {
            // Renomear stj_id -> external_id para suportar múltiplos tribunais
            $table->renameColumn('stj_id', 'external_id');
        });

        Schema::table('justus_jurisprudencia', function (Blueprint $table) {
            // Ampliar campos para nomes longos do TJSC
            $table->string('external_id', 60)->nullable()->change();
            $table->string('tribunal', 20)->default('STJ')->change();
            $table->string('orgao_julgador', 120)->nullable()->change();
            $table->string('descricao_classe', 150)->nullable()->change();
            $table->string('relator', 120)->nullable()->change();
            $table->string('numero_processo', 50)->nullable()->change();
        });

        // Trocar unique de stj_id para composite unique (tribunal + external_id)
        Schema::table('justus_jurisprudencia', function (Blueprint $table) {
            $table->dropUnique(['stj_id']);
        });

        Schema::table('justus_jurisprudencia', function (Blueprint $table) {
            $table->unique(['tribunal', 'external_id'], 'justus_tribunal_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('justus_jurisprudencia', function (Blueprint $table) {
            $table->dropUnique('justus_tribunal_external_unique');
        });

        Schema::table('justus_jurisprudencia', function (Blueprint $table) {
            $table->renameColumn('external_id', 'stj_id');
        });

        Schema::table('justus_jurisprudencia', function (Blueprint $table) {
            $table->string('stj_id', 20)->unique()->change();
            $table->string('tribunal', 10)->default('STJ')->change();
            $table->string('orgao_julgador', 50)->nullable()->change();
        });
    }
};
