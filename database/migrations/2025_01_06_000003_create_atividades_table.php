<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atividades', function (Blueprint $table) {
            $table->id();
            $table->string('datajuri_id')->unique();
            $table->string('titulo')->nullable();
            $table->string('status')->nullable();
            $table->string('tipo')->nullable();
            $table->string('responsavel_nome')->nullable();
            $table->string('responsavel_id')->nullable();
            $table->string('processo_id')->nullable();
            $table->date('data_vencimento')->nullable();
            $table->date('data_conclusao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atividades');
    }
};
