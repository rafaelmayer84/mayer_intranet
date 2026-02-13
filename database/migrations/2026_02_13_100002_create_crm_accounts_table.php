<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_accounts', function (Blueprint $table) {
            $table->id();
            // DataJuri link — nullable: se null é prospect, se preenchido é client
            $table->unsignedInteger('datajuri_pessoa_id')->nullable()->unique();
            $table->enum('kind', ['client', 'prospect'])->default('prospect');
            $table->string('name', 255);
            // doc digits only (cpf 11, cnpj 14) — sem máscara
            $table->string('doc_digits', 14)->nullable()->index();
            $table->string('email', 255)->nullable();
            $table->string('phone_e164', 20)->nullable()->index();
            // CRM fields
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->enum('lifecycle', ['onboarding', 'ativo', 'adormecido', 'risco'])->default('ativo');
            $table->tinyInteger('health_score')->nullable()->comment('0-100');
            $table->dateTime('last_touch_at')->nullable();
            $table->dateTime('next_touch_at')->nullable();
            $table->text('tags')->nullable()->comment('JSON array of tags');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('owner_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('kind');
            $table->index('lifecycle');
            $table->index('next_touch_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_accounts');
    }
};
