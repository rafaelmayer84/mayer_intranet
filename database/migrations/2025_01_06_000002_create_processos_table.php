<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processos', function (Blueprint $table) {
            $table->id();
            $table->string('datajuri_id')->unique();
            $table->string('numero')->nullable();
            $table->string('titulo')->nullable();
            $table->string('status')->nullable();
            $table->string('tipo_acao')->nullable();
            $table->string('area')->nullable();
            $table->string('cliente_nome')->nullable();
            $table->string('cliente_id')->nullable();
            $table->decimal('valor_causa', 15, 2)->default(0);
            $table->string('advogado_responsavel')->nullable();
            $table->string('advogado_id')->nullable();
            $table->date('data_distribuicao')->nullable();
            $table->date('data_conclusao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processos');
    }
};
