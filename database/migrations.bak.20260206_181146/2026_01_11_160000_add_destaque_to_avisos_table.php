<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('avisos')) {
            return;
        }

        if (!Schema::hasColumn('avisos', 'destaque')) {
            Schema::table('avisos', function (Blueprint $table) {
                $table->boolean('destaque')->default(false)->after('data_fim');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('avisos') && Schema::hasColumn('avisos', 'destaque')) {
            Schema::table('avisos', function (Blueprint $table) {
                $table->dropColumn('destaque');
            });
        }
    }
};
