<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdp_acompanhamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('ciclo_id');
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('bimestre')->comment('1=jan-fev, 2=mar-abr, 3=mai-jun');
            $table->json('respostas_json')->nullable()->comment('Chaves estáveis do formulário LimeSurvey');
            $table->enum('status', ['draft', 'submitted', 'validated', 'rejected'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->text('observacoes_validador')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('ciclo_id')->references('id')->on('gdp_ciclos')->onDelete('cascade');
            $table->foreign('validated_by')->references('id')->on('users')->onDelete('set null');

            $table->unique(['user_id', 'ciclo_id', 'ano', 'bimestre'], 'gdp_acomp_unique');
            $table->index(['ciclo_id', 'bimestre', 'status'], 'gdp_acomp_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdp_acompanhamentos');
    }
};
