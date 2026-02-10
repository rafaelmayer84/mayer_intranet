<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════
        // TABELA 1: leads_tracking (temporária)
        // ═══════════════════════════════════════════════════════════
        // Armazena dados de tracking ANTES da primeira mensagem WhatsApp
        // Registro é criado no momento do clique no botão WhatsApp
        // Fica aguardando até o webhook receber a primeira mensagem
        
        Schema::create('leads_tracking', function (Blueprint $table) {
            $table->id();
            
            // Telefone (chave para matching com webhook)
            $table->string('phone', 20)->index();
            
            // Google Ads
            $table->string('gclid', 255)->nullable();
            
            // Facebook Ads
            $table->string('fbclid', 255)->nullable();
            
            // UTM Parameters (Google Analytics)
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->string('utm_content', 100)->nullable();
            $table->string('utm_term', 100)->nullable();
            
            // Referrer e Landing Page
            $table->text('referrer_url')->nullable();
            $table->text('landing_page')->nullable();
            
            // Timestamps
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('matched_at')->nullable(); // Quando foi matchado com conversa
            
            // Índices para performance
            $table->index(['phone', 'created_at']);
            $table->index('matched_at');
        });
        
        // ═══════════════════════════════════════════════════════════
        // TABELA 2: wa_conversation_sources (definitiva)
        // ═══════════════════════════════════════════════════════════
        // Armazena a origem de marketing de cada conversa WhatsApp
        // Criada pelo webhook quando recebe a primeira mensagem
        
        Schema::create('wa_conversation_sources', function (Blueprint $table) {
            $table->id();
            
            // Foreign Key para wa_conversations
            $table->unsignedBigInteger('conversation_id')->unique();
            
            // Google Ads
            $table->string('gclid', 255)->nullable()->index();
            
            // Facebook Ads
            $table->string('fbclid', 255)->nullable()->index();
            
            // UTM Parameters (Google Analytics)
            $table->string('utm_source', 100)->nullable()->index();
            $table->string('utm_medium', 100)->nullable()->index();
            $table->string('utm_campaign', 100)->nullable()->index();
            $table->string('utm_content', 100)->nullable();
            $table->string('utm_term', 100)->nullable();
            
            // Referrer e Landing Page
            $table->text('referrer_url')->nullable();
            $table->text('landing_page')->nullable();
            
            // Timestamp
            $table->timestamp('created_at')->useCurrent();
            
            // Foreign key constraint
            $table->foreign('conversation_id')
                  ->references('id')
                  ->on('wa_conversations')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_conversation_sources');
        Schema::dropIfExists('leads_tracking');
    }
};
