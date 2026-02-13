<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_opportunities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('stage_id');
            $table->enum('type', ['aquisicao', 'carteira'])->default('aquisicao');
            $table->string('title', 255);
            $table->string('area', 100)->nullable()->comment('Área do Direito');
            $table->string('source', 100)->nullable()->comment('WhatsApp, Indicação, Site, etc.');
            $table->decimal('value_estimated', 15, 2)->nullable();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->dateTime('next_action_at')->nullable();
            $table->enum('status', ['open', 'won', 'lost'])->default('open');
            $table->string('lost_reason', 255)->nullable();
            $table->dateTime('won_at')->nullable();
            $table->dateTime('lost_at')->nullable();
            // DataJuri links
            $table->unsignedInteger('datajuri_contrato_id')->nullable();
            $table->unsignedInteger('datajuri_processo_id')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('crm_accounts')->cascadeOnDelete();
            $table->foreign('stage_id')->references('id')->on('crm_stages');
            $table->foreign('owner_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('status');
            $table->index('type');
            $table->index('next_action_at');
            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
    }
};
