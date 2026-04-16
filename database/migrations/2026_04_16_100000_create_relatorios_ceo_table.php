<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('relatorios_ceo', function (Blueprint $table) {
            $table->id();
            $table->date('periodo_inicio');
            $table->date('periodo_fim');
            $table->enum('status', ['queued', 'running', 'success', 'failed'])->default('queued');
            $table->longText('dados_json')->nullable();
            $table->longText('analise_json')->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->string('erro', 2000)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relatorios_ceo');
    }
};
