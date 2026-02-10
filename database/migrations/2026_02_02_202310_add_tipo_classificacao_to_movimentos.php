<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentos', function (Blueprint $table) {
            $table->enum('tipo_classificacao', ['receita', 'despesa'])->nullable()->after('classificacao');
            $table->index('tipo_classificacao');
        });
    }

    public function down(): void
    {
        Schema::table('movimentos', function (Blueprint $table) {
            $table->dropColumn('tipo_classificacao');
        });
    }
};
