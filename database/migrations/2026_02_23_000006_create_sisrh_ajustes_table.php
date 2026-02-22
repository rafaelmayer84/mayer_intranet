<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sisrh_ajustes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('apuracao_id');
            $table->enum('tipo', ['bonus', 'desconto', 'correcao', 'estorno']);
            $table->decimal('valor', 12, 2);
            $table->text('motivo');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('apuracao_id')->references('id')->on('sisrh_apuracoes')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index('apuracao_id', 'sisrh_ajustes_apuracao_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sisrh_ajustes');
    }
};
