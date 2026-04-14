<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_conversations', function (Blueprint $table) {
            // Registra quando o lembrete de inatividade (6h) foi enviado neste ciclo
            // NULL = lembrete ainda não enviado no ciclo atual
            $table->timestamp('lembrete_inatividade_at')->nullable()->after('last_incoming_at');
        });
    }

    public function down(): void
    {
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->dropColumn('lembrete_inatividade_at');
        });
    }
};
