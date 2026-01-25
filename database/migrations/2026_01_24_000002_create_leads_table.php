<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();
            $table->enum('origem', ['WhatsApp Bot', 'Parceiros', 'Site', 'Indicação', 'Outro'])->default('Outro');
            $table->string('cidade')->nullable();
            $table->enum('status', ['novo', 'contactado', 'qualificado', 'convertido', 'perdido'])->default('novo');
            $table->string('motivo_perda')->nullable();
            $table->unsignedBigInteger('responsavel_id')->nullable();
            $table->string('espocrm_id')->unique()->nullable();
            $table->date('data_criacao_lead')->nullable();
            $table->date('data_conversao')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('responsavel_id')->references('id')->on('users')->onDelete('set null');
            
            // Índices
            $table->index(['status', 'origem']);
            $table->index('status');
            $table->index('origem');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
