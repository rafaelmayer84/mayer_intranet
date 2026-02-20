<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexo_notificacoes', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['audiencia', 'andamento', 'os'])->index();
            $table->unsignedBigInteger('entidade_id')->comment('ID da atividade/andamento/OS');
            $table->string('entidade_type', 60)->comment('Tabela de origem');
            $table->unsignedBigInteger('cliente_id')->nullable()->comment('ID local do cliente');
            $table->string('cliente_nome', 255)->nullable();
            $table->string('telefone', 20)->nullable()->comment('E.164 sem +');
            $table->string('template_name', 80)->nullable();
            $table->json('template_vars')->nullable();
            $table->enum('status', ['pending', 'approved', 'sent', 'failed', 'skipped'])->default('pending')->index();
            $table->unsignedBigInteger('user_id')->nullable()->comment('Advogado responsavel');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('processo_pasta', 100)->nullable();
            $table->timestamps();

            $table->unique(['tipo', 'entidade_id'], 'uniq_tipo_entidade');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexo_notificacoes');
    }
};
