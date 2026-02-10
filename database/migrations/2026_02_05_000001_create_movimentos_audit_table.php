<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela de auditoria para mudanças detectadas
        Schema::create('movimentos_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('movimento_id')->nullable();
            $table->unsignedBigInteger('datajuri_id');
            $table->enum('tipo_alteracao', ['criado', 'alterado', 'removido', 'reativado']);
            
            // Snapshot do antes (JSON)
            $table->json('dados_antes')->nullable();
            
            // Snapshot do depois (JSON)  
            $table->json('dados_depois')->nullable();
            
            // Campos críticos que mudaram (para consulta rápida)
            $table->decimal('valor_antes', 15, 2)->nullable();
            $table->decimal('valor_depois', 15, 2)->nullable();
            $table->string('plano_antes', 100)->nullable();
            $table->string('plano_depois', 100)->nullable();
            $table->string('classificacao_antes', 50)->nullable();
            $table->string('classificacao_depois', 50)->nullable();
            
            // Contexto da sync
            $table->string('sync_run_id', 50)->nullable();
            $table->timestamp('detectado_em');
            
            $table->index('movimento_id');
            $table->index('datajuri_id');
            $table->index('tipo_alteracao');
            $table->index('detectado_em');
            $table->index('sync_run_id');
        });

        // Adicionar índice no payload_hash se não existir
        Schema::table('movimentos', function (Blueprint $table) {
            // Adicionar campo sync_status se não existir
            if (!Schema::hasColumn('movimentos', 'sync_status')) {
                $table->enum('sync_status', ['ativo', 'removido', 'alterado', 'novo'])->default('ativo')->after('is_stale');
            }
            
            // Adicionar campo para última sync que viu este registro
            if (!Schema::hasColumn('movimentos', 'ultima_sync_id')) {
                $table->string('ultima_sync_id', 50)->nullable()->after('sync_status');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentos_audit');
        
        Schema::table('movimentos', function (Blueprint $table) {
            if (Schema::hasColumn('movimentos', 'sync_status')) {
                $table->dropColumn('sync_status');
            }
            if (Schema::hasColumn('movimentos', 'ultima_sync_id')) {
                $table->dropColumn('ultima_sync_id');
            }
        });
    }
};
