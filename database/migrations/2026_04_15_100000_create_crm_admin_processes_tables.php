<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Templates de tipos de processo (etapas e checklist padrão)
        Schema::create('crm_admin_process_templates', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 60)->unique();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->unsignedInteger('prazo_estimado_dias')->default(30);
            $table->json('steps')->default('[]');        // array de etapas padrão
            $table->json('checklist')->default('[]');    // array de docs exigidos
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Processos administrativos
        Schema::create('crm_admin_processes', function (Blueprint $table) {
            $table->id();
            $table->string('protocolo', 20)->unique();  // ADM-2026-0001
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('opportunity_id')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('tipo', 60);
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->enum('status', [
                'rascunho','aberto','em_andamento',
                'aguardando_cliente','aguardando_terceiro',
                'suspenso','concluido','cancelado'
            ])->default('aberto');
            $table->enum('prioridade', ['baixa','normal','alta','urgente'])->default('normal');
            $table->enum('nivel_acesso', ['normal','restrito'])->default('normal');
            $table->unsignedBigInteger('owner_user_id');
            $table->string('orgao_destino')->nullable();    // "3º Cartório de RI de Florianópolis"
            $table->string('numero_externo')->nullable();   // protocolo no órgão
            $table->date('prazo_estimado')->nullable();
            $table->date('prazo_final')->nullable();
            $table->decimal('valor_honorarios', 10, 2)->nullable();
            $table->decimal('valor_despesas', 10, 2)->nullable();
            $table->boolean('client_visible')->default(true);
            $table->text('suspended_reason')->nullable();
            $table->timestamp('suspended_until')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('concluded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
            $table->index('owner_user_id');
            $table->index('status');
            $table->index('tipo');
        });

        // Etapas do processo
        Schema::create('crm_admin_process_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_process_id');
            $table->unsignedInteger('order')->default(0);
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->enum('tipo', ['interno','externo','cliente','aprovacao'])->default('interno');
            $table->string('orgao')->nullable();            // se externo: qual órgão
            $table->enum('status', [
                'pendente','em_andamento','aguardando',
                'concluido','nao_aplicavel','bloqueado'
            ])->default('pendente');
            $table->unsignedBigInteger('responsible_user_id')->nullable();
            $table->unsignedInteger('deadline_days')->nullable();    // dias úteis para esta etapa
            $table->date('deadline_at')->nullable();                 // data calculada
            $table->timestamp('scheduled_return_at')->nullable();    // retorno programado
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('depends_on_step_id')->nullable();
            $table->boolean('is_client_visible')->default(false);
            $table->boolean('requires_document')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('admin_process_id');
            $table->index('status');
            $table->index('responsible_user_id');
        });

        // Timeline / andamentos
        Schema::create('crm_admin_process_timeline', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_process_id');
            $table->unsignedBigInteger('step_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();   // null = sistema
            $table->string('tipo', 60);   // criado, etapa_concluida, andamento_manual, suspenso, etc.
            $table->string('titulo');
            $table->text('corpo')->nullable();
            $table->boolean('is_client_visible')->default(false);
            $table->boolean('is_internal')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('happened_at')->useCurrent();
            $table->timestamps();

            $table->index('admin_process_id');
            $table->index('happened_at');
        });

        // Documentos do processo
        Schema::create('crm_admin_process_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_process_id');
            $table->unsignedBigInteger('step_id')->nullable();
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->enum('category', [
                'requerido_cliente','produzido_interno',
                'recebido_terceiro','enviado_terceiro','geral'
            ])->default('geral');
            $table->string('original_name');
            $table->string('disk_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->boolean('is_client_visible')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('admin_process_id');
        });

        // Checklist de documentos exigidos
        Schema::create('crm_admin_process_checklist', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_process_id');
            $table->string('nome');                        // "Certidão de matrícula"
            $table->text('descricao')->nullable();
            $table->enum('status', ['pendente','recebido','dispensado'])->default('pendente');
            $table->unsignedBigInteger('document_id')->nullable(); // FK para documento quando recebido
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('dispensed_reason')->nullable();
            $table->timestamps();

            $table->index('admin_process_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_admin_process_checklist');
        Schema::dropIfExists('crm_admin_process_documents');
        Schema::dropIfExists('crm_admin_process_timeline');
        Schema::dropIfExists('crm_admin_process_steps');
        Schema::dropIfExists('crm_admin_processes');
        Schema::dropIfExists('crm_admin_process_templates');
    }
};
