<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_opportunities', 'espo_id')) {
                $table->string('espo_id', 20)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('crm_opportunities', 'lost_reason')) {
                $table->string('lost_reason', 100)->nullable()->after('status');
            }
            if (!Schema::hasColumn('crm_opportunities', 'tipo_demanda')) {
                $table->string('tipo_demanda', 100)->nullable()->after('lost_reason');
            }
            if (!Schema::hasColumn('crm_opportunities', 'lead_source')) {
                $table->string('lead_source', 100)->nullable()->after('tipo_demanda');
            }
            if (!Schema::hasColumn('crm_opportunities', 'close_date')) {
                $table->date('close_date')->nullable()->after('amount');
            }
            if (!Schema::hasColumn('crm_opportunities', 'currency')) {
                $table->string('currency', 3)->default('BRL')->after('amount');
            }
            if (!Schema::hasColumn('crm_opportunities', 'probability')) {
                $table->unsignedTinyInteger('probability')->nullable()->after('currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table) {
            $cols = ['espo_id', 'lost_reason', 'tipo_demanda', 'lead_source', 'close_date', 'currency', 'probability'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('crm_opportunities', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
