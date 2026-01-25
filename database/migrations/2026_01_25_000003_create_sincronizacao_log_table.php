<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sincronizacao_log', function (Blueprint $table) {
            $table->id();
            $table->string('fonte'); // datajuri, espocrm, etc
            $table->string('status')->default('pendente'); // pendente, processando, sucesso, erro
            $table->integer('registros')->nullable();
            $table->integer('duracao')->nullable(); // em segundos
            $table->text('mensagem')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('fonte');
            $table->index('status');
            $table->index('created_at');
        });

        // Inserir dados de teste
        DB::table('sincronizacao_log')->insert([
            [
                'fonte' => 'espocrm',
                'status' => 'sucesso',
                'registros' => 186,
                'duracao' => 45,
                'created_at' => now()->subDays(1)->setHour(15)->setMinute(42)->setSecond(6),
                'updated_at' => now()->subDays(1)->setHour(15)->setMinute(42)->setSecond(6),
            ],
            [
                'fonte' => 'espocrm',
                'status' => 'sucesso',
                'registros' => 186,
                'duracao' => 42,
                'created_at' => now()->subDays(1)->setHour(14)->setMinute(17)->setSecond(28),
                'updated_at' => now()->subDays(1)->setHour(14)->setMinute(17)->setSecond(28),
            ],
            [
                'fonte' => 'espocrm',
                'status' => 'sucesso',
                'registros' => 186,
                'duracao' => 38,
                'created_at' => now()->subDays(1)->setHour(0)->setMinute(28)->setSecond(31),
                'updated_at' => now()->subDays(1)->setHour(0)->setMinute(28)->setSecond(31),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sincronizacao_log');
    }
};
