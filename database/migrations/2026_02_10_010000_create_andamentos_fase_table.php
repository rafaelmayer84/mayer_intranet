<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela: andamentos_fase
 * Módulo DataJuri: AndamentoFase
 * Objetivo: Registrar cada andamento processual vinculado a uma fase,
 *           permitindo calcular SLA, identificar processos parados e
 *           alimentar o dashboard de Processos Internos (BSC).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('andamentos_fase', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('datajuri_id')->unique();

            // Vínculos com processo e fase
            $table->unsignedInteger('fase_processo_id_datajuri')->nullable();
            $table->unsignedInteger('processo_id_datajuri')->nullable();
            $table->string('processo_pasta', 50)->nullable();

            // Dados do andamento
            $table->date('data_andamento')->nullable();
            $table->text('descricao')->nullable();
            $table->string('tipo', 100)->nullable();

            // Parecer (revisão por advogado sênior)
            $table->text('parecer')->nullable();
            $table->text('parecer_revisado')->nullable();
            $table->string('parecer_revisado_por', 255)->nullable();
            $table->dateTime('data_parecer_revisado')->nullable();

            // Proprietário (quem fez o andamento)
            $table->unsignedInteger('proprietario_id')->nullable();
            $table->string('proprietario_nome', 255)->nullable();

            $table->timestamps();

            // Índices para queries do dashboard
            $table->index('data_andamento', 'idx_af_data_andamento');
            $table->index('processo_pasta', 'idx_af_processo_pasta');
            $table->index('fase_processo_id_datajuri', 'idx_af_fase_proc');
            $table->index('proprietario_id', 'idx_af_proprietario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('andamentos_fase');
    }
};
