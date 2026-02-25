<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_owner_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $t->integer('max_accounts')->default(80);
            $t->integer('priority_weight')->default(1); // 1-10, maior = recebe clientes maiores
            $t->json('specialties')->nullable(); // ["empresarial","tributario","civel"]
            $t->text('description')->nullable(); // Descrição livre do perfil
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('crm_distribution_proposals', function (Blueprint $t) {
            $t->id();
            $t->string('status')->default('pending'); // pending, approved, applied, rejected
            $t->json('assignments')->nullable(); // [{account_id, suggested_owner_id, reason, score}...]
            $t->json('summary')->nullable(); // resumo estatístico
            $t->text('ai_reasoning')->nullable(); // raciocínio completo da IA
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->timestamp('applied_at')->nullable();
            $t->timestamps();
        });

        // Fila de revisão para novos clientes
        Schema::create('crm_distribution_queue', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('crm_accounts')->onDelete('cascade');
            $t->foreignId('suggested_owner_id')->nullable()->constrained('users');
            $t->text('reason')->nullable();
            $t->string('status')->default('pending'); // pending, accepted, overridden
            $t->foreignId('decided_by')->nullable()->constrained('users');
            $t->foreignId('final_owner_id')->nullable()->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_distribution_queue');
        Schema::dropIfExists('crm_distribution_proposals');
        Schema::dropIfExists('crm_owner_profiles');
    }
};
