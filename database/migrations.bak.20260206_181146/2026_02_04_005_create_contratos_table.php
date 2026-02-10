<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contratos')) {
            Schema::create('contratos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('datajuri_id')->unique();
                $table->string('numero', 20)->nullable()->index();
                $table->decimal('valor', 15, 2)->default(0);
                $table->date('data_assinatura')->nullable()->index();
                $table->string('contratante_nome', 255)->nullable();
                $table->unsignedBigInteger('contratante_id_datajuri')->nullable()->index();
                $table->string('proprietario_nome', 150)->nullable();
                $table->unsignedBigInteger('proprietario_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
