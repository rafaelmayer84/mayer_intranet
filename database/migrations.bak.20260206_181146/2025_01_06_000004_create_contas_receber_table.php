<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_receber', function (Blueprint $table) {
            $table->id();
            $table->string('datajuri_id')->unique();
            $table->string('descricao')->nullable();
            $table->decimal('valor', 15, 2)->default(0);
            $table->date('data_vencimento')->nullable();
            $table->date('data_pagamento')->nullable();
            $table->string('status')->nullable();
            $table->string('plano_conta')->nullable();
            $table->string('cliente_nome')->nullable();
            $table->string('cliente_id')->nullable();
            $table->string('responsavel_nome')->nullable();
            $table->string('responsavel_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas_receber');
    }
};
