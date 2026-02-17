<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gdp_audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('entidade', 50);
            $table->unsignedBigInteger('entidade_id');
            $table->string('campo', 100);
            $table->text('valor_anterior')->nullable();
            $table->text('valor_novo')->nullable();
            $table->text('justificativa')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entidade', 'entidade_id'], 'idx_gdp_audit_entidade');
            $table->index('created_at', 'idx_gdp_audit_data');
        });
    }
    public function down(): void { Schema::dropIfExists('gdp_audit_log'); }
};
