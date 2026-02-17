<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdp_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciclo_id')->constrained('gdp_ciclos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('ano');
            $table->decimal('score_juridico', 7, 2)->default(0);
            $table->decimal('score_financeiro', 7, 2)->default(0);
            $table->decimal('score_desenvolvimento', 7, 2)->default(0);
            $table->decimal('score_atendimento', 7, 2)->default(0);
            $table->decimal('score_total', 7, 2)->default(0);
            $table->unsignedSmallInteger('ranking')->nullable();
            $table->boolean('congelado')->default(false);
            $table->unsignedBigInteger('congelado_por')->nullable();
            $table->timestamp('congelado_em')->nullable();
            $table->timestamps();
            $table->unique(['ciclo_id', 'user_id', 'mes', 'ano'], 'uk_gdp_snapshot');
            $table->foreign('congelado_por')->references('id')->on('users')->nullOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('gdp_snapshots'); }
};
