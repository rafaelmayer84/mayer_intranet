<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pricing_proposals -> link para oportunidade CRM criada
        Schema::table('pricing_proposals', function (Blueprint $table) {
            $table->unsignedBigInteger('crm_opportunity_id')->nullable()->after('oportunidade_id');
            $table->index('crm_opportunity_id');
        });

        // crm_opportunities -> valor fechado + link para proposta SIPEX
        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->decimal('value_closed', 15, 2)->nullable()->after('value_estimated');
            $table->unsignedBigInteger('sipex_proposal_id')->nullable()->after('datajuri_processo_id');
            $table->index('sipex_proposal_id');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_proposals', function (Blueprint $table) {
            $table->dropIndex(['crm_opportunity_id']);
            $table->dropColumn('crm_opportunity_id');
        });
        Schema::table('crm_opportunities', function (Blueprint $table) {
            $table->dropIndex(['sipex_proposal_id']);
            $table->dropColumn(['value_closed', 'sipex_proposal_id']);
        });
    }
};
