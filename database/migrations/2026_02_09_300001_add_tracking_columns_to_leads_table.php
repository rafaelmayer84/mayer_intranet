<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona campos de rastreamento UTM + FBCLID na tabela leads.
     * Os campos gclid e origem_canal JÁ existem — não são tocados.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'utm_source')) {
                $table->string('utm_source', 255)->nullable()->after('gclid');
            }
            if (!Schema::hasColumn('leads', 'utm_medium')) {
                $table->string('utm_medium', 255)->nullable()->after('utm_source');
            }
            if (!Schema::hasColumn('leads', 'utm_campaign')) {
                $table->string('utm_campaign', 255)->nullable()->after('utm_medium');
            }
            if (!Schema::hasColumn('leads', 'utm_content')) {
                $table->string('utm_content', 255)->nullable()->after('utm_campaign');
            }
            if (!Schema::hasColumn('leads', 'utm_term')) {
                $table->string('utm_term', 255)->nullable()->after('utm_content');
            }
            if (!Schema::hasColumn('leads', 'fbclid')) {
                $table->string('fbclid', 500)->nullable()->after('utm_term');
            }
            if (!Schema::hasColumn('leads', 'landing_page')) {
                $table->text('landing_page')->nullable()->after('fbclid');
            }
            if (!Schema::hasColumn('leads', 'referrer_url')) {
                $table->text('referrer_url')->nullable()->after('landing_page');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $cols = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid', 'landing_page', 'referrer_url'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('leads', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
