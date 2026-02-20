<?php

namespace App\Services\BscInsights;

use App\Models\BscInsightCard;
use App\Models\AiRun;
use App\Models\BscInsightSnapshot;
use Illuminate\Support\Facades\Log;

class DemoCardGenerator
{
    public function generate(BscInsightSnapshot $snapshot, ?int $userId = null): array
    {
        $run = AiRun::create([
            'feature'            => 'bsc_insights',
            'snapshot_id'        => $snapshot->id,
            'model'              => 'demo-mode',
            'status'             => 'processing',
            'input_tokens'       => 0,
            'output_tokens'      => 0,
            'total_tokens'       => 0,
            'estimated_cost_usd' => 0,
        ]);

        $payload = is_array($snapshot->payload) ? $snapshot->payload : json_decode($snapshot->payload, true);
        $cards = [];
        $cardDefs = $this->buildCards($payload);

        foreach ($cardDefs as $def) {
            $cards[] = BscInsightCard::create([
                'run_id'             => $run->id,
                'snapshot_id'        => $run->snapshot_id,
                'universo'           => $def['universo'],
                'severidade'         => $def['severidade'],
                'confidence'         => $def['confidence'],
                'title'              => $def['title'],
                'what_changed'       => $def['what_changed'],
                'why_it_matters'     => $def['why_it_matters'],
                'evidences_json'     => json_encode($def['evidence_data'] ?? []),
                'recommendation'     => $def['recommendation'],
                'next_step'          => $def['next_step'],
                'questions_json'     => json_encode($def['questions'] ?? []),
                'dependencies_json'  => json_encode($def['dependencies'] ?? []),
                'evidence_keys_json' => json_encode($def['evidence_keys'] ?? []),
                'impact_score'       => $def['impact_score'],
            ]);
        }

        $summary = $this->buildSummary($payload);

        $run->update([
            'status'             => 'success',
            'model'              => 'demo-mode',
            'input_tokens'       => 0,
            'output_tokens'      => 0,
            'total_tokens'       => 0,
            'estimated_cost_usd' => 0,
        ]);

        Log::info("BSC Insights DEMO: " . count($cards) . " cards gerados");

        return ['cards' => $cards, 'summary' => $summary, 'run' => $run];
    }

    private function lastVal(array $monthlyDict): float
    {
        if (empty($monthlyDict)) return 0;
        return (float) end($monthlyDict);
    }

    private function lastKey(array $monthlyDict): string
    {
        if (empty($monthlyDict)) return '';
        $keys = array_keys($monthlyDict);
        return end($keys);
    }

    private function buildCards(array $s): array
    {
        $cards = [];

        // === FINANCEIRO ===
        $fin  = $s['finance'] ?? [];
        $inad = $s['inadimplência'] ?? [];

        $receitaTotalMensal = $fin['receita_total_mensal'] ?? [];
        $despesasMensal     = $fin['despesas_mensal'] ?? [];
        $resultadoMensal    = $fin['resultado_mensal'] ?? [];

        $receitaTotal  = $this->lastVal($receitaTotalMensal);
        $despesaTotal  = $this->lastVal($despesasMensal);
        $resultado     = $this->lastVal($resultadoMensal);
        $margemLiq     = $fin['margem_liquida_pct'] ?? ($receitaTotal > 0 ? round(($resultado / $receitaTotal) * 100, 1) : 0);
        $varReceita    = $fin['var_pct']['receita_total'] ?? 0;
        $mixPf         = $fin['mix_pf_pj']['pf_pct'] ?? 0;
        $mixPj         = $fin['mix_pf_pj']['pj_pct'] ?? 0;
        $inadTotal     = $inad['total_vencido'] ?? 0;
        $inadQtd       = $inad['qtd_vencidas'] ?? 0;
        $inadDias      = $inad['dias_medio_atraso'] ?? 0;

        // Card: Inadimplência
        if ($inadTotal > 0) {
            $cards[] = [
                'universo'     => 'FINANCEIRO',
                'severidade'   => $inadTotal > 500000 ? 'CRITICO' : 'ATENCAO',
                'confidence'   => 92,
                'title'        => $inadTotal > 500000
                    ? 'Inadimplência de R$ ' . number_format($inadTotal, 0, ',', '.') . ' exige ação imediata'
                    : 'Inadimplência de R$ ' . number_format($inadTotal, 0, ',', '.') . ' requer atencao',
                'what_changed' => 'São ' . $inadQtd . ' títulos vencidos com atraso médio de ' . $inadDias . ' dias. Cada mês sem cobrança ativa reduz a probabilidade de recuperação.',
                'why_it_matters' => 'Inadimplência prolongada compromete o fluxo de caixa e pode inviabilizar investimentos planejados.',
                'evidence_data' => [
                    ['metric' => 'Total vencido', 'value' => 'R$ ' . number_format($inadTotal, 0, ',', '.')],
                    ['metric' => 'Titulos vencidos', 'value' => $inadQtd . ' títulos'],
                    ['metric' => 'Atraso medio', 'value' => $inadDias . ' dias'],
                ],
                'recommendation' => 'Priorizar cobrança dos 10 maiores devedores esta semana',
                'next_step'      => 'Gerar relatório de aging e agendar reunião com equipe de cobrança',
                'questions'      => ['Quais clientes tem mais de 180 dias de atraso?', 'Há acordos de pagamento em andamento?'],
                'evidence_keys'  => ['inadimplência.total_vencido', 'inadimplência.qtd_vencidas'],
                'impact_score'   => $inadTotal > 500000 ? 9 : 6,
            ];
        }

        // Card: Resultado / Margem + tendência
        if ($receitaTotal > 0) {
            $tendência = $varReceita < -20 ? 'queda acentuada' : ($varReceita < 0 ? 'queda' : 'crescimento');
            $sev = $varReceita < -20 ? 'ATENCAO' : 'INFO';
            $cards[] = [
                'universo'     => 'FINANCEIRO',
                'severidade'   => $sev,
                'confidence'   => 85,
                'title'        => 'Receita em ' . $tendência . ' (' . ($varReceita > 0 ? '+' : '') . $varReceita . '% vs mes anterior)',
                'what_changed' => 'Receita de R$ ' . number_format($receitaTotal, 0, ',', '.') . ' com despesas de R$ ' . number_format($despesaTotal, 0, ',', '.') . '. Resultado: R$ ' . number_format($resultado, 0, ',', '.') . '.',
                'why_it_matters' => 'A tendência de receita é o principal indicador antecedente de saúde financeira.',
                'evidence_data' => [
                    ['metric' => 'Receita', 'value' => 'R$ ' . number_format($receitaTotal, 0, ',', '.')],
                    ['metric' => 'Despesas', 'value' => 'R$ ' . number_format($despesaTotal, 0, ',', '.')],
                    ['metric' => 'Variacao', 'value' => ($varReceita > 0 ? '+' : '') . $varReceita . '%'],
                ],
                'recommendation' => $varReceita < -20
                    ? 'Revisar os 5 maiores centros de custo e acelerar cobranças pendentes'
                    : 'Manter acompanhamento mensal e avaliar investimento em captação',
                'next_step'      => 'Comparar despesas dos ultimos 3 meses para identificar tendência',
                'evidence_keys'  => ['finance.receita_total_mensal', 'finance.var_pct'],
                'impact_score'   => $varReceita < -20 ? 7 : 4,
            ];
        }

        // Card: Mix PF/PJ
        if ($mixPf > 0 || $mixPj > 0) {
            $desequilibrado = abs($mixPf - $mixPj) > 30;
            $cards[] = [
                'universo'     => 'FINANCEIRO',
                'severidade'   => $desequilibrado ? 'ATENCAO' : 'INFO',
                'confidence'   => 78,
                'title'        => $desequilibrado
                    ? 'Concentração de receita em ' . ($mixPj > $mixPf ? 'PJ' : 'PF') . ' (' . max($mixPf, $mixPj) . '%) representa risco'
                    : 'Mix PF/PJ equilibrado — boa diversificação',
                'what_changed' => 'Receita distribuida em ' . $mixPf . '% PF e ' . $mixPj . '% PJ. ' . ($desequilibrado ? 'Alta concentração aumenta exposicao a ciclos economicos.' : 'Distribuição saudável reduz risco.'),
                'why_it_matters' => 'Diversificação de receita protege contra oscilações de mercado.',
                'evidence_data' => [
                    ['metric' => 'PF', 'value' => $mixPf . '%'],
                    ['metric' => 'PJ', 'value' => $mixPj . '%'],
                ],
                'recommendation' => $desequilibrado
                    ? 'Criar campanha de captação para segmento ' . ($mixPf < $mixPj ? 'PF' : 'PJ')
                    : 'Manter estratégia atual de captação balanceada',
                'next_step'      => 'Analisar ticket medio por segmento para otimizar esforço comercial',
                'evidence_keys'  => ['finance.mix_pf_pj'],
                'impact_score'   => $desequilibrado ? 5 : 3,
            ];
        }

        // === CLIENTES & MERCADO ===
        $cli   = $s['clientes'] ?? [];
        $leads = $s['leads'] ?? [];
        $crm   = $s['crm'] ?? [];

        $totalClientes  = $cli['total_clientes'] ?? 0;
        $novosCliMensal = $cli['novos_clientes_mensal'] ?? [];
        $novosUltimoMes = $this->lastVal($novosCliMensal);

        $totalLeads     = $leads['total'] ?? 0;
        $leadsMensal    = $leads['leads_mensal'] ?? [];
        $leadsUltimo    = $this->lastVal($leadsMensal);

        $winRate     = $crm['win_rate'] ?? 0;
        $opOpen      = $crm['oportunidades']['open'] ?? ($crm['oportunidades_abertas'] ?? 0);
        $pipelineVal = $crm['valor_pipeline'] ?? 0;

        // Card: Pipeline CRM
        if ($opOpen > 0 || $winRate > 0) {
            $cards[] = [
                'universo'     => 'CLIENTES_MERCADO',
                'severidade'   => $winRate < 30 ? 'ATENCAO' : 'INFO',
                'confidence'   => 80,
                'title'        => $winRate < 30
                    ? 'Taxa de conversão de ' . $winRate . '% indica gargalo comercial'
                    : $opOpen . ' oportunidades abertas com taxa de ' . $winRate . '% de conversão',
                'what_changed' => 'O pipeline tem ' . $opOpen . ' oportunidades ativas. ' . ($winRate < 30 ? 'Menos de 1 em 3 se converte em contrato.' : 'Conversao de ' . $winRate . '%, compatível com o mercado.'),
                'why_it_matters' => 'Cada ponto de melhoria na conversão representa receita adicional sem custo de captação.',
                'evidence_data' => [
                    ['metric' => 'Oportunidades abertas', 'value' => $opOpen],
                    ['metric' => 'Win rate', 'value' => $winRate . '%'],
                    ['metric' => 'Pipeline', 'value' => 'R$ ' . number_format($pipelineVal, 0, ',', '.')],
                ],
                'recommendation' => $winRate < 30 ? 'Implementar follow-up estruturado nas oportunidades paradas ha mais de 7 dias' : 'Focar em aumentar volume de leads qualificados',
                'next_step'      => 'Revisar oportunidades sem atividade recente no CRM',
                'evidence_keys'  => ['crm.oportunidades', 'crm.win_rate'],
                'impact_score'   => $winRate < 30 ? 7 : 5,
            ];
        }

        // Card: Captacao de leads
        $cards[] = [
            'universo'     => 'CLIENTES_MERCADO',
            'severidade'   => $leadsUltimo < 10 ? 'ATENCAO' : 'INFO',
            'confidence'   => 75,
            'title'        => $leadsUltimo < 10
                ? 'Apenas ' . (int)$leadsUltimo . ' leads no último mês — captação insuficiente'
                : (int)$leadsUltimo . ' leads captados no último mês — funil ativo',
            'what_changed' => 'O escritório captou ' . (int)$leadsUltimo . ' leads no último mês via WhatsApp. ' . ($leadsUltimo < 10 ? 'Volume baixo pode indicar problema na campanha.' : 'Volume adequado para manter pipeline saudável.'),
            'why_it_matters' => 'Sem fluxo constante de leads, o pipeline seca em 2-3 meses.',
            'evidence_data' => [
                ['metric' => 'Leads último mês', 'value' => (int)$leadsUltimo . ' leads'],
                ['metric' => 'Total acumulado', 'value' => $totalLeads . ' leads'],
            ],
            'recommendation' => $leadsUltimo < 10 ? 'Revisar campanhas ativas e testar novo canal de aquisição' : 'Manter investimento atual e otimizar qualificação',
            'next_step'      => 'Analisar fonte dos leads para identificar canal mais eficiente',
            'evidence_keys'  => ['leads.leads_mensal'],
            'impact_score'   => $leadsUltimo < 10 ? 6 : 4,
        ];

        // Card: Base de clientes
        $cards[] = [
            'universo'     => 'CLIENTES_MERCADO',
            'severidade'   => 'INFO',
            'confidence'   => 90,
            'title'        => number_format($totalClientes, 0, ',', '.') . ' clientes na base — ' . (int)$novosUltimoMes . ' novos no último mês',
            'what_changed' => $novosUltimoMes > 0
                ? 'A base cresceu com ' . (int)$novosUltimoMes . ' novos clientes. Acompanhe a relação entre novos clientes e receita incremental.'
                : 'Nenhum novo cliente no último mês. Pode ser reflexo do ciclo de vendas ou sazonalidade.',
            'why_it_matters' => 'O crescimento da base é indicador antecedente de receita futura.',
            'evidence_data' => [
                ['metric' => 'Base total', 'value' => number_format($totalClientes, 0, ',', '.') . ' clientes'],
                ['metric' => 'Novos (mes)', 'value' => (int)$novosUltimoMes],
            ],
            'recommendation' => 'Mapear lifetime value por segmento para direcionar investimento de captação',
            'next_step'      => 'Cruzar base de clientes com receita por cliente',
            'evidence_keys'  => ['clientes.total_clientes', 'clientes.novos_clientes_mensal'],
            'impact_score'   => 3,
        ];

        // === PROCESSOS INTERNOS ===
        $proc       = $s['processos'] ?? [];
        $porStatus  = $proc['por_status'] ?? [];
        $totalAtivos = ($porStatus['Ativo'] ?? 0) + ($porStatus['Em liquidação'] ?? 0) + ($porStatus['Suspenso'] ?? 0);
        $parados90d  = $proc['sem_movimentação_90d'] ?? 0;
        $encerrados  = $proc['encerrados'] ?? 0;
        $totalProc   = $proc['total'] ?? 0;

        // Card: Processos parados
        if ($parados90d > 0) {
            $pctParados = $totalAtivos > 0 ? round(($parados90d / $totalAtivos) * 100, 1) : 0;
            $cards[] = [
                'universo'     => 'PROCESSOS_INTERNOS',
                'severidade'   => $pctParados > 20 ? 'CRITICO' : 'ATENCAO',
                'confidence'   => 95,
                'title'        => $parados90d . ' processos sem movimentação ha 90+ dias (' . $pctParados . '% do acervo)',
                'what_changed' => 'Processos parados representam risco de perda de prazo e insatisfação do cliente.',
                'why_it_matters' => 'Cliente sem retorno é o principal motivo de perda de carteira.',
                'evidence_data' => [
                    ['metric' => 'Processos parados', 'value' => $parados90d],
                    ['metric' => 'Do acervo ativo', 'value' => $pctParados . '%'],
                ],
                'recommendation' => 'Distribuir lista de processos parados entre advogados com prazo de 5 dias',
                'next_step'      => 'Extrair relatorio de processos sem movimentação e atribuir responsaveis',
                'evidence_keys'  => ['processos.sem_movimentação_90d'],
                'impact_score'   => $pctParados > 20 ? 9 : 6,
            ];
        }

        // Card: Volume processual
        $cards[] = [
            'universo'     => 'PROCESSOS_INTERNOS',
            'severidade'   => 'INFO',
            'confidence'   => 88,
            'title'        => $totalAtivos . ' processos ativos de ' . number_format($totalProc, 0, ',', '.') . ' no acervo total',
            'what_changed' => 'O escritório gerencia ' . $totalAtivos . ' processos ativos (' . ($porStatus['Ativo'] ?? 0) . ' ativos, ' . ($porStatus['Em liquidação'] ?? 0) . ' em liquidacao, ' . ($porStatus['Suspenso'] ?? 0) . ' suspensos) com ' . $encerrados . ' encerrados.',
            'why_it_matters' => 'O volume do acervo impacta diretamente na necessidade de equipe e receita recorrente.',
            'evidence_data' => [
                ['metric' => 'Ativos', 'value' => $totalAtivos],
                ['metric' => 'Encerrados', 'value' => number_format($encerrados, 0, ',', '.')],
                ['metric' => 'Total', 'value' => number_format($totalProc, 0, ',', '.')],
            ],
            'recommendation' => 'Acompanhar relacao novos/encerrados mensalmente para prever demanda',
            'next_step'      => 'Verificar distribuicao por advogado para balancear carga',
            'evidence_keys'  => ['processos.por_status', 'processos.total'],
            'impact_score'   => 4,
        ];

        // Card: Tickets
        $tickets       = $s['tickets'] ?? [];
        $ticketsAbertos = $tickets['por_status']['aberto'] ?? 0;
        $ticketsTotal   = $tickets['total'] ?? 0;
        if ($ticketsTotal > 0) {
            $cards[] = [
                'universo'     => 'PROCESSOS_INTERNOS',
                'severidade'   => $ticketsAbertos > 5 ? 'ATENCAO' : 'INFO',
                'confidence'   => 82,
                'title'        => $ticketsAbertos > 5
                    ? $ticketsAbertos . ' tickets abertos — fila crescendo'
                    : 'Fila de tickets sob controle (' . $ticketsAbertos . ' abertos de ' . $ticketsTotal . ')',
                'what_changed' => $ticketsAbertos . ' tickets aguardam resolução de um total de ' . $ticketsTotal . '.',
                'why_it_matters' => 'Tickets sao a voz do cliente. Cada ticket nao resolvido pode virar uma reclamação.',
                'evidence_data' => [
                    ['metric' => 'Tickets abertos', 'value' => $ticketsAbertos],
                    ['metric' => 'Total', 'value' => $ticketsTotal],
                ],
                'recommendation' => $ticketsAbertos > 5 ? 'Definir SLA de resposta e escalonar tickets antigos' : 'Manter cadência de resolução atual',
                'next_step'      => 'Categorizar tickets por tipo para identificar problemas recorrentes',
                'evidence_keys'  => ['tickets.por_status'],
                'impact_score'   => $ticketsAbertos > 5 ? 6 : 3,
            ];
        }

        // === TIMES & EVOLUCAO ===
        $horas  = $s['horas'] ?? [];
        $atend  = $s['atendimento'] ?? [];
        $horasMensal    = $horas['horas_por_mes'] ?? [];
        $lastHoras      = $this->lastVal($horasMensal);
        $totalRegistros = $horas['total_registros'] ?? 0;
        $conversasTotal = $atend['total_conversas'] ?? 0;
        $semResposta    = $atend['sem_resposta'] ?? 0;

        // Card: WhatsApp sem resposta
        if ($semResposta > 0) {
            $cards[] = [
                'universo'     => 'TIMES_EVOLUCAO',
                'severidade'   => $semResposta > 10 ? 'CRITICO' : 'ATENCAO',
                'confidence'   => 90,
                'title'        => $semResposta . ' conversas WhatsApp sem resposta',
                'what_changed' => 'Há ' . $semResposta . ' conversas aguardando resposta. ' . ($semResposta > 10 ? 'Volume alto pode causar perda de clientes.' : 'Priorizar as mais antigas.'),
                'why_it_matters' => 'WhatsApp é o canal principal de contato. Silêncio prolongado é percebido como descaso.',
                'evidence_data' => [
                    ['metric' => 'Sem resposta', 'value' => $semResposta . ' conversas'],
                    ['metric' => 'Total conversas', 'value' => $conversasTotal],
                ],
                'recommendation' => 'Atribuir responsável e responder todas em ate 4 horas',
                'next_step'      => 'Verificar painel NEXO e priorizar conversas mais antigas',
                'evidence_keys'  => ['atendimento.sem_resposta'],
                'impact_score'   => $semResposta > 10 ? 8 : 5,
            ];
        }

        // Card: Horas trabalhadas
        if ($lastHoras > 0) {
            $cards[] = [
                'universo'     => 'TIMES_EVOLUCAO',
                'severidade'   => 'INFO',
                'confidence'   => 85,
                'title'        => number_format($lastHoras, 0, ',', '.') . 'h registradas no último mês',
                'what_changed' => number_format($lastHoras, 0, ',', '.') . ' horas de trabalho registradas. Acompanhar permite identificar sobrecarga e otimizar alocação.',
                'why_it_matters' => 'Horas registradas sao base para precificação e produtividade.',
                'evidence_data' => [
                    ['metric' => 'Horas (mes)', 'value' => number_format($lastHoras, 0, ',', '.') . 'h'],
                    ['metric' => 'Total registros', 'value' => $totalRegistros],
                ],
                'recommendation' => 'Cruzar horas por advogado com receita para calcular produtividade individual',
                'next_step'      => 'Gerar ranking de produtividade (R$/hora) por profissional',
                'evidence_keys'  => ['horas.horas_por_mes'],
                'impact_score'   => 4,
            ];
        }

        // Card: Adoção do sistema
        $cards[] = [
            'universo'     => 'TIMES_EVOLUCAO',
            'severidade'   => 'INFO',
            'confidence'   => 70,
            'title'        => 'Equipe usando ' . ($conversasTotal > 200 ? 'ativamente' : 'parcialmente') . ' os canais digitais',
            'what_changed' => $conversasTotal . ' conversas WhatsApp e ' . $totalRegistros . ' registros de horas indicam ' . ($conversasTotal > 200 ? 'boa adoção dos canais digitais.' : 'oportunidade de aumentar uso das ferramentas.'),
            'why_it_matters' => 'A maturidade digital do escritório depende do engajamento de toda a equipe.',
            'evidence_data' => [
                ['metric' => 'Conversas WhatsApp', 'value' => $conversasTotal],
                ['metric' => 'Registros de horas', 'value' => $totalRegistros],
            ],
            'recommendation' => 'Incluir metricas de adoção na avaliação de desempenho (GDP)',
            'next_step'      => 'Identificar profissionais com baixo registro e oferecer treinamento',
            'evidence_keys'  => ['atendimento.total_conversas', 'horas.total_registros'],
            'impact_score'   => 3,
        ];

        return $cards;
    }

    private function buildSummary(array $s): array
    {
        $inad  = $s['inadimplência'] ?? [];
        $proc  = $s['processos'] ?? [];
        $atend = $s['atendimento'] ?? [];
        $crm   = $s['crm'] ?? [];
        $fin   = $s['finance'] ?? [];

        return [
            'principais_riscos' => array_values(array_filter([
                ($inad['total_vencido'] ?? 0) > 300000 ? 'Inadimplência de R$ ' . number_format($inad['total_vencido'] ?? 0, 0, ',', '.') . ' ameaça fluxo de caixa' : null,
                ($proc['sem_movimentação_90d'] ?? 0) > 10 ? ($proc['sem_movimentação_90d'] ?? 0) . ' processos parados podem gerar perda de prazo' : null,
                ($atend['sem_resposta'] ?? 0) > 5 ? ($atend['sem_resposta'] ?? 0) . ' clientes aguardando resposta no WhatsApp' : null,
                ($fin['var_pct']['receita_total'] ?? 0) < -20 ? 'Receita em queda de ' . abs($fin['var_pct']['receita_total'] ?? 0) . '% vs mes anterior' : null,
            ])),
            'principais_oportunidades' => array_values(array_filter([
                ($crm['valor_pipeline'] ?? 0) > 0 ? 'R$ ' . number_format($crm['valor_pipeline'] ?? 0, 0, ',', '.') . ' no pipeline aguardando conversão' : null,
                'Automacao de cobrança pode recuperar ate 30% da inadimplência',
                'Ranking de produtividade (GDP) pode alinhar incentivos com resultado',
            ])),
            'apostas_recomendadas' => [
                ['descricao' => 'Campanha de cobrança ativa nos 20 maiores devedores', 'impacto_esperado' => 'Recuperar R$ 100-200 mil em 60 dias', 'esforço' => 'Médio'],
                ['descricao' => 'Follow-up estruturado nas oportunidades CRM abertas', 'impacto_esperado' => 'Aumentar conversão em 5-10 pontos percentuais', 'esforço' => 'Baixo'],
                ['descricao' => 'SLA de resposta WhatsApp de 4 horas', 'impacto_esperado' => 'Reduzir reclamações e aumentar retenção', 'esforço' => 'Baixo'],
            ],
        ];
    }
}
