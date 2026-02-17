<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdp_validacao_financeira', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ciclo_id')->constrained('gdp_ciclos')->cascadeOnDelete();
            $table->unsignedTinyInteger('mes');
            $table->unsignedSmallInteger('ano');
            $table->unsignedBigInteger('movimento_id');
            $table->unsignedBigInteger('movimento_datajuri_id')->nullable();
            $table->unsignedBigInteger('proprietario_id_original')->nullable();
            $table->unsignedBigInteger('user_id_resolvido')->nullable();
            $table->unsignedBigInteger('user_id_override')->nullable();
            $table->string('classificacao', 30)->nullable();
            $table->decimal('valor', 15, 2);
            $table->string('descricao', 500)->nullable();
            $table->enum('status_pontuacao', ['pontuavel', 'validacao', 'informativo'])->default('validacao');
            $table->enum('vinculo_tipo', ['contrato', 'processo', 'manual', 'sem_vinculo'])->default('sem_vinculo');
            $table->text('justificativa')->nullable();
            $table->unsignedBigInteger('validado_por')->nullable();
            $table->timestamp('validado_em')->nullable();
            $table->timestamps();
            $table->foreign('movimento_id')->references('id')->on('movimentos')->cascadeOnDelete();
            $table->foreign('user_id_resolvido')->references('id')->on('users')->nullOnDelete();
            $table->foreign('user_id_override')->references('id')->on('users')->nullOnDelete();
            $table->foreign('validado_por')->references('id')->on('users')->nullOnDelete();
            $table->index(['ciclo_id', 'mes', 'ano', 'status_pontuacao'], 'idx_gdp_valid_fin_busca');
        });
    }
    public function down(): void { Schema::dropIfExists('gdp_validacao_financeira'); }
};
