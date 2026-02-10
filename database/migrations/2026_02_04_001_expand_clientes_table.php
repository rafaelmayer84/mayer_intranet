<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // Campos que ainda não existem
            if (!Schema::hasColumn('clientes', 'is_cliente')) {
                $table->boolean('is_cliente')->default(false)->after('tipo');
            }
            if (!Schema::hasColumn('clientes', 'status_pessoa')) {
                $table->string('status_pessoa', 100)->nullable()->after('is_cliente');
            }
            if (!Schema::hasColumn('clientes', 'valor_hora')) {
                $table->decimal('valor_hora', 15, 2)->default(0)->after('valor_carteira');
            }
            if (!Schema::hasColumn('clientes', 'total_contas_receber')) {
                $table->decimal('total_contas_receber', 15, 2)->default(0)->after('valor_hora');
            }
            if (!Schema::hasColumn('clientes', 'total_contas_vencidas')) {
                $table->decimal('total_contas_vencidas', 15, 2)->default(0)->after('total_contas_receber');
            }
            if (!Schema::hasColumn('clientes', 'valor_contas_abertas')) {
                $table->decimal('valor_contas_abertas', 15, 2)->default(0)->after('total_contas_vencidas');
            }
            if (!Schema::hasColumn('clientes', 'cpf')) {
                $table->string('cpf', 20)->nullable()->after('cpf_cnpj');
            }
            if (!Schema::hasColumn('clientes', 'cnpj')) {
                $table->string('cnpj', 25)->nullable()->after('cpf');
            }
            if (!Schema::hasColumn('clientes', 'data_nascimento')) {
                $table->date('data_nascimento')->nullable()->after('cnpj');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $cols = ['is_cliente','status_pessoa','valor_hora','total_contas_receber','total_contas_vencidas','valor_contas_abertas','cpf','cnpj','data_nascimento'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('clientes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
