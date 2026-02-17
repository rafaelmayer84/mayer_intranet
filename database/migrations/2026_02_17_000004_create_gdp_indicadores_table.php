<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdp_indicadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eixo_id')->constrained('gdp_eixos')->cascadeOnDelete();
            $table->string('codigo', 10);
            $table->string('nome', 150);
            $table->text('descricao')->nullable();
            $table->enum('chave_atribuicao', ['proprietario', 'advogado_atuante', 'user_nexo', 'manual'])->default('proprietario');
            $table->string('chave_fallback', 30)->nullable();
            $table->string('fonte_dados', 100);
            $table->string('unidade', 20)->default('numero');
            $table->enum('direcao', ['maior_melhor', 'menor_melhor'])->default('maior_melhor');
            $table->decimal('peso', 5, 2);
            $table->decimal('cap_percentual', 5, 2)->default(120.00);
            $table->enum('status_v1', ['score', 'informativo', 'futuro'])->default('score');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['eixo_id', 'codigo']);
        });
    }
    public function down(): void { Schema::dropIfExists('gdp_indicadores'); }
};
