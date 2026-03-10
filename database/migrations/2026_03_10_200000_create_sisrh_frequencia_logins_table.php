<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sisrh_frequencia_logins', function (Blueprint $table) {
            $table->id();
            $table->string('email_datajuri', 150);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->date('data_login');
            $table->time('hora_login');
            $table->string('ip_origem', 45)->nullable();
            $table->string('url_origem', 255)->nullable();
            $table->string('status_login', 30)->default('Êxito'); // Êxito, Senha inválida, Sessão
            $table->integer('minutos_expirar')->nullable();
            $table->dateTime('ultimo_acesso')->nullable();
            $table->string('plataforma', 30)->nullable();
            $table->string('navegador', 80)->nullable();
            $table->unsignedBigInteger('importado_por')->nullable();
            $table->date('semana_referencia')->nullable()->index(); // segunda-feira da semana
            $table->timestamps();

            $table->index(['user_id', 'data_login']);
            $table->index(['data_login', 'email_datajuri']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sisrh_frequencia_logins');
    }
};
