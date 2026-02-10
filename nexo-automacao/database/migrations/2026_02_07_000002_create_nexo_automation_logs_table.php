<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexo_automation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('telefone', 20)->index();
            $table->enum('acao', [
                'identificacao',
                'auth_sucesso',
                'auth_falha',
                'auth_bloqueio',
                'consulta_status',
                'consulta_boleto',
                'erro'
            ])->index();
            $table->json('dados')->nullable();
            $table->text('resposta_ia')->nullable();
            $table->integer('tempo_resposta_ms')->nullable();
            $table->string('erro', 500)->nullable();
            $table->timestamps();
            
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexo_automation_logs');
    }
};
