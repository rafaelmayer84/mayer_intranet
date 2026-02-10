<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('linked_processo_id')->nullable()->after('linked_cliente_id');
            $table->index('linked_processo_id', 'wa_conv_linked_processo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->dropIndex('wa_conv_linked_processo_idx');
            $table->dropColumn('linked_processo_id');
        });
    }
};
