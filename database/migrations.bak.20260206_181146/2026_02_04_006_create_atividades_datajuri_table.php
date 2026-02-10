<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('atividades_datajuri')) {
            Schema::create('atividades_datajuri', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('datajuri_id')->unique();
                $table->string('status', 50)->nullable()->index();
                $table->datetime('data_hora')->nullable()->index();
                $table->datetime('data_conclusao')->nullable();
                $table->date('data_prazo_fatal')->nullable()->index();
                $table->string('processo_pasta', 50)->nullable()->index();
                $table->unsignedBigInteger('proprietario_id')->nullable()->index();
                $table->boolean('particular')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('atividades_datajuri');
    }
};
