<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('justus_document_chunks', function (Blueprint $table) {
            $table->string('embedding_model', 50)->nullable()->after('token_estimate');
        });
        // LONGBLOB via raw SQL (Laravel nao tem helper)
        DB::statement('ALTER TABLE justus_document_chunks ADD COLUMN embedding LONGBLOB NULL AFTER token_estimate');

        Schema::create('justus_rag_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('message_id')->index();
            $table->unsignedBigInteger('chunk_id')->index();
            $table->enum('feedback', ['positive', 'negative']);
            $table->float('score_adjustment')->default(0);
            $table->timestamps();
            $table->foreign('conversation_id')->references('id')->on('justus_conversations')->onDelete('cascade');
            $table->foreign('message_id')->references('id')->on('justus_messages')->onDelete('cascade');
            $table->foreign('chunk_id')->references('id')->on('justus_document_chunks')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('justus_document_chunks', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'embedding_model']);
        });
        Schema::dropIfExists('justus_rag_feedback');
    }
};
