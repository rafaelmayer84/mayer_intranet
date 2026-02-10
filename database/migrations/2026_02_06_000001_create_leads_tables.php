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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('telefone', 20)->index();
            $table->string('contact_id')->nullable()->index();
            $table->string('area_interesse')->nullable();
            $table->string('cidade')->nullable();
            $table->text('resumo_demanda')->nullable();
            $table->string('palavras_chave')->nullable();
            $table->enum('intencao_contratar', ['sim', 'não', 'talvez'])->default('não');
            $table->string('gclid')->nullable();
            $table->string('status')->default('novo'); // novo, contatado, qualificado, convertido, descartado
            $table->string('espocrm_id')->nullable();
            $table->text('erro_processamento')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('data_entrada')->useCurrent();
            $table->timestamps();
            
            // Índices
            $table->index('status');
            $table->index('intencao_contratar');
            $table->index('area_interesse');
            $table->index('data_entrada');
        });

        Schema::create('lead_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->onDelete('cascade');
            $table->enum('direction', ['in', 'out'])->default('in'); // in = cliente, out = bot
            $table->text('message_text')->nullable();
            $table->string('message_type')->default('text'); // text, image, audio, document
            $table->json('raw_data')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            
            $table->index(['lead_id', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_messages');
        Schema::dropIfExists('leads');
    }
};
