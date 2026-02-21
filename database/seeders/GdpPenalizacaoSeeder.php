<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GdpPenalizacaoSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $eixos = DB::table('gdp_eixos')->pluck('id', 'codigo')->toArray();
        if (empty($eixos)) {
            $this->command->error('gdp_eixos vazia. Execute GdpSeederInaugural primeiro.');
            return;
        }

        $tipos = [
            ['codigo'=>'PEN-J01','eixo_id'=>$eixos['JURIDICO'],'nome'=>'Processo sem movimentacao','descricao'=>'Processo ativo do advogado sem nenhum andamento registrado ha X dias.','gravidade'=>'moderada','pontos_desconto'=>5,'threshold_valor'=>30,'threshold_unidade'=>'dias','fonte_tabela'=>'andamentos_fase + processos'],
            ['codigo'=>'PEN-J02','eixo_id'=>$eixos['JURIDICO'],'nome'=>'Prazo judicial descumprido','descricao'=>'Prazo judicial com data definida foi ultrapassado sem cumprimento registrado.','gravidade'=>'grave','pontos_desconto'=>10,'threshold_valor'=>0,'threshold_unidade'=>'dias','fonte_tabela'=>'fases_processo'],
            ['codigo'=>'PEN-J03','eixo_id'=>$eixos['JURIDICO'],'nome'=>'Processo sem tarefa futura','descricao'=>'Processo ativo sem nenhuma tarefa ou fase agendada para o futuro.','gravidade'=>'leve','pontos_desconto'=>2,'threshold_valor'=>0,'threshold_unidade'=>'ocorrencias','fonte_tabela'=>'fases_processo + processos'],
            ['codigo'=>'PEN-J04','eixo_id'=>$eixos['JURIDICO'],'nome'=>'Prazo fatal sem preparacao','descricao'=>'Audiencia ou prazo fatal nos proximos X dias sem atividade de preparacao registrada.','gravidade'=>'grave','pontos_desconto'=>10,'threshold_valor'=>7,'threshold_unidade'=>'dias','fonte_tabela'=>'fases_processo + atividades_datajuri'],
            ['codigo'=>'PEN-J05','eixo_id'=>$eixos['JURIDICO'],'nome'=>'Publicacao/intimacao nao tratada','descricao'=>'Publicacao ou intimacao registrada sem atividade subsequente do advogado em X horas.','gravidade'=>'grave','pontos_desconto'=>10,'threshold_valor'=>48,'threshold_unidade'=>'horas','fonte_tabela'=>'andamentos_fase + atividades_datajuri'],
            ['codigo'=>'PEN-J06','eixo_id'=>$eixos['JURIDICO'],'nome'=>'OS aberta sem andamento','descricao'=>'Ordem de servico atribuida ao advogado sem nenhum andamento registrado ha X dias.','gravidade'=>'leve','pontos_desconto'=>2,'threshold_valor'=>7,'threshold_unidade'=>'dias','fonte_tabela'=>'ordens_servico + atividades_datajuri'],
            ['codigo'=>'PEN-F01','eixo_id'=>$eixos['FINANCEIRO'],'nome'=>'Inadimplente >15d sem contato','descricao'=>'Cliente com titulo vencido ha mais de X dias sem registro de contato pelo advogado responsavel.','gravidade'=>'moderada','pontos_desconto'=>5,'threshold_valor'=>15,'threshold_unidade'=>'dias','fonte_tabela'=>'contas_receber + wa_messages + atividades_datajuri'],
            ['codigo'=>'PEN-F02','eixo_id'=>$eixos['FINANCEIRO'],'nome'=>'Inadimplente >30d sem acao formal','descricao'=>'Cliente com titulo vencido ha mais de X dias sem acao formal de cobranca.','gravidade'=>'grave','pontos_desconto'=>10,'threshold_valor'=>30,'threshold_unidade'=>'dias','fonte_tabela'=>'contas_receber + atividades_datajuri'],
            ['codigo'=>'PEN-F03','eixo_id'=>$eixos['FINANCEIRO'],'nome'=>'Inadimplencia recorrente sem plano','descricao'=>'Cliente com X+ titulos vencidos em 6 meses sem registro de plano de acao no CRM.','gravidade'=>'grave','pontos_desconto'=>10,'threshold_valor'=>3,'threshold_unidade'=>'ocorrencias','fonte_tabela'=>'contas_receber + crm_activities'],
            ['codigo'=>'PEN-F04','eixo_id'=>$eixos['FINANCEIRO'],'nome'=>'Contrato inconforme sem regularizacao','descricao'=>'Contrato marcado como inconforme/irregular ha mais de X dias sem atividade de regularizacao.','gravidade'=>'moderada','pontos_desconto'=>5,'threshold_valor'=>15,'threshold_unidade'=>'dias','fonte_tabela'=>'contratos + atividades_datajuri'],
            ['codigo'=>'PEN-A01','eixo_id'=>$eixos['ATENDIMENTO'],'nome'=>'WhatsApp sem resposta >4h','descricao'=>'Mensagem WhatsApp incoming sem resposta outgoing na mesma conversa em X horas uteis.','gravidade'=>'moderada','pontos_desconto'=>5,'threshold_valor'=>4,'threshold_unidade'=>'horas','fonte_tabela'=>'wa_messages + wa_conversations'],
            ['codigo'=>'PEN-A02','eixo_id'=>$eixos['ATENDIMENTO'],'nome'=>'Ticket nao tratado em 24h','descricao'=>'Ticket aberto ha mais de X horas sem nenhuma nota do responsavel.','gravidade'=>'moderada','pontos_desconto'=>5,'threshold_valor'=>24,'threshold_unidade'=>'horas','fonte_tabela'=>'nexo_tickets + nexo_ticket_notas'],
            ['codigo'=>'PEN-A03','eixo_id'=>$eixos['ATENDIMENTO'],'nome'=>'Transferencia sem nota','descricao'=>'Conversa transferida sem nota explicativa de contexto.','gravidade'=>'leve','pontos_desconto'=>2,'threshold_valor'=>0,'threshold_unidade'=>'ocorrencias','fonte_tabela'=>'nexo_ticket_notas'],
            ['codigo'=>'PEN-A04','eixo_id'=>$eixos['ATENDIMENTO'],'nome'=>'Bot assumido e abandonado','descricao'=>'Advogado assumiu controle do bot mas nao respondeu ao cliente em X minutos.','gravidade'=>'moderada','pontos_desconto'=>5,'threshold_valor'=>30,'threshold_unidade'=>'minutos','fonte_tabela'=>'wa_conversations + wa_messages'],
            ['codigo'=>'PEN-A05','eixo_id'=>$eixos['ATENDIMENTO'],'nome'=>'Reclamacao sem tratativa em 12h','descricao'=>'Ticket de reclamacao aberto ha mais de X horas sem nota do responsavel.','gravidade'=>'grave','pontos_desconto'=>10,'threshold_valor'=>12,'threshold_unidade'=>'horas','fonte_tabela'=>'nexo_tickets + nexo_ticket_notas'],
            ['codigo'=>'PEN-A06','eixo_id'=>$eixos['ATENDIMENTO'],'nome'=>'Follow-up forcado pelo cliente','descricao'=>'Cliente reenviou mensagem apos X horas sem resposta, forcando follow-up.','gravidade'=>'leve','pontos_desconto'=>2,'threshold_valor'=>24,'threshold_unidade'=>'horas','fonte_tabela'=>'wa_messages'],
            ['codigo'=>'PEN-A07','eixo_id'=>$eixos['ATENDIMENTO'],'nome'=>'Lead qualificado sem follow-up','descricao'=>'Lead qualificado sem registro de atividade no CRM em X horas.','gravidade'=>'moderada','pontos_desconto'=>5,'threshold_valor'=>48,'threshold_unidade'=>'horas','fonte_tabela'=>'leads + crm_activities'],
            ['codigo'=>'PEN-A08','eixo_id'=>$eixos['ATENDIMENTO'],'nome'=>'Oportunidade CRM estagnada','descricao'=>'Oportunidade no CRM parada no mesmo estagio sem atividade ha X dias.','gravidade'=>'leve','pontos_desconto'=>2,'threshold_valor'=>14,'threshold_unidade'=>'dias','fonte_tabela'=>'crm_opportunities + crm_activities'],
            ['codigo'=>'PEN-D01','eixo_id'=>$eixos['DESENVOLVIMENTO'],'nome'=>'Semana sem registro de horas','descricao'=>'Nenhum registro de horas trabalhadas na semana.','gravidade'=>'moderada','pontos_desconto'=>5,'threshold_valor'=>0,'threshold_unidade'=>'ocorrencias','fonte_tabela'=>'horas_trabalhadas_datajuri'],
            ['codigo'=>'PEN-D02','eixo_id'=>$eixos['ATENDIMENTO'],'nome'=>'Aviso prioritario nao lido','descricao'=>'Aviso com prioridade alta/urgente nao lido pelo advogado em X horas.','gravidade'=>'leve','pontos_desconto'=>2,'threshold_valor'=>48,'threshold_unidade'=>'horas','fonte_tabela'=>'avisos + avisos_lidos'],
            ['codigo'=>'PEN-D03','eixo_id'=>$eixos['DESENVOLVIMENTO'],'nome'=>'Acordo GDP nao aceito no prazo','descricao'=>'Acordo de desempenho nao aceito pelo advogado dentro do prazo definido.','gravidade'=>'moderada','pontos_desconto'=>5,'threshold_valor'=>7,'threshold_unidade'=>'dias','fonte_tabela'=>'gdp_snapshots'],
            ['codigo'=>'PEN-D04','eixo_id'=>$eixos['JURIDICO'],'nome'=>'Insucesso sem justificativa','descricao'=>'Processo encerrado com resultado negativo sem nota ou atividade explicativa.','gravidade'=>'leve','pontos_desconto'=>2,'threshold_valor'=>0,'threshold_unidade'=>'ocorrencias','fonte_tabela'=>'processos + atividades_datajuri'],
        ];

        foreach ($tipos as $tipo) {
            DB::table('gdp_penalizacao_tipos')->updateOrInsert(
                ['codigo' => $tipo['codigo']],
                array_merge($tipo, ['created_at' => $now, 'updated_at' => $now])
            );
        }
        $this->command->info('22 tipos de penalizacao inseridos/atualizados.');

        $cicloId = DB::table('gdp_ciclos')->where('status', 'aberto')->value('id');
        if (!$cicloId) {
            $this->command->warn('Nenhum ciclo ativo. Faixas nao inseridas.');
            return;
        }

        $faixas = [
            ['score_min'=>90,'score_max'=>100,'percentual_remuneracao'=>100,'label'=>'Bonus integral'],
            ['score_min'=>80,'score_max'=>89,'percentual_remuneracao'=>85,'label'=>'Desempenho alto'],
            ['score_min'=>70,'score_max'=>79,'percentual_remuneracao'=>70,'label'=>'Desempenho bom'],
            ['score_min'=>60,'score_max'=>69,'percentual_remuneracao'=>50,'label'=>'Desempenho regular'],
            ['score_min'=>50,'score_max'=>59,'percentual_remuneracao'=>30,'label'=>'Desempenho abaixo'],
            ['score_min'=>0,'score_max'=>49,'percentual_remuneracao'=>0,'label'=>'Sem bonus'],
        ];

        foreach ($faixas as $faixa) {
            DB::table('gdp_remuneracao_faixas')->updateOrInsert(
                ['ciclo_id'=>$cicloId,'score_min'=>$faixa['score_min']],
                array_merge($faixa, ['ciclo_id'=>$cicloId,'created_at'=>$now,'updated_at'=>$now])
            );
        }
        $this->command->info('6 faixas de remuneracao inseridas para ciclo ' . $cicloId);
    }
}
