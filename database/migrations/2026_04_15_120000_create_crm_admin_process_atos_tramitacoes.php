<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quem está com o processo (mesa do advogado)
        Schema::table('crm_admin_processes', function (Blueprint $table) {
            $table->unsignedBigInteger('com_user_id')->nullable()->after('owner_user_id');
            $table->index('com_user_id');
        });

        // Atos do processo (documentos/movimentos — coração do dossiê)
        Schema::create('crm_admin_process_atos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_process_id');
            $table->unsignedInteger('numero');   // sequencial no processo: 1, 2, 3...
            $table->unsignedBigInteger('user_id');
            $table->string('tipo', 40);          // despacho, parecer, oficio, requerimento, certidao, protocolo, etc.
            $table->string('titulo');
            $table->text('corpo')->nullable();    // conteúdo do ato / texto livre
            $table->boolean('is_client_visible')->default(false);
            $table->unsignedBigInteger('assinado_por_user_id')->nullable();
            $table->timestamp('assinado_at')->nullable();
            $table->timestamps();

            $table->index('admin_process_id');
            $table->unique(['admin_process_id', 'numero']);
        });

        // Anexos de atos (arquivos vinculados a cada ato)
        Schema::create('crm_admin_process_ato_anexos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ato_id');
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->string('original_name');
            $table->string('disk_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->index('ato_id');
        });

        // Tramitação (movimentação interna entre advogados)
        Schema::create('crm_admin_process_tramitacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_process_id');
            $table->unsignedBigInteger('de_user_id');
            $table->unsignedBigInteger('para_user_id');
            $table->enum('tipo', ['tramitacao','devolucao','encaminhamento'])->default('tramitacao');
            $table->text('despacho')->nullable();  // mensagem ao tramitar
            $table->timestamp('recebido_at')->nullable(); // quando o destinatário "abriu"
            $table->timestamps();

            $table->index('admin_process_id');
            $table->index('para_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_admin_process_tramitacoes');
        Schema::dropIfExists('crm_admin_process_ato_anexos');
        Schema::dropIfExists('crm_admin_process_atos');

        Schema::table('crm_admin_processes', function (Blueprint $table) {
            $table->dropIndex(['com_user_id']);
            $table->dropColumn('com_user_id');
        });
    }
};
