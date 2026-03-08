<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('evidentia')->create('evidentia_embeddings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chunk_id');
            $table->string('model', 60);
            $table->unsignedSmallInteger('dims');
            $table->json('vector_json');
            $table->double('norm');
            $table->timestamps();

            $table->index('chunk_id', 'idx_emb_chunk');
            $table->foreign('chunk_id')
                  ->references('id')
                  ->on('evidentia_chunks')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('evidentia')->dropIfExists('evidentia_embeddings');
    }
};
