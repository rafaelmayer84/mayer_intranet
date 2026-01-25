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
        // Adicionar coluna sync_id com valor padrão se não existir
        if (Schema::hasTable('integration_logs')) {
            Schema::table('integration_logs', function (Blueprint $table) {
                // Tornar sync_id nullable com valor padrão
                if (!Schema::hasColumn('integration_logs', 'sync_id')) {
                    $table->string('sync_id')->nullable()->default(null)->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
