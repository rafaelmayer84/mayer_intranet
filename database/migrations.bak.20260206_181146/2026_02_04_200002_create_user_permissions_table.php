<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('modulo_id')->constrained('modulos')->onDelete('cascade');
            $table->boolean('pode_visualizar')->default(false);
            $table->boolean('pode_editar')->default(false);
            $table->boolean('pode_criar')->default(false);
            $table->boolean('pode_excluir')->default(false);
            $table->boolean('pode_executar')->default(false);
            $table->enum('escopo', ['proprio', 'equipe', 'todos'])->default('proprio');
            $table->timestamps();
            
            $table->unique(['user_id', 'modulo_id']);
            $table->index('user_id');
            $table->index('modulo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
    }
};
