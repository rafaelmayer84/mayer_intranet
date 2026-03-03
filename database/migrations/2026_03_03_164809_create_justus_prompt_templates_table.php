<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justus_prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->index();
            $table->string('label', 100);
            $table->string('description', 255)->nullable();
            $table->string('mode', 30)->default('assessor');
            $table->string('type', 30)->default('analise_completa');
            $table->text('prompt_text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justus_prompt_templates');
    }
};
