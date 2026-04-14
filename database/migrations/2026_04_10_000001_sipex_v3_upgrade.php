<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabela de configuracoes do SIPEX (key-value)
        Schema::create('sipex_settings', function (Blueprint $table) {
            $table->id();
            $table->string('chave', 100)->unique();
            $table->text('valor')->nullable();
            $table->string('descricao')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // Seed: modelo padrao
        DB::table('sipex_settings')->insert([
            [
                'chave' => 'modelo_ia',
                'valor' => 'gpt-5.4',
                'descricao' => 'Modelo de IA utilizado para gerar propostas de precificacao',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // 2. Novos campos em pricing_proposals
        Schema::table('pricing_proposals', function (Blueprint $table) {
            $table->string('modelo_ia_utilizado', 50)->nullable()->after('justificativa_ia');
            $table->json('analise_yield')->nullable()->after('modelo_ia_utilizado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sipex_settings');

        Schema::table('pricing_proposals', function (Blueprint $table) {
            $table->dropColumn(['modelo_ia_utilizado', 'analise_yield']);
        });
    }
};
