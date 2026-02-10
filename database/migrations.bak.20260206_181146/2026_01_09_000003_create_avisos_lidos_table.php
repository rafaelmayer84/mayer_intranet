<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('avisos_lidos')) {
            return;
        }

        Schema::create('avisos_lidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aviso_id')->constrained('avisos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('lido_em')->useCurrent();

            $table->unique(['aviso_id', 'usuario_id'], 'unique_aviso_usuario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_lidos');
    }
};
