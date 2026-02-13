<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_identities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->enum('kind', ['phone', 'email', 'doc', 'datajuri', 'espocrm', 'sendpulse']);
            $table->string('value', 255);
            $table->string('value_norm', 255)->comment('Normalizado: doc=digits, email=lower, phone=E164');
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('crm_accounts')->cascadeOnDelete();
            $table->unique(['kind', 'value_norm']);
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_identities');
    }
};
