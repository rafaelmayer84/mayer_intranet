<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('linked_crm_account_id')
                ->nullable()
                ->after('linked_cliente_id')
                ->index()
                ->comment('FK direta para crm_accounts — fonte de verdade de identidade do contato');

            $table->foreign('linked_crm_account_id')
                ->references('id')
                ->on('crm_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wa_conversations', function (Blueprint $table) {
            $table->dropForeign(['linked_crm_account_id']);
            $table->dropIndex(['linked_crm_account_id']);
            $table->dropColumn('linked_crm_account_id');
        });
    }
};
