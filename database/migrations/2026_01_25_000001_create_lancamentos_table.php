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
        Schema::create('lancamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            $table->enum('tipo', ['receita', 'despesa'])->default('receita');
            $table->decimal('valor', 15, 2);
            $table->text('descricao')->nullable();
            $table->date('data');
            $table->string('referencia')->nullable(); // Contrato, NF, etc
            $table->string('status')->default('pendente'); // pendente, pago, cancelado
            $table->timestamps();
            $table->softDeletes();
            
            // Índices para performance
            $table->index('cliente_id');
            $table->index('data');
            $table->index('tipo');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lancamentos');
    }
};
