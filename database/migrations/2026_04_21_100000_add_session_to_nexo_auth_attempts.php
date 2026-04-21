<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nexo_auth_attempts', function (Blueprint $table) {
            $table->string('session_token', 64)->nullable()->after('autenticado_ate');
            $table->json('session_campos')->nullable()->after('session_token');
            $table->timestamp('session_expires_at')->nullable()->after('session_campos');
            $table->index('session_token');
        });
    }

    public function down(): void
    {
        Schema::table('nexo_auth_attempts', function (Blueprint $table) {
            $table->dropIndex(['session_token']);
            $table->dropColumn(['session_token', 'session_campos', 'session_expires_at']);
        });
    }
};
