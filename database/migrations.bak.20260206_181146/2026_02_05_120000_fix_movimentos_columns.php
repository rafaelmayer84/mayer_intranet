<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration de correção - Garante que todas as colunas necessárias existam
 * na tabela movimentos para que a sincronização funcione corretamente.
 * 
 * Criado: 05/02/2026
 * Motivo: Corrigir problemas após quebra do Orchestrator
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentos', function (Blueprint $table) {
            // Colunas que podem estar faltando
            if (!Schema::hasColumn('movimentos', 'origem')) {
                $table->string('origem', 50)->default('datajuri')->after('id');
            }
            
            if (!Schema::hasColumn('movimentos', 'payload_hash')) {
                $table->string('payload_hash', 64)->nullable();
            }
            
            if (!Schema::hasColumn('movimentos', 'updated_at_api')) {
                $table->timestamp('updated_at_api')->nullable();
            }
            
            if (!Schema::hasColumn('movimentos', 'is_stale')) {
                $table->boolean('is_stale')->default(false);
            }
            
            if (!Schema::hasColumn('movimentos', 'payload_raw')) {
                $table->json('payload_raw')->nullable();
            }
            
            // Campos da expansão de 04/02
            if (!Schema::hasColumn('movimentos', 'pessoa_id_datajuri')) {
                $table->unsignedBigInteger('pessoa_id_datajuri')->nullable();
            }
            
            if (!Schema::hasColumn('movimentos', 'contrato_id_datajuri')) {
                $table->unsignedBigInteger('contrato_id_datajuri')->nullable();
            }
            
            if (!Schema::hasColumn('movimentos', 'processo_pasta')) {
                $table->string('processo_pasta', 50)->nullable();
            }
            
            if (!Schema::hasColumn('movimentos', 'proprietario_nome')) {
                $table->string('proprietario_nome', 150)->nullable();
            }
            
            if (!Schema::hasColumn('movimentos', 'plano_conta_id')) {
                $table->unsignedBigInteger('plano_conta_id')->nullable();
            }
            
            if (!Schema::hasColumn('movimentos', 'proprietario_id')) {
                $table->unsignedBigInteger('proprietario_id')->nullable();
            }
            
            if (!Schema::hasColumn('movimentos', 'tipo_classificacao')) {
                $table->string('tipo_classificacao', 50)->nullable();
            }
        });
        
        // Criar tabela sync_runs se não existir
        if (!Schema::hasTable('sync_runs')) {
            Schema::create('sync_runs', function (Blueprint $table) {
                $table->id();
                $table->string('run_id', 36)->unique();
                $table->enum('tipo', ['full', 'incremental', 'reprocessar_financeiro'])->default('full');
                $table->enum('status', ['running', 'completed', 'failed', 'cancelled'])->default('running');
                $table->string('modulo_atual')->nullable();
                $table->integer('pagina_atual')->default(0);
                $table->integer('total_paginas')->default(0);
                $table->integer('registros_processados')->default(0);
                $table->integer('registros_criados')->default(0);
                $table->integer('registros_atualizados')->default(0);
                $table->integer('registros_deletados')->default(0);
                $table->integer('erros')->default(0);
                $table->json('modulos_processados')->nullable();
                $table->json('erros_detalhados')->nullable();
                $table->text('mensagem')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
                
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        // Esta migration é de correção, não removemos nada no down
    }
};
