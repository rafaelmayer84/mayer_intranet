<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE crm_accounts
            MODIFY COLUMN lifecycle
            ENUM('onboarding','ativo','adormecido','risco','arquivado','inadimplente','bloqueado_adversa')
            NOT NULL DEFAULT 'onboarding'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE crm_accounts SET lifecycle='arquivado'
            WHERE lifecycle IN ('inadimplente','bloqueado_adversa')
        ");

        DB::statement("
            ALTER TABLE crm_accounts
            MODIFY COLUMN lifecycle
            ENUM('onboarding','ativo','adormecido','risco','arquivado')
            NOT NULL DEFAULT 'onboarding'
        ");
    }
};
