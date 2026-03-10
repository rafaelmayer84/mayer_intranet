<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_home_shortcuts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('posicao')->comment('1-5 ordem do atalho');
            $table->string('modulo_slug', 100);
            $table->timestamps();

            $table->unique(['user_id', 'posicao']);
            $table->unique(['user_id', 'modulo_slug']);
            $table->index('user_id');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_home_shortcuts');
    }
};
