<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela lead_tracking — armazena dados de rastreamento de origem
     * capturados pelo JavaScript do site antes do clique no WhatsApp.
     *
     * NÃO altera nenhuma tabela existente.
     */
    public function up(): void
    {
        Schema::create('lead_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('gclid', 500)->nullable();
            $table->string('fbclid', 500)->nullable();
            $table->string('utm_source', 255)->nullable();
            $table->string('utm_medium', 255)->nullable();
            $table->string('utm_campaign', 255)->nullable();
            $table->string('utm_content', 255)->nullable();
            $table->string('utm_term', 255)->nullable();
            $table->text('landing_page')->nullable();
            $table->text('referrer')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['phone', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_tracking');
    }
};
