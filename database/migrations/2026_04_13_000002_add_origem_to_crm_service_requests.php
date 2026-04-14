<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona suporte a tickets originados por clientes via autoatendimento NEXO.
     *
     * - origem: distingue tickets criados internamente ('crm') vs pelo cliente ('autoatendimento')
     * - phone_contato: telefone do cliente quando não há account_id resolvido
     * - requested_by_user_id: torna nullable (autoatendimento não tem user logado)
     * - account_id: torna nullable (edge case: cliente sem CrmAccount ainda)
     */
    public function up(): void
    {
        Schema::table('crm_service_requests', function (Blueprint $table) {
            // Origem da solicitação
            $table->string('origem', 30)
                ->default('crm')
                ->after('category')
                ->comment('crm | autoatendimento | nexo');

            // Telefone do contato para tickets de cliente sem account_id
            $table->string('phone_contato', 30)
                ->nullable()
                ->after('origem')
                ->comment('Telefone E164 do cliente — preenchido quando conta não está no CRM');

            // Tornar account_id nullable: cliente pode não ter CrmAccount ainda
            $table->unsignedBigInteger('account_id')->nullable()->change();

            // Tornar requested_by_user_id nullable: autoatendimento não tem user logado
            $table->unsignedBigInteger('requested_by_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('crm_service_requests', function (Blueprint $table) {
            $table->dropColumn(['origem', 'phone_contato']);
            $table->unsignedBigInteger('account_id')->nullable(false)->change();
            $table->unsignedBigInteger('requested_by_user_id')->nullable(false)->change();
        });
    }
};
