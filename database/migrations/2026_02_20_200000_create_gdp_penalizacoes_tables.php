<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdp_penalizacao_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->unsignedBigInteger('eixo_id');
            $table->string('nome', 100);
            $table->text('descricao');
            $table->enum('gravidade', ['leve', 'moderada', 'grave']);
            $table->unsignedTinyInteger('pontos_desconto');
            $table->unsignedSmallInteger('threshold_valor');
            $table->enum('threshold_unidade', ['dias', 'horas', 'minutos', 'ocorrencias']);
            $table->string('fonte_tabela', 100)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->foreign('eixo_id')->references('id')->on('gdp_eixos')->onDelete('cascade');
        });

        Schema::create('gdp_penalizacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ciclo_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('tipo_id');
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('pontos_desconto');
            $table->text('descricao_automatica');
            $table->string('referencia_tipo', 50)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->boolean('automatica')->default(true);
            $table->boolean('contestada')->default(false);
            $table->text('contestacao_texto')->nullable();
            $table->enum('contestacao_status', ['pendente', 'aceita', 'rejeitada'])->nullable();
            $table->unsignedBigInteger('contestacao_por')->nullable();
            $table->timestamp('contestacao_em')->nullable();
            $table->timestamps();
            $table->foreign('ciclo_id')->references('id')->on('gdp_ciclos')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('tipo_id')->references('id')->on('gdp_penalizacao_tipos')->onDelete('cascade');
            $table->foreign('contestacao_por')->references('id')->on('users')->onDelete('set null');
            $table->unique(['ciclo_id','user_id','tipo_id','mes','ano','referencia_tipo','referencia_id'], 'gdp_pen_unique_occurrence');
            $table->index(['user_id','mes','ano'], 'gdp_pen_user_month');
            $table->index(['contestada','contestacao_status'], 'gdp_pen_contestacao');
        });

        Schema::create('gdp_penalizacao_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ciclo_id');
            $table->unsignedBigInteger('tipo_id');
            $table->unsignedSmallInteger('threshold_valor')->nullable();
            $table->unsignedTinyInteger('pontos_desconto')->nullable();
            $table->boolean('ativo')->nullable();
            $table->timestamps();
            $table->foreign('ciclo_id')->references('id')->on('gdp_ciclos')->onDelete('cascade');
            $table->foreign('tipo_id')->references('id')->on('gdp_penalizacao_tipos')->onDelete('cascade');
            $table->unique(['ciclo_id','tipo_id'], 'gdp_pen_config_unique');
        });

        Schema::create('gdp_remuneracao_faixas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ciclo_id');
            $table->unsignedTinyInteger('score_min');
            $table->unsignedTinyInteger('score_max');
            $table->unsignedTinyInteger('percentual_remuneracao');
            $table->string('label', 50)->nullable();
            $table->timestamps();
            $table->foreign('ciclo_id')->references('id')->on('gdp_ciclos')->onDelete('cascade');
            $table->index(['ciclo_id','score_min','score_max'], 'gdp_rem_faixa_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdp_remuneracao_faixas');
        Schema::dropIfExists('gdp_penalizacao_config');
        Schema::dropIfExists('gdp_penalizacoes');
        Schema::dropIfExists('gdp_penalizacao_tipos');
    }
};
