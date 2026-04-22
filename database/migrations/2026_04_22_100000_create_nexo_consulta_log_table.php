<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexo_consulta_log', function (Blueprint $table) {
            $table->id();
            $table->string('telefone', 30);
            $table->unsignedBigInteger('cliente_id')->nullable();
            // acao: identificar, auth_ok, auth_falha, consulta_status, consulta_processo, definir_pin, probe_suspeito
            $table->string('acao', 50);
            // resultado: ok, falha, bloqueado, nao_encontrado
            $table->string('resultado', 20);
            $table->string('ip', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexo_consulta_log');
    }
};
