<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE crm_account_data_gates
            MODIFY COLUMN status
            ENUM('aberto','em_revisao','resolvido_auto','resolvido_manual','excecao_justificada','escalado','cancelado')
            NOT NULL DEFAULT 'aberto'
        ");

        Schema::table('crm_account_data_gates', function (Blueprint $table) {
            $table->text('excecao_justificativa')->nullable()->after('dj_valor_no_fechamento');
            $table->unsignedBigInteger('excecao_by_user_id')->nullable()->after('excecao_justificativa');
            $table->timestamp('excecao_at')->nullable()->after('excecao_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_account_data_gates', function (Blueprint $table) {
            $table->dropColumn(['excecao_justificativa','excecao_by_user_id','excecao_at']);
        });
        DB::statement("
            ALTER TABLE crm_account_data_gates
            MODIFY COLUMN status
            ENUM('aberto','em_revisao','resolvido_auto','resolvido_manual','escalado','cancelado')
            NOT NULL DEFAULT 'aberto'
        ");
    }
};
