<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'datajuri_proprietario_id')) {
                $table->unsignedBigInteger('datajuri_proprietario_id')->nullable()->after('role')
                      ->comment('ID do proprietario no DataJuri');
            }
            if (!Schema::hasColumn('users', 'datajuri_perfil')) {
                $table->string('datajuri_perfil', 50)->nullable()->after('datajuri_proprietario_id');
            }
            if (!Schema::hasColumn('users', 'datajuri_ativo')) {
                $table->boolean('datajuri_ativo')->default(false)->after('datajuri_perfil');
            }
            if (!Schema::hasColumn('users', 'celular')) {
                $table->string('celular', 30)->nullable()->after('telefone');
            }
        });

        // Unique index separado para evitar erro se coluna já existia
        if (!collect(DB::select("SHOW INDEX FROM users WHERE Key_name = 'uk_users_datajuri_prop_id'"))->count()) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('datajuri_proprietario_id', 'uk_users_datajuri_prop_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('uk_users_datajuri_prop_id');
            $table->dropColumn(['datajuri_proprietario_id', 'datajuri_perfil', 'datajuri_ativo', 'celular']);
        });
    }
};
