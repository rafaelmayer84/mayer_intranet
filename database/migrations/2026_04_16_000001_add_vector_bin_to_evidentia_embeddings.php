<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona coluna vector_bin (BLOB float16) na tabela evidentia_embeddings
 * do banco 'evidentia' (usado por TRF4 e TRT12).
 *
 * Contexto: os bancos shardados emb_tjsc e emb_stj já foram criados com
 * vector_bin. O banco 'evidentia' (legado) ficou com vector_json apenas,
 * causando falha no GenerateEmbeddingsJob ao processar TRF4/TRT12.
 *
 * Após esta migration:
 * - Novos embeddings gravam em vector_bin (mais eficiente, float16).
 * - Embeddings legados permanecem em vector_json (lidos pelo path legado).
 * - searchEmbeddingsLegacy prefere vector_bin quando disponível.
 * - vector_json torna-se nullable (novos registros não precisam preencher).
 */
return new class extends Migration
{
    protected $connection = 'evidentia';

    public function up(): void
    {
        Schema::connection('evidentia')->table('evidentia_embeddings', function (Blueprint $table) {
            // Coluna binária float16: 1536 dims × 2 bytes = 3072 bytes por vetor
            $table->binary('vector_bin')->nullable()->after('vector_json');

            // Torna vector_json nullable — novos registros só usarão vector_bin
            $table->json('vector_json')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('evidentia')->table('evidentia_embeddings', function (Blueprint $table) {
            $table->dropColumn('vector_bin');
            $table->json('vector_json')->nullable(false)->change();
        });
    }
};
