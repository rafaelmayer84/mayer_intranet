<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentos', function (Blueprint $table) {
            $table->id();
            $table->string('datajuri_id')->unique();
            $table->string('descricao')->nullable();
            $table->decimal('valor', 15, 2)->default(0);
            $table->date('data')->nullable(); // Data do lançamento (pagamento efetivo)
            $table->string('tipo')->nullable(); // Crédito ou Débito
            $table->string('plano_conta_codigo')->nullable(); // Ex: 3.01.01.01
            $table->string('plano_conta_nome')->nullable(); // Ex: Receita bruta - Contrato PF
            $table->string('plano_conta_completo')->nullable(); // Nome completo para classificação
            $table->enum('classificacao', ['PF', 'PJ', 'DESPESA', 'OUTRO'])->default('OUTRO');
            $table->string('conta_bancaria')->nullable();
            $table->string('cliente_nome')->nullable();
            $table->string('processo_pasta')->nullable();
            $table->timestamps();
            
            $table->index('data');
            $table->index('classificacao');
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentos');
    }
};
