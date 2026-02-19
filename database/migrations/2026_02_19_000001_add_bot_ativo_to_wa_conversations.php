<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->boolean('bot_ativo')->default(true)->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->dropColumn('bot_ativo');
        });
    }
};
