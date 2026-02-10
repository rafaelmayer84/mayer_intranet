<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('movimentos')) {
            Schema::table('movimentos', function (Blueprint $table) {
                if (!Schema::hasColumn('movimentos', 'responsavel_id')) {
                    $table->unsignedBigInteger('responsavel_id')->nullable()->after('pessoa');
                    $table->index('responsavel_id');
                }
            });
        }

        if (Schema::hasTable('classificacao_regras')) {
            Schema::table('classificacao_regras', function (Blueprint $table) {
                if (!Schema::hasColumn('classificacao_regras', 'grupo_dre')) {
                    $table->string('grupo_dre', 20)->nullable()->after('classificacao');
                }
            });
        } else {
            Schema::create('classificacao_regras', function (Blueprint $table) {
                $table->id();
                $table->string('codigo_plano', 50)->unique();
                $table->string('nome_plano')->nullable();
                $table->string('classificacao', 30)->default('PENDENTE_CLASSIFICACAO');
                $table->string('grupo_dre', 20)->nullable();
                $table->enum('origem', ['API_DATAJURI', 'MANUAL', 'IMPORTACAO'])->default('MANUAL');
                $table->boolean('ativo')->default(true);
                $table->timestamps();
                $table->index('classificacao');
                $table->index('ativo');
                $table->index('grupo_dre');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('movimentos') && Schema::hasColumn('movimentos', 'responsavel_id')) {
            Schema::table('movimentos', function (Blueprint $table) {
                $table->dropIndex(['responsavel_id']);
                $table->dropColumn('responsavel_id');
            });
        }
        if (Schema::hasTable('classificacao_regras') && Schema::hasColumn('classificacao_regras', 'grupo_dre')) {
            Schema::table('classificacao_regras', function (Blueprint $table) {
                $table->dropColumn('grupo_dre');
            });
        }
    }
};
