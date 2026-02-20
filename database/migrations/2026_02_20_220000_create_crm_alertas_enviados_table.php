<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_alertas_enviados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->string('tipo_alerta', 50)->index();
            $table->unsignedBigInteger('ref_id')->nullable()->comment('ID extra: titulo_id, opp_id, etc');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['account_id', 'tipo_alerta', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_alertas_enviados');
    }
};
