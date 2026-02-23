<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('sisrh_vinculos')) return;
        Schema::create('sisrh_vinculos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('nivel_senioridade', 30)->nullable();
            $table->date('data_inicio_exercicio')->nullable();
            $table->unsignedBigInteger('equipe_id')->nullable();
            $table->boolean('ativo')->default(true);
            $table->text('observacoes')->nullable();
            $table->string('cpf', 20)->nullable();
            $table->string('oab', 30)->nullable();
            $table->string('rg', 30)->nullable();
            $table->string('endereco_rua', 150)->nullable();
            $table->string('endereco_numero', 20)->nullable();
            $table->string('endereco_complemento', 100)->nullable();
            $table->string('endereco_bairro', 80)->nullable();
            $table->string('endereco_cep', 15)->nullable();
            $table->string('endereco_cidade', 80)->nullable();
            $table->string('endereco_estado', 2)->nullable();
            $table->string('nome_pai', 150)->nullable();
            $table->string('nome_mae', 150)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('sisrh_vinculos'); }
};
