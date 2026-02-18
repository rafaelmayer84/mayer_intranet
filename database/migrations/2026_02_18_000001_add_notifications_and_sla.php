<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. user_id em wa_messages (quem enviou)
        if (!Schema::hasColumn('wa_messages', 'user_id')) {
            Schema::table('wa_messages', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('is_human');
                $table->index('user_id');
            });
        }

        // 2. assigned_at em wa_conversations (SLA de transferência)
        if (!Schema::hasColumn('wa_conversations', 'assigned_at')) {
            Schema::table('wa_conversations', function (Blueprint $table) {
                $table->datetime('assigned_at')->nullable()->after('assigned_user_id');
            });
        }

        // 3. Tabela de notificações
        if (!Schema::hasTable('notifications_intranet')) {
            Schema::create('notifications_intranet', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('tipo', 30)->default('info');
                $table->string('titulo', 255);
                $table->text('mensagem')->nullable();
                $table->string('link', 500)->nullable();
                $table->string('icone', 30)->default('bell');
                $table->boolean('lida')->default(false);
                $table->timestamps();
                $table->index(['user_id', 'lida', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_intranet');
        if (Schema::hasColumn('wa_conversations', 'assigned_at')) {
            Schema::table('wa_conversations', function (Blueprint $table) {
                $table->dropColumn('assigned_at');
            });
        }
        if (Schema::hasColumn('wa_messages', 'user_id')) {
            Schema::table('wa_messages', function (Blueprint $table) {
                $table->dropIndex(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }
};
