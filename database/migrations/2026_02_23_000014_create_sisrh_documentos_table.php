<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('sisrh_documentos')) return;
        Schema::create('sisrh_documentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('categoria', 50)->default('geral');
            $table->string('nome_original', 255);
            $table->string('nome_storage', 255);
            $table->string('mime_type', 100)->default('application/pdf');
            $table->unsignedBigInteger('tamanho')->default(0);
            $table->text('descricao')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'categoria']);
        });
    }
    public function down(): void { Schema::dropIfExists('sisrh_documentos'); }
};
