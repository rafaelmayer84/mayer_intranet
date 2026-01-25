<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Iniciando correção das tabelas..." . PHP_EOL;

try {
    if (!Schema::hasTable('clientes')) {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('espo_id')->unique();
            $table->string('nome');
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();
            $table->string('status')->nullable();
            $table->string('tipo')->nullable();
            $table->string('origem')->nullable();
            $table->text('descricao')->nullable();
            $table->string('assigned_user_id')->nullable();
            $table->string('assigned_user_name')->nullable();
            $table->timestamp('data_criacao_espo')->nullable();
            $table->timestamp('data_alteracao_espo')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        echo "Tabela 'clientes' criada." . PHP_EOL;
    }

    if (!Schema::hasTable('leads')) {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('espo_id')->unique();
            $table->string('nome');
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();
            $table->string('status')->nullable();
            $table->string('origem')->nullable();
            $table->text('descricao')->nullable();
            $table->string('assigned_user_id')->nullable();
            $table->string('assigned_user_name')->nullable();
            $table->timestamp('data_criacao_espo')->nullable();
            $table->timestamp('data_alteracao_espo')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        echo "Tabela 'leads' criada." . PHP_EOL;
    }

    if (!Schema::hasTable('oportunidades')) {
        Schema::create('oportunidades', function (Blueprint $table) {
            $table->id();
            $table->string('espo_id')->unique();
            $table->string('nome');
            $table->decimal('valor', 15, 2)->nullable();
            $table->string('estagio')->nullable();
            $table->date('data_fechamento')->nullable();
            $table->integer('probabilidade')->nullable();
            $table->string('cliente_id')->nullable();
            $table->string('assigned_user_id')->nullable();
            $table->string('assigned_user_name')->nullable();
            $table->timestamp('data_criacao_espo')->nullable();
            $table->timestamp('data_alteracao_espo')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        echo "Tabela 'oportunidades' criada." . PHP_EOL;
    }

    if (!Schema::hasTable('integration_logs')) {
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('modulo');
            $table->string('tipo');
            $table->string('status');
            $table->integer('registros_processados')->default(0);
            $table->integer('registros_sucesso')->default(0);
            $table->integer('registros_erro')->default(0);
            $table->text('mensagem')->nullable();
            $table->json('detalhes_erro')->nullable();
            $table->timestamp('data_inicio')->nullable();
            $table->timestamp('data_fim')->nullable();
            $table->timestamps();
        });
        echo "Tabela 'integration_logs' criada." . PHP_EOL;
    }

    echo "Correção concluída com sucesso!" . PHP_EOL;
} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . PHP_EOL;
}
