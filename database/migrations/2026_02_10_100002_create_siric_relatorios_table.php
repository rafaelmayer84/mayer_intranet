<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siric_relatorios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consulta_id');
            $table->longText('markdown')->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->unsignedBigInteger('gerado_por');  // user_id
            $table->timestamps();

            $table->foreign('consulta_id')
                  ->references('id')->on('siric_consultas')
                  ->onDelete('cascade');

            $table->index('consulta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siric_relatorios');
    }
};
