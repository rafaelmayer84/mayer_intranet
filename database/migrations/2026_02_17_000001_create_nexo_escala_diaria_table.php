<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexo_escala_diaria', function (Blueprint $table) {
            $table->id();
            $table->date('data')->unique();
            $table->unsignedBigInteger('user_id');
            $table->time('inicio')->default('09:00');
            $table->time('fim')->default('18:00');
            $table->string('observacao', 255)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['data', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexo_escala_diaria');
    }
};
