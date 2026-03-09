<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cleanup auditoria 09/03/2026
     * Remove colunas legadas do KpiMonthlyTargetController (descontinuado)
     * Dados migrados para ano/mes/meta_valor pelo script migrate_kpi.py
     */
    public function up(): void
    {
        Schema::table("kpi_monthly_targets", function (Blueprint $table) {
            $table->dropUnique("kpi_monthly_targets_year_month_kpi_key_unique");
            $table->dropIndex("kpi_monthly_targets_year_index");
            $table->dropIndex("kpi_monthly_targets_month_index");
            $table->dropColumn(["year", "month", "target_value"]);
        });
    }

    public function down(): void
    {
        Schema::table("kpi_monthly_targets", function (Blueprint $table) {
            $table->smallInteger("year")->unsigned()->default(0)->after("modulo");
            $table->tinyInteger("month")->unsigned()->default(0)->after("year");
            $table->string("target_value", 80)->nullable()->after("created_by");
        });
    }
};
