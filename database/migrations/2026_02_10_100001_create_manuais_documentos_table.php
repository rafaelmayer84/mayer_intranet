<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manuais_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('manuais_grupos')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->text('url_onedrive');
            $table->date('data_publicacao')->nullable();
            $table->integer('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['grupo_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manuais_documentos');
    }
};
