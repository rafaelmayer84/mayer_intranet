<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela nexo_tickets
        Schema::create('nexo_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->unsignedBigInteger('datajuri_id')->nullable();
            $table->string('telefone', 20)->index();
            $table->string('nome_cliente', 255)->nullable();
            $table->string('assunto', 255);
            $table->text('mensagem')->nullable();
            $table->enum('status', ['aberto', 'em_andamento', 'concluido', 'cancelado'])->default('aberto')->index();
            $table->string('atendente', 100)->nullable();
            $table->text('resposta_interna')->nullable();
            $table->timestamps();

            $table->index('cliente_id');
            $table->index('created_at');
        });

        // 2. Expandir ENUM de nexo_automation_logs.acao
        DB::statement("ALTER TABLE nexo_automation_logs MODIFY COLUMN acao VARCHAR(50) NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('nexo_tickets');

        DB::statement("ALTER TABLE nexo_automation_logs MODIFY COLUMN acao ENUM('identificacao','auth_sucesso','auth_falha','auth_bloqueio','consulta_status','consulta_status_selecao','consulta_status_processo','consulta_boleto','erro') NOT NULL");
    }
};
