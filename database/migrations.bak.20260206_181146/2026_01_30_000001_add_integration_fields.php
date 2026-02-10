<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Adicionar campos de integração à tabela clientes
        if (!Schema::hasColumn('clientes', 'datajuri_id')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->string('datajuri_id')->nullable()->index()->after('id');
            });
        }

        if (!Schema::hasColumn('clientes', 'espocrm_id')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->string('espocrm_id')->nullable()->index()->after('datajuri_id');
            });
        }

        // Adicionar campos de integração à tabela processos
        if (!Schema::hasColumn('processos', 'datajuri_id')) {
            Schema::table('processos', function (Blueprint $table) {
                $table->string('datajuri_id')->nullable()->index()->after('id');
            });
        }

        if (!Schema::hasColumn('processos', 'tipo_acao')) {
            Schema::table('processos', function (Blueprint $table) {
                $table->string('tipo_acao')->nullable()->after('status');
            });
        }

        if (!Schema::hasColumn('processos', 'data_abertura')) {
            Schema::table('processos', function (Blueprint $table) {
                $table->date('data_abertura')->nullable()->after('tipo_acao');
            });
        }

        // Adicionar campos de integração à tabela movimentos
        if (!Schema::hasColumn('movimentos', 'datajuri_id')) {
            Schema::table('movimentos', function (Blueprint $table) {
                $table->string('datajuri_id')->nullable()->index()->after('id');
            });
        }

        if (!Schema::hasColumn('movimentos', 'data_movimento')) {
            Schema::table('movimentos', function (Blueprint $table) {
                $table->date('data_movimento')->nullable()->after('valor');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['datajuri_id', 'espocrm_id']);
        });

        Schema::table('processos', function (Blueprint $table) {
            $table->dropColumn(['datajuri_id', 'tipo_acao', 'data_abertura']);
        });

        Schema::table('movimentos', function (Blueprint $table) {
            $table->dropColumn(['datajuri_id', 'data_movimento']);
        });
    }
};
