<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidentia_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jurisprudence_id');
            $table->string('tribunal', 20)->index();
            $table->string('source_db', 50);
            $table->unsignedInteger('chunk_index');
            $table->text('chunk_text');
            $table->char('chunk_hash', 40)->index();
            $table->string('chunk_source', 20)->default('ementa');
            $table->timestamps();

            $table->index(['jurisprudence_id', 'tribunal'], 'idx_chunk_juris_trib');
            $table->index(['source_db', 'jurisprudence_id'], 'idx_chunk_source_juris');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidentia_chunks');
    }
};
