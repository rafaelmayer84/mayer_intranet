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
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 36)->unique()->comment('UUID da execução');
            $table->enum('tipo', ['full', 'incremental', 'reprocessar_financeiro'])->default('full');
            $table->enum('status', ['running', 'completed', 'failed', 'cancelled'])->default('running');
            $table->string('modulo_atual')->nullable()->comment('Módulo sendo processado');
            $table->integer('pagina_atual')->default(0);
            $table->integer('total_paginas')->default(0);
            $table->integer('registros_processados')->default(0);
            $table->integer('registros_criados')->default(0);
            $table->integer('registros_atualizados')->default(0);
            $table->integer('registros_deletados')->default(0);
            $table->integer('erros')->default(0);
            $table->json('modulos_processados')->nullable()->comment('Lista de módulos já processados');
            $table->json('erros_detalhados')->nullable()->comment('Lista de erros com detalhes');
            $table->text('mensagem')->nullable()->comment('Mensagem de status ou erro');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
            $table->index('run_id');
        });
        
        // Adicionar colunas de controle nas tabelas sincronizadas
        $tablesToUpdate = ['movimentos', 'processos', 'clientes', 'atividades', 'contas_receber'];
        
        foreach ($tablesToUpdate as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (!Schema::hasColumn($tableName, 'origem')) {
                        $table->string('origem', 50)->default('datajuri')->after('id');
                    }
                    if (!Schema::hasColumn($tableName, 'payload_hash')) {
                        $table->string('payload_hash', 64)->nullable()->comment('SHA256 do payload para detectar mudanças');
                    }
                    if (!Schema::hasColumn($tableName, 'updated_at_api')) {
                        $table->timestamp('updated_at_api')->nullable()->comment('Data de atualização na API');
                    }
                    if (!Schema::hasColumn($tableName, 'is_stale')) {
                        $table->boolean('is_stale')->default(false)->comment('Marcado para remoção no reprocessamento');
                    }
                    if (!Schema::hasColumn($tableName, 'payload_raw')) {
                        $table->json('payload_raw')->nullable()->comment('Payload bruto para auditoria');
                    }
                });
                
                // Criar índice único se não existir
                try {
                    Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                        $table->unique(['origem', 'datajuri_id'], "{$tableName}_origem_datajuri_id_unique");
                    });
                } catch (\Exception $e) {
                    // Índice já existe, ignorar
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
        
        $tablesToUpdate = ['movimentos', 'processos', 'clientes', 'atividades', 'contas_receber'];
        
        foreach ($tablesToUpdate as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $columns = ['origem', 'payload_hash', 'updated_at_api', 'is_stale', 'payload_raw'];
                    foreach ($columns as $col) {
                        if (Schema::hasColumn($tableName, $col)) {
                            $table->dropColumn($col);
                        }
                    }
                });
            }
        }
    }
};
