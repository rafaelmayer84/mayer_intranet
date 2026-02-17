<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Eval180QuestionsSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            // ── Seção 1: Cumprimento das Diretrizes e Processos Internos ──
            ['section' => 1, 'number' => '1.1', 'text' => 'Cumpre os prazos internos e respeita a organização processual conforme os padrões da sociedade?'],
            ['section' => 1, 'number' => '1.2', 'text' => 'Segue corretamente os protocolos de atendimento ao cliente?'],
            ['section' => 1, 'number' => '1.3', 'text' => 'Observa as normas de precificação e concessão de honorários?'],
            ['section' => 1, 'number' => '1.4', 'text' => 'Mantém a organização e atualização da carteira de processos?'],
            ['section' => 1, 'number' => '1.5', 'text' => 'Demonstra comprometimento com a cultura organizacional?'],

            // ── Seção 2: Evolução Profissional e Adaptação ao Escritório ──
            ['section' => 2, 'number' => '2.1', 'text' => 'Demonstra evolução técnica e conhecimento aprofundado?'],
            ['section' => 2, 'number' => '2.2', 'text' => 'Apresenta melhoria na autonomia profissional?'],
            ['section' => 2, 'number' => '2.3', 'text' => 'Demonstra interesse e iniciativa para buscar novos conhecimentos?'],
            ['section' => 2, 'number' => '2.4', 'text' => 'Consegue gerir suas responsabilidades de forma eficiente?'],
            ['section' => 2, 'number' => '2.5', 'text' => 'Demonstra capacidade de resolver problemas de forma independente?'],

            // ── Seção 3: Compromisso com Metas e Resultados ──
            ['section' => 3, 'number' => '3.1', 'text' => 'Mantém uma produtividade consistente?'],
            ['section' => 3, 'number' => '3.2', 'text' => 'Contribui para a retenção e fidelização de clientes?'],
            ['section' => 3, 'number' => '3.3', 'text' => 'Cumpre as metas individuais e do escritório?'],
            ['section' => 3, 'number' => '3.4', 'text' => 'Demonstra proatividade na busca de soluções?'],
            ['section' => 3, 'number' => '3.5', 'text' => 'Contribui para a eficiência e sustentabilidade financeira?'],

            // ── Seção 4: Relacionamento Profissional e Adesão à Cultura do Escritório ──
            ['section' => 4, 'number' => '4.1', 'text' => 'Mantém um bom relacionamento interpessoal?'],
            ['section' => 4, 'number' => '4.2', 'text' => 'Segue as diretrizes de comunicação interna?'],
            ['section' => 4, 'number' => '4.3', 'text' => 'Demonstra compromisso com a cultura do escritório?'],
            ['section' => 4, 'number' => '4.4', 'text' => 'Aceita feedbacks de forma construtiva?'],
            ['section' => 4, 'number' => '4.5', 'text' => 'Contribui para o crescimento do escritório?'],
        ];

        $sectionNames = [
            1 => 'Cumprimento das Diretrizes e Processos Internos',
            2 => 'Evolução Profissional e Adaptação ao Escritório',
            3 => 'Compromisso com Metas e Resultados',
            4 => 'Relacionamento Profissional e Adesão à Cultura do Escritório',
        ];

        // Configuração padrão do Eval 180
        $configKey = 'gdp_eval180_config';
        $configExists = DB::table('configuracoes')->where('chave', $configKey)->exists();

        if (!$configExists) {
            DB::table('configuracoes')->insert([
                'chave' => $configKey,
                'valor' => json_encode([
                    'sections' => $sectionNames,
                    'questions' => $questions,
                    'section_weights' => [1 => 25, 2 => 25, 3 => 25, 4 => 25],
                    'periodicidade' => 'configuravel', // mensal | trimestral | configuravel
                    'piso_qualidade' => 3.0,
                    'evidencia_trigger_min' => 2,
                    'evidencia_trigger_max' => 5,
                    'action_required_threshold' => 3.0,
                    'max_action_items' => 3,
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "✅ Configuração Eval 180 inserida na tabela configuracoes\n";
        } else {
            echo "⏭️  Configuração Eval 180 já existe, pulando\n";
        }

        echo "✅ Seeder Eval180 concluído — " . count($questions) . " perguntas em " . count($sectionNames) . " seções\n";
    }
}
