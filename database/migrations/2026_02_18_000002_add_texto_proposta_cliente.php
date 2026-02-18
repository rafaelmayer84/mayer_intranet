<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_proposals', function (Blueprint $table) {
            $table->longText('texto_proposta_cliente')->nullable()->after('observacao_advogado');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_proposals', function (Blueprint $table) {
            $table->dropColumn('texto_proposta_cliente');
        });
    }
};
