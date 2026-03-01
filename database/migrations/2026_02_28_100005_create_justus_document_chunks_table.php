<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justus_document_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attachment_id');
            $table->unsignedInteger('chunk_index')->default(0);
            $table->unsignedInteger('page_start');
            $table->unsignedInteger('page_end');
            $table->longText('content');
            $table->unsignedInteger('token_estimate')->default(0);
            $table->timestamps();

            $table->index('attachment_id');
        });

        DB::statement('ALTER TABLE justus_document_chunks ADD FULLTEXT INDEX ft_chunk_content (content)');
    }

    public function down(): void
    {
        Schema::dropIfExists('justus_document_chunks');
    }
};
