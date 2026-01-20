<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advogado_id')->nullable()->constrained('advogados')->cascadeOnDelete();
            $table->integer('ano');
            $table->integer('mes');
            $table->decimal('meta_faturamento', 15, 2)->default(0);
            $table->decimal('meta_processos', 10, 0)->default(0);
            $table->decimal('meta_atividades', 10, 0)->default(0);
            $table->decimal('meta_horas', 10, 2)->default(0);
            $table->timestamps();
            
            $table->unique(['advogado_id', 'ano', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metas');
    }
};
