<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE integration_logs MODIFY COLUMN tipo ENUM(
            'sync_clientes',
            'sync_leads', 
            'sync_oportunidades',
            'sync_full',
            'sync_movimentos',
            'sync_all',
            'movimentos',
            'clientes',
            'all'
        ) DEFAULT 'sync_full'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE integration_logs MODIFY COLUMN tipo ENUM(
            'sync_clientes',
            'sync_leads',
            'sync_oportunidades',
            'sync_full'
        ) DEFAULT 'sync_full'");
    }
};
