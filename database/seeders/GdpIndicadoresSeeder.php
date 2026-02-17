<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GdpIndicadoresSeeder extends Seeder
{
    public function run(): void
    {
        $cicloId = DB::table('gdp_ciclos')->insertGetId([
            'nome' => '2026-S1', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-06-30',
            'status' => 'aberto', 'observacao' => 'Primeiro ciclo GDP', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $eixos = [
            ['codigo' => 'JURIDICO',       'nome' => 'Juridico',                'peso' => 30.00, 'ordem' => 1],
            ['codigo' => 'FINANCEIRO',      'nome' => 'Financeiro',              'peso' => 30.00, 'ordem' => 2],
            ['codigo' => 'DESENVOLVIMENTO', 'nome' => 'Desenvolvimento Pessoal', 'peso' => 20.00, 'ordem' => 3],
            ['codigo' => 'ATENDIMENTO',     'nome' => 'Atendimento',             'peso' => 20.00, 'ordem' => 4],
        ];

        $eixoIds = [];
        foreach ($eixos as $e) {
            $eixoIds[$e['codigo']] = DB::table('gdp_eixos')->insertGetId(
                array_merge($e, ['ciclo_id' => $cicloId, 'created_at' => now(), 'updated_at' => now()])
            );
        }

        $inds = [
            ['eixo'=>'JURIDICO','codigo'=>'J1','nome'=>'Processos ativos na carteira','descricao'=>'Quantidade de processos com status Ativo vinculados ao advogado','chave_atribuicao'=>'proprietario','chave_fallback'=>null,'fonte_dados'=>'processos','unidade'=>'numero','direcao'=>'maior_melhor','peso'=>25.00,'status_v1'=>'score','ordem'=>1],
            ['eixo'=>'JURIDICO','codigo'=>'J2','nome'=>'Novos processos abertos no mes','descricao'=>'Processos com dataAbertura dentro do mes','chave_atribuicao'=>'proprietario','chave_fallback'=>null,'fonte_dados'=>'processos','unidade'=>'numero','direcao'=>'maior_melhor','peso'=>25.00,'status_v1'=>'score','ordem'=>2],
            ['eixo'=>'JURIDICO','codigo'=>'J3','nome'=>'Processos encerrados com exito','descricao'=>'Processos encerrados no mes com ganhoCausa=Sim','chave_atribuicao'=>'proprietario','chave_fallback'=>null,'fonte_dados'=>'processos','unidade'=>'numero','direcao'=>'maior_melhor','peso'=>25.00,'status_v1'=>'score','ordem'=>3],
            ['eixo'=>'JURIDICO','codigo'=>'J4','nome'=>'Pontualidade em prazos fatais','descricao'=>'% de atividades com prazo fatal concluidas antes do vencimento','chave_atribuicao'=>'advogado_atuante','chave_fallback'=>'proprietario','fonte_dados'=>'atividades_datajuri','unidade'=>'percentual','direcao'=>'maior_melhor','peso'=>25.00,'status_v1'=>'score','ordem'=>4],
            ['eixo'=>'FINANCEIRO','codigo'=>'F1','nome'=>'Receita pontuavel realizada','descricao'=>'Movimentos com vinculo forte + natureza valida (regra 3 camadas)','chave_atribuicao'=>'proprietario','chave_fallback'=>null,'fonte_dados'=>'gdp_validacao_financeira','unidade'=>'reais','direcao'=>'maior_melhor','peso'=>40.00,'status_v1'=>'score','ordem'=>1],
            ['eixo'=>'FINANCEIRO','codigo'=>'F2','nome'=>'Contratos novos fechados (qtd)','descricao'=>'Contratos com dataAssinatura dentro do mes','chave_atribuicao'=>'proprietario','chave_fallback'=>null,'fonte_dados'=>'contratos','unidade'=>'numero','direcao'=>'maior_melhor','peso'=>30.00,'status_v1'=>'score','ordem'=>2],
            ['eixo'=>'FINANCEIRO','codigo'=>'F3','nome'=>'Valor total contratos fechados','descricao'=>'Soma do valor dos contratos novos no mes','chave_atribuicao'=>'proprietario','chave_fallback'=>null,'fonte_dados'=>'contratos','unidade'=>'reais','direcao'=>'maior_melhor','peso'=>30.00,'status_v1'=>'score','ordem'=>3],
            ['eixo'=>'DESENVOLVIMENTO','codigo'=>'D1','nome'=>'Horas trabalhadas registradas','descricao'=>'Total de horas registradas no mes','chave_atribuicao'=>'proprietario','chave_fallback'=>null,'fonte_dados'=>'horas_trabalhadas_datajuri','unidade'=>'horas','direcao'=>'maior_melhor','peso'=>50.00,'status_v1'=>'score','ordem'=>1],
            ['eixo'=>'DESENVOLVIMENTO','codigo'=>'D2','nome'=>'Aderencia ao registro de horas','descricao'=>'% de dias uteis com pelo menos 1 registro','chave_atribuicao'=>'proprietario','chave_fallback'=>null,'fonte_dados'=>'horas_trabalhadas_datajuri','unidade'=>'percentual','direcao'=>'maior_melhor','peso'=>50.00,'status_v1'=>'score','ordem'=>2],
            ['eixo'=>'ATENDIMENTO','codigo'=>'A1','nome'=>'Tempo medio 1a resposta WhatsApp','descricao'=>'Media de minutos entre incoming e outgoing por conversa atribuida','chave_atribuicao'=>'user_nexo','chave_fallback'=>null,'fonte_dados'=>'wa_conversations,wa_messages','unidade'=>'minutos','direcao'=>'menor_melhor','peso'=>25.00,'status_v1'=>'score','ordem'=>1],
            ['eixo'=>'ATENDIMENTO','codigo'=>'A2','nome'=>'Taxa de resposta WhatsApp','descricao'=>'% de conversas atribuidas com pelo menos 1 resposta','chave_atribuicao'=>'user_nexo','chave_fallback'=>null,'fonte_dados'=>'wa_conversations,wa_messages','unidade'=>'percentual','direcao'=>'maior_melhor','peso'=>25.00,'status_v1'=>'score','ordem'=>2],
            ['eixo'=>'ATENDIMENTO','codigo'=>'A3','nome'=>'Conversas sem resposta >24h','descricao'=>'Conversas atribuidas sem resposta por mais de 24h','chave_atribuicao'=>'user_nexo','chave_fallback'=>null,'fonte_dados'=>'wa_conversations,wa_messages','unidade'=>'numero','direcao'=>'menor_melhor','peso'=>15.00,'status_v1'=>'score','ordem'=>3],
            ['eixo'=>'ATENDIMENTO','codigo'=>'A4','nome'=>'Tickets resolvidos no mes','descricao'=>'Tickets com status resolvido pelo responsavel','chave_atribuicao'=>'user_nexo','chave_fallback'=>null,'fonte_dados'=>'nexo_tickets','unidade'=>'numero','direcao'=>'maior_melhor','peso'=>20.00,'status_v1'=>'score','ordem'=>4],
            ['eixo'=>'ATENDIMENTO','codigo'=>'A5','nome'=>'Tempo medio resolucao tickets','descricao'=>'Media de horas entre abertura e resolucao','chave_atribuicao'=>'user_nexo','chave_fallback'=>null,'fonte_dados'=>'nexo_tickets','unidade'=>'horas','direcao'=>'menor_melhor','peso'=>15.00,'status_v1'=>'score','ordem'=>5],
        ];

        foreach ($inds as $i) {
            $eixoCod = $i['eixo']; unset($i['eixo']);
            DB::table('gdp_indicadores')->insert(array_merge($i, [
                'eixo_id' => $eixoIds[$eixoCod], 'cap_percentual' => 120.00, 'ativo' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }
    }
}
