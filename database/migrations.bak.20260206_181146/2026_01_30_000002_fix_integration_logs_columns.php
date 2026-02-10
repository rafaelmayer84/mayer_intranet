<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('integration_logs', 'sistema')) {
                $table->string('sistema', 50)->nullable()->after('id');
            }
            if (!Schema::hasColumn('integration_logs', 'tipo')) {
                $table->string('tipo', 100)->nullable()->after('sistema');
            }
            if (!Schema::hasColumn('integration_logs', 'status')) {
                $table->string('status', 20)->nullable()->after('tipo');
            }
            if (!Schema::hasColumn('integration_logs', 'mensagem')) {
                $table->text('mensagem')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('integration_logs', function (Blueprint $table) {
            $table->dropColumn(['sistema', 'tipo', 'status', 'mensagem']);
        });
    }
};
