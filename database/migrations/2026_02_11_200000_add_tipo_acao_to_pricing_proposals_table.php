<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_proposals', function (Blueprint $table) {
            $table->string('tipo_acao', 200)->nullable()->after('area_direito')->comment('Tipo de ação conforme tabela OAB/SC');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_proposals', function (Blueprint $table) {
            $table->dropColumn('tipo_acao');
        });
    }
};
