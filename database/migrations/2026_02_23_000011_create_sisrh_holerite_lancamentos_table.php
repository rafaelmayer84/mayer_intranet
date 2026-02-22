<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('sisrh_holerite_lancamentos')) return;
        Schema::create('sisrh_holerite_lancamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->integer('ano');
            $table->integer('mes');
            $table->unsignedBigInteger('rubrica_id');
            $table->string('referencia', 20)->nullable();
            $table->decimal('valor', 15, 2)->default(0);
            $table->text('observacao')->nullable();
            $table->enum('origem', ['manual', 'automatico'])->default('manual');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('rubrica_id')->references('id')->on('sisrh_rubricas')->onDelete('cascade');
            $table->index(['user_id', 'ano', 'mes']);
        });
    }
    public function down(): void { Schema::dropIfExists('sisrh_holerite_lancamentos'); }
};
