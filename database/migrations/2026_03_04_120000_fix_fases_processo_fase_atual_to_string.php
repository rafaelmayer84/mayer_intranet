<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Converter dados existentes (int → string) antes de alterar tipo
        DB::table('fases_processo')->where('fase_atual', 1)->update(['fase_atual' => 'TMP_SIM']);
        DB::table('fases_processo')->where('fase_atual', 0)->update(['fase_atual' => 'TMP_NAO']);

        // 2. Alterar coluna de integer para varchar
        Schema::table('fases_processo', function (Blueprint $table) {
            $table->string('fase_atual', 10)->nullable()->change();
        });

        // 3. Normalizar os valores temporários
        DB::table('fases_processo')->where('fase_atual', 'TMP_SIM')->update(['fase_atual' => 'Sim']);
        DB::table('fases_processo')->where('fase_atual', 'TMP_NAO')->update(['fase_atual' => 'Não']);
    }

    public function down(): void
    {
        // Converter de volta para integer
        DB::table('fases_processo')->where('fase_atual', 'Sim')->update(['fase_atual' => 1]);
        DB::table('fases_processo')->where('fase_atual', 'Não')->update(['fase_atual' => 0]);

        Schema::table('fases_processo', function (Blueprint $table) {
            $table->integer('fase_atual')->nullable()->change();
        });
    }
};
