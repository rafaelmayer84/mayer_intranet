<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->id();
            $table->string('chave')->unique();
            $table->text('valor')->nullable();
            $table->string('tipo')->default('string');
            $table->timestamps();
        });
        
        // Inserir configurações padrão
        DB::table('configuracoes')->insert([
            ['chave' => 'peso_financeiro', 'valor' => '30', 'tipo' => 'integer', 'created_at' => now(), 'updated_at' => now()],
            ['chave' => 'peso_clientes', 'valor' => '20', 'tipo' => 'integer', 'created_at' => now(), 'updated_at' => now()],
            ['chave' => 'peso_processos', 'valor' => '25', 'tipo' => 'integer', 'created_at' => now(), 'updated_at' => now()],
            ['chave' => 'peso_aprendizado', 'valor' => '25', 'tipo' => 'integer', 'created_at' => now(), 'updated_at' => now()],
            ['chave' => 'ano_filtro', 'valor' => '2025', 'tipo' => 'integer', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes');
    }
};
