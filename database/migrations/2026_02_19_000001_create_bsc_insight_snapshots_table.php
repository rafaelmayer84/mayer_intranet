<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bsc_insight_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('periodo_inicio');
            $table->date('periodo_fim');
            $table->longText('json_payload');
            $table->string('payload_hash', 64)->index();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('trigger_type', 20)->default('manual');
            $table->timestamps();

            $table->foreign('created_by_user_id')
                  ->references('id')->on('users')
                  ->nullOnDelete();

            $table->index(['periodo_inicio', 'periodo_fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bsc_insight_snapshots');
    }
};
