<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexo_public_tokens', function (Blueprint $table) {
            $table->id();
            $table->char('token', 36)->unique();
            $table->string('tipo', 30);                     // financeiro | processo-judicial | processo-admin | compromissos | tickets
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->string('telefone', 20);
            $table->json('payload');                        // snapshot dos dados
            $table->timestamp('expires_at');                // now() + 6h
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->index('expires_at', 'idx_expires');
            $table->index(['telefone', 'tipo'], 'idx_telefone_tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexo_public_tokens');
    }
};
