<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categorias_avisos')) {
            return;
        }

        Schema::create('categorias_avisos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100)->unique();
            $table->text('descricao')->nullable();
            $table->string('cor_hexadecimal', 7)->default('#3B82F6');
            $table->string('icone', 50)->nullable();
            $table->integer('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['ativo', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_avisos');
    }
};
