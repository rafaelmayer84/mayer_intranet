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
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_id')->index();
            $table->enum('tipo', ['sync_clientes', 'sync_leads', 'sync_oportunidades', 'sync_full'])->default('sync_full');
            $table->enum('fonte', ['datajuri', 'espocrm', 'manual'])->default('manual');
            $table->enum('status', ['iniciado', 'em_progresso', 'concluido', 'erro'])->default('iniciado');
            $table->integer('registros_processados')->default(0);
            $table->integer('registros_criados')->default(0);
            $table->integer('registros_atualizados')->default(0);
            $table->integer('registros_ignorados')->default(0);
            $table->integer('registros_erro')->default(0);
            $table->text('mensagem_erro')->nullable();
            $table->json('detalhes')->nullable();
            $table->timestamp('inicio')->nullable();
            $table->timestamp('fim')->nullable();
            $table->integer('duracao_segundos')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index(['tipo', 'status']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
    }
};
