<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modulos', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('nome', 100);
            $table->string('grupo', 50);
            $table->string('descricao')->nullable();
            $table->string('rota')->nullable();
            $table->string('icone', 10)->nullable();
            $table->integer('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            
            $table->index('grupo');
            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modulos');
    }
};
