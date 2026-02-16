<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('nexo_tickets', 'resolucao')) {
            Schema::table('nexo_tickets', function (Blueprint $table) {
                $table->text('resolucao')->nullable()->after('mensagem');
            });
        }

        if (!Schema::hasColumn('nexo_ticket_notas', 'tipo')) {
            Schema::table('nexo_ticket_notas', function (Blueprint $table) {
                $table->string('tipo', 20)->default('tratativa')->after('texto');
            });
        }
    }

    public function down(): void
    {
        Schema::table('nexo_tickets', function (Blueprint $table) {
            $table->dropColumn('resolucao');
        });
        Schema::table('nexo_ticket_notas', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
