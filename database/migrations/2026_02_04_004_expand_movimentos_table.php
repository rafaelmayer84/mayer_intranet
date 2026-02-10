<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentos', function (Blueprint $table) {
            if (!Schema::hasColumn('movimentos', 'pessoa_id_datajuri')) {
                $table->unsignedBigInteger('pessoa_id_datajuri')->nullable()->after('pessoa');
            }
            if (!Schema::hasColumn('movimentos', 'contrato_id_datajuri')) {
                $table->unsignedBigInteger('contrato_id_datajuri')->nullable()->after('pessoa_id_datajuri');
            }
            if (!Schema::hasColumn('movimentos', 'processo_pasta')) {
                $table->string('processo_pasta', 50)->nullable()->after('contrato_id_datajuri');
            }
            if (!Schema::hasColumn('movimentos', 'proprietario_nome')) {
                $table->string('proprietario_nome', 150)->nullable()->after('responsavel_id');
            }
            if (!Schema::hasColumn('movimentos', 'plano_conta_id')) {
                $table->unsignedBigInteger('plano_conta_id')->nullable()->after('codigo_plano');
            }
        });
    }

    public function down(): void
    {
        Schema::table('movimentos', function (Blueprint $table) {
            $cols = ['pessoa_id_datajuri','contrato_id_datajuri','processo_pasta','proprietario_nome','plano_conta_id'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('movimentos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
