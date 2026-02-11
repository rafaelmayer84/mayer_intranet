<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_calibrations', function (Blueprint $table) {
            $table->id();
            $table->string('eixo', 50)->unique()->comment('Identificador do eixo estratégico');
            $table->string('label', 100)->comment('Nome exibido na UI');
            $table->string('descricao', 500)->nullable()->comment('Tooltip explicativo');
            $table->string('label_min', 50)->comment('Label extremo esquerdo do slider');
            $table->string('label_max', 50)->comment('Label extremo direito do slider');
            $table->integer('valor')->default(50)->comment('Valor 0-100 do slider');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        // Seed dos 7 eixos fixos
        DB::table('pricing_calibrations')->insert([
            [
                'eixo' => 'posicionamento_preco',
                'label' => 'Posicionamento de Preço',
                'descricao' => 'Define se o escritório se posiciona com preços mais acessíveis ou premium no mercado',
                'label_min' => 'Acessível',
                'label_max' => 'Premium',
                'valor' => 60,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'eixo' => 'meta_vs_margem',
                'label' => 'Prioridade: Meta vs Margem',
                'descricao' => 'Equilibra entre atingir metas de faturamento e preservar margens de lucro',
                'label_min' => 'Foco em Meta',
                'label_max' => 'Foco em Margem',
                'valor' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'eixo' => 'apetite_risco',
                'label' => 'Apetite a Risco',
                'descricao' => 'Tolerância a aceitar casos com maior incerteza de resultado ou pagamento',
                'label_min' => 'Conservador',
                'label_max' => 'Agressivo',
                'valor' => 40,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'eixo' => 'volume_vs_seletividade',
                'label' => 'Volume vs Seletividade',
                'descricao' => 'Estratégia de captação: muitos casos ou poucos casos de alto valor',
                'label_min' => 'Alto Volume',
                'label_max' => 'Alta Seletividade',
                'valor' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'eixo' => 'politica_desconto',
                'label' => 'Política de Desconto',
                'descricao' => 'Flexibilidade para conceder descontos na negociação',
                'label_min' => 'Restritiva',
                'label_max' => 'Flexível',
                'valor' => 40,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'eixo' => 'peso_historico',
                'label' => 'Peso do Histórico Interno',
                'descricao' => 'Quanto o histórico de casos similares do escritório influencia o preço',
                'label_min' => 'Baixo Peso',
                'label_max' => 'Alto Peso',
                'valor' => 70,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'eixo' => 'peso_perfil_lead',
                'label' => 'Peso do Perfil do Lead',
                'descricao' => 'Quanto o perfil financeiro e comportamental do lead influencia o preço',
                'label_min' => 'Baixo Peso',
                'label_max' => 'Alto Peso',
                'valor' => 60,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_calibrations');
    }
};
