<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->string('reply_to_message_id', 255)->nullable()->after('media_caption')
                  ->comment('provider_message_id da mensagem citada (reply/quote)');
        });
    }

    public function down(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->dropColumn('reply_to_message_id');
        });
    }
};
