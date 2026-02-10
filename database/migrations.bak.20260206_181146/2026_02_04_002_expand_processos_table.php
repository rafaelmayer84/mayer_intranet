<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            if (!Schema::hasColumn('processos', 'assunto')) {
                $table->text('assunto')->nullable()->after('titulo');
            }
            if (!Schema::hasColumn('processos', 'natureza')) {
                $table->string('natureza', 150)->nullable()->after('tipo_acao');
            }
            if (!Schema::hasColumn('processos', 'valor_provisionado')) {
                $table->decimal('valor_provisionado', 15, 2)->default(0)->after('valor_causa');
            }
            if (!Schema::hasColumn('processos', 'valor_sentenca')) {
                $table->decimal('valor_sentenca', 15, 2)->default(0)->after('valor_provisionado');
            }
            if (!Schema::hasColumn('processos', 'possibilidade')) {
                $table->string('possibilidade', 50)->nullable()->after('valor_sentenca');
            }
            if (!Schema::hasColumn('processos', 'ganho_causa')) {
                $table->boolean('ganho_causa')->default(false)->after('possibilidade');
            }
            if (!Schema::hasColumn('processos', 'tipo_encerramento')) {
                $table->string('tipo_encerramento', 100)->nullable()->after('ganho_causa');
            }
            if (!Schema::hasColumn('processos', 'cliente_documento')) {
                $table->string('cliente_documento', 25)->nullable()->after('cliente_id');
            }
            if (!Schema::hasColumn('processos', 'proprietario_nome')) {
                $table->string('proprietario_nome', 150)->nullable()->after('advogado_id');
            }
            if (!Schema::hasColumn('processos', 'proprietario_id')) {
                $table->unsignedBigInteger('proprietario_id')->nullable()->after('proprietario_nome');
            }
            if (!Schema::hasColumn('processos', 'data_cadastro_dj')) {
                $table->datetime('data_cadastro_dj')->nullable()->after('proprietario_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            $cols = ['assunto','natureza','valor_provisionado','valor_sentenca','possibilidade','ganho_causa','tipo_encerramento','cliente_documento','proprietario_nome','proprietario_id','data_cadastro_dj'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('processos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
