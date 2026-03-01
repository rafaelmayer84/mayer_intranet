<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justus_document_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attachment_id');
            $table->unsignedInteger('page_number');
            $table->longText('text_content');
            $table->unsignedInteger('char_count')->default(0);
            $table->timestamps();

            $table->index('attachment_id');
            $table->unique(['attachment_id', 'page_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justus_document_pages');
    }
};
