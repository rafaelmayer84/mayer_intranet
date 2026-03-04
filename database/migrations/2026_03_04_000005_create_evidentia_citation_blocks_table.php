<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidentia_citation_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('search_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->longText('sintese_objetiva');
            $table->longText('bloco_precedentes');
            $table->json('jurisprudence_ids_used');
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->timestamps();

            $table->foreign('search_id')
                  ->references('id')
                  ->on('evidentia_searches')
                  ->cascadeOnDelete();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidentia_citation_blocks');
    }
};
