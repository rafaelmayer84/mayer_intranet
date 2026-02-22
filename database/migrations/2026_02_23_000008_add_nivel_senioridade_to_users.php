<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'nivel_senioridade')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('nivel_senioridade', 30)
                    ->nullable()
                    ->after('role')
                    ->comment('Junior, Pleno, Senior_I, Senior_II, Senior_III');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'nivel_senioridade')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('nivel_senioridade');
            });
        }
    }
};
