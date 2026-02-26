<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_service_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('category', 60);
            $table->string('subject');
            $table->text('description');
            $table->enum('priority', ['baixa', 'normal', 'alta', 'urgente'])->default('normal');
            $table->enum('status', ['aberto', 'em_andamento', 'aguardando_aprovacao', 'aprovado', 'rejeitado', 'concluido', 'cancelado'])->default('aberto');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->text('resolution_notes')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->index('status');
            $table->index('assigned_to_user_id');
            $table->index('requested_by_user_id');
        });

        Schema::create('crm_service_request_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_request_id');
            $table->unsignedBigInteger('user_id');
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index('service_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_service_request_comments');
        Schema::dropIfExists('crm_service_requests');
    }
};
