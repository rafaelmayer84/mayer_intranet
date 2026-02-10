<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manuais_grupo_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('manuais_grupos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['grupo_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuais_grupo_user');
    }
};
