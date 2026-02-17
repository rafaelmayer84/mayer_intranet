<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdp_eixos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciclo_id')->constrained('gdp_ciclos')->cascadeOnDelete();
            $table->string('codigo', 30);
            $table->string('nome', 100);
            $table->decimal('peso', 5, 2);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
            $table->unique(['ciclo_id', 'codigo']);
        });
    }
    public function down(): void { Schema::dropIfExists('gdp_eixos'); }
};
