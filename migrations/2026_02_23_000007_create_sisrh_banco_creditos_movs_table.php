<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sisrh_banco_creditos_movs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->enum('tipo', ['credit', 'debit']);
            $table->decimal('valor', 12, 2);
            $table->unsignedBigInteger('origem_apuracao_id')->nullable();
            $table->text('motivo')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('origem_apuracao_id')->references('id')->on('sisrh_apuracoes')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['user_id', 'tipo'], 'sisrh_banco_user_tipo_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sisrh_banco_creditos_movs');
    }
};
