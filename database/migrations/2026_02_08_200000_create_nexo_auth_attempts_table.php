<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexo_auth_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('telefone', 30)->index();
            $table->unsignedTinyInteger('tentativas')->default(0);
            $table->boolean('bloqueado')->default(false);
            $table->timestamp('bloqueado_ate')->nullable();
            $table->timestamp('ultimo_tentativa')->nullable();
            $table->timestamps();

            $table->unique('telefone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexo_auth_attempts');
    }
};
