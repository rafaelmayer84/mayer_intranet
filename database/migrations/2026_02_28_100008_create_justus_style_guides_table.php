<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justus_style_guides', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version');
            $table->string('name', 255)->default('Manual de Estilo Jurídico Mayer');
            $table->longText('system_prompt');
            $table->boolean('is_active')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique('version');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('justus_style_guides');
    }
};
