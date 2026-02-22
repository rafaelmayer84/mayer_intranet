<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sisrh_rb_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('ciclo_id');
            $table->decimal('valor_rb', 12, 2)->comment('Valor RB overridden para este usuário neste ciclo');
            $table->text('motivo')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('ciclo_id')->references('id')->on('gdp_ciclos')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'ciclo_id'], 'sisrh_rb_override_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sisrh_rb_overrides');
    }
};
