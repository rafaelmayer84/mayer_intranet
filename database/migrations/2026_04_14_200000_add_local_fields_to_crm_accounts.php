<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_accounts', function (Blueprint $table) {
            // Campos pessoais editáveis pelo admin (override do DataJuri)
            $table->string('profissao', 255)->nullable()->after('notes');
            $table->date('data_nascimento')->nullable()->after('profissao');
            $table->string('endereco_cidade', 100)->nullable()->after('data_nascimento');
            $table->string('endereco_estado', 2)->nullable()->after('endereco_cidade');
        });
    }

    public function down(): void
    {
        Schema::table('crm_accounts', function (Blueprint $table) {
            $table->dropColumn(['profissao', 'data_nascimento', 'endereco_cidade', 'endereco_estado']);
        });
    }
};
