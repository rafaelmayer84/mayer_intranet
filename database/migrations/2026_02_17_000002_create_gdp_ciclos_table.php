<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdp_ciclos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 50);
            $table->date('data_inicio');
            $table->date('data_fim');
            $table->enum('status', ['rascunho', 'aberto', 'fechado'])->default('rascunho');
            $table->text('observacao')->nullable();
            $table->unsignedBigInteger('criado_por')->nullable();
            $table->timestamps();
            $table->unique('nome');
            $table->foreign('criado_por')->references('id')->on('users')->nullOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('gdp_ciclos'); }
};
