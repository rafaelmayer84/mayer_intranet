<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('sisrh_rubricas')) return;
        Schema::create('sisrh_rubricas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nome', 100);
            $table->enum('tipo', ['provento', 'desconto']);
            $table->boolean('automatica')->default(false);
            $table->string('formula', 50)->nullable();
            $table->boolean('ativo')->default(true);
            $table->integer('ordem')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('sisrh_rubricas'); }
};
