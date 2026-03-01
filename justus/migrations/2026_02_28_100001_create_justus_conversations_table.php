<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justus_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title', 255)->nullable();
            $table->enum('type', [
                'analise_estrategica',
                'analise_completa',
                'peca',
                'calculo_prazo',
                'higiene_autos',
            ])->default('analise_estrategica');
            $table->enum('status', ['active', 'archived', 'draft'])->default('active');
            $table->unsignedInteger('total_input_tokens')->default(0);
            $table->unsignedInteger('total_output_tokens')->default(0);
            $table->decimal('total_cost_brl', 10, 4)->default(0);
            $table->unsignedInteger('style_version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('status');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justus_conversations');
    }
};
