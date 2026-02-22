<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sisrh_rb_niveis', function (Blueprint $table) {
            $table->id();
            $table->string('nivel', 30)->comment('Junior, Pleno, Senior_I, Senior_II, Senior_III');
            $table->unsignedBigInteger('ciclo_id');
            $table->decimal('valor_rb', 12, 2)->comment('Valor da RB mensal para este nível neste ciclo');
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('ciclo_id')->references('id')->on('gdp_ciclos')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['nivel', 'ciclo_id'], 'sisrh_rb_nivel_ciclo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sisrh_rb_niveis');
    }
};
