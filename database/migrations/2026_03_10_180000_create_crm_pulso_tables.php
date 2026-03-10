<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Consolidação diária por cliente
        Schema::create('crm_pulso_diario', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->date('data');
            $table->integer('wa_msgs_incoming')->default(0);
            $table->integer('wa_conversations_opened')->default(0);
            $table->integer('tickets_abertos')->default(0);
            $table->integer('crm_interactions')->default(0);
            $table->integer('phone_calls')->default(0);
            $table->integer('total_contatos')->default(0);
            $table->boolean('has_movimentacao')->default(false);
            $table->boolean('threshold_exceeded')->default(false);
            $table->timestamps();

            $table->unique(['account_id', 'data']);
            $table->index('data');
            $table->index('threshold_exceeded');
            $table->foreign('account_id')->references('id')->on('crm_accounts')->onDelete('cascade');
        });

        // 2. Alertas gerados
        Schema::create('crm_pulso_alertas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->enum('tipo', ['diario_excedido', 'semanal_excedido', 'reiteracao', 'fora_horario']);
            $table->text('descricao');
            $table->json('dados_json')->nullable();
            $table->enum('status', ['pendente', 'visto', 'resolvido'])->default('pendente');
            $table->timestamp('notificado_em')->nullable();
            $table->unsignedBigInteger('resolvido_por')->nullable();
            $table->timestamp('resolvido_em')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index('status');
            $table->foreign('account_id')->references('id')->on('crm_accounts')->onDelete('cascade');
            $table->foreign('resolvido_por')->references('id')->on('users')->nullOnDelete();
        });

        // 3. Controle de uploads de ligações
        Schema::create('crm_pulso_phone_uploads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('filename');
            $table->integer('registros_processados')->default(0);
            $table->integer('registros_ignorados')->default(0);
            $table->date('periodo_inicio')->nullable();
            $table->date('periodo_fim')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 4. Thresholds configuráveis
        Schema::create('crm_pulso_config', function (Blueprint $table) {
            $table->id();
            $table->string('chave')->unique();
            $table->string('valor');
            $table->text('descricao')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_pulso_config');
        Schema::dropIfExists('crm_pulso_phone_uploads');
        Schema::dropIfExists('crm_pulso_alertas');
        Schema::dropIfExists('crm_pulso_diario');
    }
};
