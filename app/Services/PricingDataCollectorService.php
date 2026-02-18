<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Cliente;
use App\Models\Oportunidade;
use App\Models\Processo;
use App\Models\PricingCalibration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PricingDataCollectorService
{
    /**
     * Busca lead/cliente por nome ou documento (CPF/CNPJ)
     */
    public function buscarProponente(string $busca): array
    {
        $resultados = [];

        // Buscar em leads
        $leads = Lead::where('nome', 'like', "%{$busca}%")
            ->orWhere('telefone', 'like', "%{$busca}%")
            ->limit(10)
            ->get(['id', 'nome', 'telefone', 'email', 'cidade', 'area_interesse', 'status',
                    'resumo_demanda', 'potencial_honorarios', 'urgencia', 'intencao_contratar',
                    'palavras_chave']);

        foreach ($leads as $lead) {
            $resultados[] = [
                'tipo' => 'lead',
                'id' => $lead->id,
                'nome' => $lead->nome,
                'telefone' => $lead->telefone,
                'email' => $lead->email,
                'info' => 'Lead - ' . ($lead->area_interesse ?? 'sem área') . ' - ' . ($lead->status ?? 'novo'),
                'lead' => $lead->toArray(),
            ];
        }

        // Buscar em clientes
        $clientes = Cliente::where('nome', 'like', "%{$busca}%")
            ->orWhere('cpf_cnpj', 'like', "%{$busca}%")
            ->orWhere('cpf', 'like', "%{$busca}%")
            ->orWhere('cnpj', 'like', "%{$busca}%")
            ->limit(10)
            ->get();

        foreach ($clientes as $cliente) {
            $resultados[] = [
                'tipo' => 'cliente',
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'documento' => $cliente->cpf_cnpj ?? $cliente->cpf ?? $cliente->cnpj,
                'info' => 'Cliente - ' . ($cliente->tipo ?? $cliente->tipo_pessoa ?? 'N/I'),
                'cliente' => $cliente->toArray(),
            ];
        }

        return $resultados;
    }

    /**
     * Coleta TODOS os dados disponíveis de um lead para a IA
     */
    public function coletarDadosLead(int $leadId): array
    {
        $lead = Lead::find($leadId);
        if (!$lead) {
            return ['erro' => 'Lead não encontrado'];
        }

        $dados = [
            'proponente' => [
                'nome' => $lead->nome,
                'telefone' => $lead->telefone,
                'email' => $lead->email,
                'cidade' => $lead->cidade,
                'tipo_pessoa' => 'PF', // Leads são geralmente PF
                'documento' => null,
            ],
            'demanda' => [
                'area_interesse' => $lead->area_interesse,
                'resumo_demanda' => $lead->resumo_demanda,
                'palavras_chave' => $lead->palavras_chave,
                'urgencia' => $lead->urgencia,
                'intencao_contratar' => $lead->intencao_contratar,
                'potencial_honorarios' => $lead->potencial_honorarios,
            ],
            'lead_id' => $leadId,
            'cliente_id' => $lead->cliente_id,
        ];

        // Se lead vinculado a cliente, puxar dados do cliente
        if ($lead->cliente_id) {
            $dadosCliente = $this->coletarDadosCliente($lead->cliente_id);
            $dados = array_merge($dados, $dadosCliente);
        }

        return $dados;
    }

    /**
     * Coleta TODOS os dados disponíveis de um cliente para a IA
     */
    public function coletarDadosCliente(int $clienteId): array
    {
        $cliente = Cliente::find($clienteId);
        if (!$cliente) {
            return ['erro' => 'Cliente não encontrado'];
        }

        $dados = [
            'proponente' => [
                'nome' => $cliente->nome,
                'telefone' => $cliente->telefone,
                'email' => $cliente->email,
                'cidade' => $cliente->endereco ?? null,
                'tipo_pessoa' => $cliente->tipo ?? $cliente->tipo_pessoa ?? 'PF',
                'documento' => $cliente->cpf_cnpj ?? $cliente->cpf ?? $cliente->cnpj,
            ],
            'cliente_id' => $clienteId,
        ];

        // Histórico de processos do cliente
        $processos = Processo::where('cliente_id', $clienteId)->get();
        $dados['historico_cliente'] = [
            'total_processos' => $processos->count(),
            'processos_ativos' => $processos->where('status', 'Ativo')->count(),
            'processos_encerrados' => $processos->whereIn('status', ['Encerrado', 'Arquivado', 'Baixado'])->count(),
            'valor_causa_total' => $processos->sum('valor_causa'),
            'valor_causa_medio' => $processos->count() > 0 ? $processos->avg('valor_causa') : 0,
            'areas' => $processos->pluck('natureza')->filter()->unique()->values()->toArray(),
        ];

        // Dados financeiros do cliente
        $dados['financeiro_cliente'] = $this->getDadosFinanceirosCliente($clienteId);

        // SIRIC - Análise de crédito (se existir)
        $dados['siric'] = $this->getSiricData($clienteId, $cliente->cpf_cnpj ?? $cliente->cpf ?? $cliente->cnpj);

        return $dados;
    }

    /**
     * Busca dados financeiros agregados do cliente
     */
    private function getDadosFinanceirosCliente(int $clienteId): array
    {
        try {
            // Total faturado
            $faturamento = DB::table('movimentos')
                ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
                ->where('pessoa_id_datajuri', function ($q) use ($clienteId) {
                    $q->select('datajuri_id')->from('clientes')->where('id', $clienteId)->limit(1);
                })
                ->sum('valor');

            // Contas em atraso
            $atraso = DB::table('contas_receber')
                ->where('cliente', function ($q) use ($clienteId) {
                    $q->select('nome')->from('clientes')->where('id', $clienteId)->limit(1);
                })
                ->where('status', '!=', 'Pago')
                ->whereDate('data_vencimento', '<', now())
                ->sum('valor');

            // Pontualidade (% pago no prazo)
            $totalContas = DB::table('contas_receber')
                ->where('cliente', function ($q) use ($clienteId) {
                    $q->select('nome')->from('clientes')->where('id', $clienteId)->limit(1);
                })
                ->count();

            $contasPagas = DB::table('contas_receber')
                ->where('cliente', function ($q) use ($clienteId) {
                    $q->select('nome')->from('clientes')->where('id', $clienteId)->limit(1);
                })
                ->where('status', 'Pago')
                ->count();

            return [
                'total_faturado' => round($faturamento, 2),
                'valor_em_atraso' => round($atraso, 2),
                'total_contas' => $totalContas,
                'contas_pagas' => $contasPagas,
                'taxa_pontualidade' => $totalContas > 0 ? round(($contasPagas / $totalContas) * 100, 1) : null,
            ];
        } catch (\Exception $e) {
            Log::warning('PricingDataCollector: Erro ao buscar financeiro do cliente', ['erro' => $e->getMessage()]);
            return ['total_faturado' => null, 'valor_em_atraso' => null];
        }
    }

    /**
     * Busca dados do SIRIC (análise de crédito já processada)
     */
    private function getSiricData(int $clienteId, ?string $documento): array
    {
        try {
            // Tenta buscar resultado SIRIC mais recente
            $siric = DB::table('siric_analises')
                ->where('cliente_id', $clienteId)
                ->orWhere('documento', $documento)
                ->orderByDesc('created_at')
                ->first();

            if ($siric) {
                return [
                    'score' => $siric->score ?? null,
                    'rating' => $siric->rating ?? null,
                    'limite_sugerido' => $siric->limite_sugerido ?? null,
                    'recomendacao' => $siric->recomendacao ?? null,
                    'data_analise' => $siric->created_at ?? null,
                ];
            }
        } catch (\Exception $e) {
            // Tabela SIRIC pode não existir ainda - graceful degradation
            Log::info('PricingDataCollector: SIRIC não disponível', ['erro' => $e->getMessage()]);
        }

        return ['score' => null, 'rating' => null, 'limite_sugerido' => null, 'recomendacao' => null];
    }

    /**
     * Coleta histórico agregado do escritório para casos similares
     */

    /**
     * Analisa histórico de oportunidades ganhas/perdidas do CRM
     * para calibrar precificação com dados reais de conversão.
     */
    public function getHistoricoCRM(?string $areaDireito): array
    {
        try {
            $baseQuery = DB::table('crm_opportunities')
                ->whereIn('status', ['won', 'lost'])
                ->where('value_estimated', '>', 0);

            // ---- GERAL (todas as áreas) ----
            $geral = (clone $baseQuery)->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status='won' THEN 1 ELSE 0 END) as ganhas,
                SUM(CASE WHEN status='lost' THEN 1 ELSE 0 END) as perdidas,
                ROUND(AVG(CASE WHEN status='won' THEN value_estimated END), 2) as ticket_medio_won,
                ROUND(AVG(CASE WHEN status='lost' THEN value_estimated END), 2) as ticket_medio_lost,
                ROUND(MIN(CASE WHEN status='won' THEN value_estimated END), 2) as menor_won,
                ROUND(MAX(CASE WHEN status='won' THEN value_estimated END), 2) as maior_won
            ")->first();

            $winRate = ($geral->total > 0) ? round(($geral->ganhas / $geral->total) * 100, 1) : 0;

            // ---- POR TIPO DE DEMANDA ----
            $porTipo = (clone $baseQuery)
                ->whereNotNull('tipo_demanda')
                ->groupBy('tipo_demanda', 'status')
                ->selectRaw("
                    tipo_demanda,
                    status,
                    COUNT(*) as qtd,
                    ROUND(AVG(value_estimated), 2) as ticket_medio,
                    ROUND(MIN(value_estimated), 2) as menor,
                    ROUND(MAX(value_estimated), 2) as maior
                ")->get();

            $analise_por_tipo = [];
            foreach ($porTipo as $row) {
                $tipo = $row->tipo_demanda;
                if (!isset($analise_por_tipo[$tipo])) {
                    $analise_por_tipo[$tipo] = ['won' => null, 'lost' => null];
                }
                $analise_por_tipo[$tipo][$row->status] = [
                    'qtd' => $row->qtd,
                    'ticket_medio' => $row->ticket_medio,
                    'faixa' => "{$row->menor} - {$row->maior}",
                ];
            }

            // Calcular win rate por tipo
            $conversao_por_tipo = [];
            foreach ($analise_por_tipo as $tipo => $dados) {
                $w = $dados['won']['qtd'] ?? 0;
                $l = $dados['lost']['qtd'] ?? 0;
                $total = $w + $l;
                $conversao_por_tipo[$tipo] = [
                    'won' => $w,
                    'lost' => $l,
                    'win_rate' => $total > 0 ? round(($w / $total) * 100, 1) : 0,
                    'ticket_medio_won' => $dados['won']['ticket_medio'] ?? null,
                    'ticket_medio_lost' => $dados['lost']['ticket_medio'] ?? null,
                    'faixa_won' => $dados['won']['faixa'] ?? null,
                ];
            }

            // ---- FILTRO POR ÁREA ESPECÍFICA (se informada) ----
            $area_especifica = null;
            if ($areaDireito) {
                $areaQuery = (clone $baseQuery)->where(function($q) use ($areaDireito) {
                    $q->where('tipo_demanda', 'like', "%{$areaDireito}%")
                      ->orWhere('area', 'like', "%{$areaDireito}%");
                });

                $areaStats = $areaQuery->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status='won' THEN 1 ELSE 0 END) as ganhas,
                    SUM(CASE WHEN status='lost' THEN 1 ELSE 0 END) as perdidas,
                    ROUND(AVG(CASE WHEN status='won' THEN value_estimated END), 2) as ticket_medio_won,
                    ROUND(AVG(CASE WHEN status='lost' THEN value_estimated END), 2) as ticket_medio_lost
                ")->first();

                if ($areaStats->total > 0) {
                    $area_especifica = [
                        'area' => $areaDireito,
                        'total' => $areaStats->total,
                        'ganhas' => $areaStats->ganhas,
                        'perdidas' => $areaStats->perdidas,
                        'win_rate' => round(($areaStats->ganhas / $areaStats->total) * 100, 1),
                        'ticket_medio_won' => $areaStats->ticket_medio_won,
                        'ticket_medio_lost' => $areaStats->ticket_medio_lost,
                    ];
                }
            }

            // ---- MOTIVOS DE PERDA ----
            $motivos = DB::table('crm_opportunities')
                ->where('status', 'lost')
                ->whereNotNull('lost_reason')
                ->where('lost_reason', '!=', '')
                ->groupBy('lost_reason')
                ->selectRaw("lost_reason, COUNT(*) as qtd, ROUND(AVG(value_estimated), 2) as ticket_medio")
                ->orderByDesc('qtd')
                ->get()
                ->map(fn($r) => [
                    'motivo' => $r->lost_reason,
                    'qtd' => $r->qtd,
                    'ticket_medio' => $r->ticket_medio,
                ])
                ->toArray();

            // ---- FAIXAS DE PREÇO COM TAXA DE CONVERSÃO ----
            $faixas = DB::select("
                SELECT
                    CASE
                        WHEN value_estimated <= 2000 THEN 'ate_2000'
                        WHEN value_estimated <= 5000 THEN '2001_a_5000'
                        WHEN value_estimated <= 10000 THEN '5001_a_10000'
                        WHEN value_estimated <= 20000 THEN '10001_a_20000'
                        ELSE 'acima_20000'
                    END as faixa,
                    COUNT(*) as total,
                    SUM(CASE WHEN status='won' THEN 1 ELSE 0 END) as ganhas,
                    ROUND(SUM(CASE WHEN status='won' THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as win_rate
                FROM crm_opportunities
                WHERE status IN ('won','lost') AND value_estimated > 0
                GROUP BY faixa
                ORDER BY MIN(value_estimated)
            ");

            $faixas_conversao = [];
            foreach ($faixas as $f) {
                $faixas_conversao[$f->faixa] = [
                    'total' => $f->total,
                    'ganhas' => $f->ganhas,
                    'win_rate' => $f->win_rate,
                ];
            }

            return [
                'resumo_geral' => [
                    'total_oportunidades' => $geral->total,
                    'ganhas' => $geral->ganhas,
                    'perdidas' => $geral->perdidas,
                    'win_rate_geral' => $winRate,
                    'ticket_medio_ganhas' => $geral->ticket_medio_won,
                    'ticket_medio_perdidas' => $geral->ticket_medio_lost,
                    'faixa_ganhas' => "{$geral->menor_won} - {$geral->maior_won}",
                ],
                'conversao_por_tipo_demanda' => $conversao_por_tipo,
                'area_especifica' => $area_especifica,
                'motivos_perda' => $motivos,
                'faixas_preco_conversao' => $faixas_conversao,
                'insight_preco' => "Propostas perdidas por preco tinham ticket medio de R$" .
                    collect($motivos)->firstWhere('motivo', 'Preço dos honorários')['ticket_medio'] ?? 'N/A',
            ];

        } catch (\Exception $e) {
            Log::warning('PricingDataCollector: Erro no historico CRM', ['erro' => $e->getMessage()]);
            return ['resumo_geral' => ['total_oportunidades' => 0, 'win_rate_geral' => 0]];
        }
    }

    public function getHistoricoAgregado(?string $areaDireito): array
    {
        try {
            $query = DB::table('processos')->whereNotNull('valor_causa')->where('valor_causa', '>', 0);

            if ($areaDireito) {
                $query->where(function ($q) use ($areaDireito) {
                    $q->where('natureza', 'like', "%{$areaDireito}%")
                      ->orWhere('assunto', 'like', "%{$areaDireito}%");
                });
            }

            $stats = $query->selectRaw('
                COUNT(*) as total_casos,
                AVG(valor_causa) as valor_causa_medio,
                MIN(valor_causa) as valor_causa_min,
                MAX(valor_causa) as valor_causa_max,
                STDDEV(valor_causa) as valor_causa_desvio
            ')->first();

            // Tempo médio dos processos da mesma área
            $tempoMedio = DB::table('processos')
                ->whereNotNull('data_cadastro_dj')
                ->whereIn('status', ['Encerrado', 'Arquivado', 'Baixado'])
                ->when($areaDireito, function ($q) use ($areaDireito) {
                    $q->where('natureza', 'like', "%{$areaDireito}%");
                })
                ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as dias_medio')
                ->value('dias_medio');

            // Horas trabalhadas médias
            $horasMedia = DB::table('horas_trabalhadas_datajuri')
                ->selectRaw('AVG(total_hora_trabalhada) as media_horas')
                ->value('media_horas');

            // Receita média por caso na área
            $receitaMedia = DB::table('contratos')
                ->whereNotNull('valor')
                ->where('valor', '>', 0)
                ->selectRaw('AVG(valor) as receita_media, COUNT(*) as total_contratos')
                ->first();

            return [
                'area_filtro' => $areaDireito ?? 'Todas as áreas',
                'total_casos' => $stats->total_casos ?? 0,
                'valor_causa_medio' => round($stats->valor_causa_medio ?? 0, 2),
                'valor_causa_min' => round($stats->valor_causa_min ?? 0, 2),
                'valor_causa_max' => round($stats->valor_causa_max ?? 0, 2),
                'tempo_medio_dias' => round($tempoMedio ?? 0),
                'horas_media_caso' => round($horasMedia ?? 0, 1),
                'receita_media_contrato' => round($receitaMedia->receita_media ?? 0, 2),
                'total_contratos' => $receitaMedia->total_contratos ?? 0,
            ];
        } catch (\Exception $e) {
            Log::warning('PricingDataCollector: Erro no histórico agregado', ['erro' => $e->getMessage()]);
            return ['total_casos' => 0, 'valor_causa_medio' => 0];
        }
    }

    /**
     * Coleta dados macro do escritório (metas, capacidade)
     */
    public function getDadosMacroEscritorio(): array
    {
        try {
            $mesAtual = now()->month;
            $anoAtual = now()->year;

            // Receita do mês atual
            $receitaMes = DB::table('movimentos')
                ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
                ->where('mes', $mesAtual)
                ->where('ano', $anoAtual)
                ->sum('valor');

            // Meta do mês (se existir)
            $metaMes = DB::table('kpi_monthly_targets')
                ->where('mes', $mesAtual)
                ->where('ano', $anoAtual)
                ->where('kpi_key', 'receita_total')
                ->value('meta_valor');

            // Processos ativos
            $processosAtivos = DB::table('processos')
                ->where('status', 'Ativo')
                ->count();

            // Pipeline aberto
            $pipelineAberto = DB::table('crm_opportunities')
                ->where('status', 'open')
                ->sum('value_estimated');

            return [
                'receita_mes_atual' => round($receitaMes, 2),
                'meta_mes_atual' => $metaMes ? round($metaMes, 2) : null,
                'percentual_meta' => ($metaMes && $metaMes > 0) ? round(($receitaMes / $metaMes) * 100, 1) : null,
                'processos_ativos' => $processosAtivos,
                'pipeline_aberto' => round($pipelineAberto, 2),
                'mes_referencia' => $mesAtual . '/' . $anoAtual,
            ];
        } catch (\Exception $e) {
            Log::warning('PricingDataCollector: Erro nos dados macro', ['erro' => $e->getMessage()]);
            return ['receita_mes_atual' => 0];
        }
    }

    /**
     * Monta o pacote completo de dados para enviar à IA
     */
    public function montarPacoteIA(array $dadosProponente, ?string $areaDireito, ?string $contextoAdicional): array
    {
        $calibracao = PricingCalibration::getForPrompt();
        $historico = $this->getHistoricoAgregado($areaDireito);
        $macro = $this->getDadosMacroEscritorio();

        try {
            $historicoCRM = $this->getHistoricoCRM($areaDireito);
        } catch (\Exception $e) {
            \Log::warning('PricingDataCollector: Erro histórico CRM', ['erro' => $e->getMessage()]);
            $historicoCRM = [];
        }

        return [
            'proponente' => $dadosProponente['proponente'] ?? [],
            'demanda' => $dadosProponente['demanda'] ?? [],
            'historico_cliente' => $dadosProponente['historico_cliente'] ?? [],
            'financeiro_cliente' => $dadosProponente['financeiro_cliente'] ?? [],
            'siric' => $dadosProponente['siric'] ?? [],
            'historico_escritorio' => $historico,
            'historico_crm_conversao' => $historicoCRM,
            'macro_escritorio' => $macro,
            'calibracao_estrategica' => $calibracao,
            'contexto_adicional' => $contextoAdicional,
            'referencia_oab_sc' => $this->getHonorariosOAB($areaDireito),
            'data_geracao' => now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * Retorna piso mínimo OAB/SC por área do direito (Resolução CP 04/2025, IPCA 12/2024)
     */
    public function getHonorariosOAB(?string $areaDireito): array
    {
        $tabela = [
            'Atuação Avulsa/Extrajudicial' => ['piso_min' => 130.22, 'piso_padrao' => 1302.25, 'piso_max' => 6511.22, 'referencia' => 'Itens 1-14: consultas R$455-781, pareceres R$3.255-6.511, contratos R$1.562-6.511, diligências R$117-390'],
            'Juizados Especiais' => ['piso_min' => 651.12, 'piso_padrao' => 3906.73, 'piso_max' => 3906.73, 'referencia' => 'Itens 15-16: atuação até sentença R$3.906, recurso +R$1.302, sustentação oral R$1.302'],
            'Direito Administrativo/Público' => ['piso_min' => 2604.48, 'piso_padrao' => 5208.98, 'piso_max' => 58601.01, 'referencia' => 'Itens 17-20: defesa sindicância R$2.604, processo admin R$5.208, mandado segurança R$6.511, improbidade R$10.417'],
            'Direito Civil e Empresarial' => ['piso_min' => 2604.48, 'piso_padrao' => 5208.98, 'piso_max' => 7813.47, 'referencia' => 'Itens 21-45: rito sumário R$3.906, ordinário R$5.208, mandado segurança R$6.511, dissolução sociedade R$7.813, indenizatória R$3.906'],
            'Direito Falimentar' => ['piso_min' => 3906.73, 'piso_padrao' => 6511.22, 'piso_max' => 10417.96, 'referencia' => 'Itens 46-55: falência credor R$10.417, recuperação judicial R$10.417, habilitação crédito R$5.208'],
            'Direito de Família' => ['piso_min' => 1562.70, 'piso_padrao' => 5208.98, 'piso_max' => 11720.20, 'referencia' => 'Itens 56-94: divórcio consensual R$5.208, litigioso R$6.511-8.464, alimentos R$5.208, guarda R$5.208, investigação paternidade R$10.417, adoção R$9.115-11.720'],
            'Direito das Sucessões' => ['piso_min' => 3906.73, 'piso_padrao' => 5208.98, 'piso_max' => 10417.96, 'referencia' => 'Itens 95-112: inventário sem litígio R$5.208, com litígio R$7.813, sobrepartilha R$5.208, nulidade testamento R$7.813'],
            'Direito Eleitoral' => ['piso_min' => 6511.22, 'piso_padrao' => 9115.71, 'piso_max' => 26044.89, 'referencia' => 'Itens 113-117: queixa/representação R$9.115, defesa prisão R$26.044, defesa multa R$6.511, TRE R$13.022'],
            'Direito Militar' => ['piso_min' => 781.35, 'piso_padrao' => 6511.22, 'piso_max' => 13022.45, 'referencia' => 'Itens 118-138: defesa 1ª instância R$9.897-11.069, recurso apelação R$7.813, recurso especial R$13.022'],
            'Direito Penal' => ['piso_min' => 2344.04, 'piso_padrao' => 5208.98, 'piso_max' => 33207.24, 'referencia' => 'Itens 138-171: defesa comum R$9.766, rito especial R$10.417, júri pronúncia R$19.533, júri plenário R$33.207, habeas corpus R$11.720'],
            'Direito do Trabalho' => ['piso_min' => 976.69, 'piso_padrao' => 3255.61, 'piso_max' => 13803.80, 'referencia' => 'Itens 172-183: reclamação reclamante 20% mín R$1.953, defesa reclamado 20% mín R$3.255, dissídio coletivo R$4.948-13.803, rescisória R$2.344'],
            'Direito Previdenciário' => ['piso_min' => 2604.48, 'piso_padrao' => 3255.61, 'piso_max' => 5208.98, 'referencia' => 'Itens 184-213: ação concessão 20-30% mín R$3.906, requerimento admin R$3.646, planejamento R$3.255, mandado segurança R$5.208'],
            'Direito Tributário' => ['piso_min' => 3646.28, 'piso_padrao' => 5860.10, 'piso_max' => 6511.22, 'referencia' => 'Itens 214-224: defesa admin R$5.208, anulatória R$5.860, embargos execução R$6.511, mandado segurança R$6.511, repetição indébito R$4.557'],
            'Direito do Consumidor' => ['piso_min' => 2604.48, 'piso_padrao' => 3906.73, 'piso_max' => 6511.22, 'referencia' => 'Itens 225-229: ação consumidor R$3.906, defesa fornecedor R$5.860, nulidade cláusula R$5.208'],
            'Tribunais e Conselhos' => ['piso_min' => 1953.37, 'piso_padrao' => 5208.98, 'piso_max' => 13022.45, 'referencia' => 'Itens 230-271: apelação cível R$4.557, agravo instrumento R$4.557, recurso especial R$7.813, revisão criminal R$13.022, ação rescisória R$7.813'],
            'Direito Desportivo' => ['piso_min' => 651.12, 'piso_padrao' => 6511.22, 'piso_max' => 19533.66, 'referencia' => 'Itens 272-281: 1º grau R$1.302, TJD R$1.562, STJD R$2.344, CAS/TAS R$19.533, consultoria R$6.511-13.022'],
            'Direito Marítimo/Portuário/Aduaneiro' => ['piso_min' => 2604.48, 'piso_padrao' => 6511.22, 'piso_max' => 65112.23, 'referencia' => 'Itens 282-304: contratos transporte R$3.906-10.417, tribunal marítimo R$26.044, ANTAQ R$13.022-65.112, ações aduaneiras R$5.208-7.813'],
            'Direito de Partido' => ['piso_min' => 1953.37, 'piso_padrao' => 4557.86, 'piso_max' => 5208.98, 'referencia' => 'Itens 305-307: consultivo R$1.953, assistência total R$4.557, vínculo 4h R$2.864, vínculo 8h R$5.208'],
            'Propriedade Intelectual' => ['piso_min' => 520.90, 'piso_padrao' => 3255.61, 'piso_max' => 16929.18, 'referencia' => 'Itens 308-330: registro marca R$3.255, recurso INPI R$3.776, ação contrafação R$10.417, nulidade INPI R$16.929'],
            'Direito Ambiental' => ['piso_min' => 651.12, 'piso_padrao' => 6511.22, 'piso_max' => 13022.45, 'referencia' => 'Itens 331-338: defesa admin R$4.167, licenciamento R$6.511, inquérito civil R$6.511, ação civil pública R$13.022'],
            'Direito da Criança e Adolescente' => ['piso_min' => 1953.37, 'piso_padrao' => 5208.98, 'piso_max' => 14324.69, 'referencia' => 'Itens 339-358: ato infracional R$9.115, habeas corpus R$11.720-14.324, execução medida R$9.115, destituição poder familiar R$9.766'],
            'Direito Digital' => ['piso_min' => 1041.80, 'piso_padrao' => 3906.73, 'piso_max' => 15626.93, 'referencia' => 'Itens 359-367: remoção conteúdo R$3.906, termos uso R$3.906, contrato software R$2.604, ação negatória R$15.626'],
            'Assistência Social' => ['piso_min' => 1562.70, 'piso_padrao' => 1562.70, 'piso_max' => 1562.70, 'referencia' => 'Itens 368-369: ação judicial R$1.562, ação extrajudicial R$1.562'],
            'Direito Imobiliário' => ['piso_min' => 1302.25, 'piso_padrao' => 5208.98, 'piso_max' => 7813.47, 'referencia' => 'Itens 370-392: despejo R$3.906, usucapião R$5.860-7.813, reivindicatória R$7.813, incorporação R$7.813, extinção condomínio R$7.813'],
            'Mediação e Conciliação' => ['piso_min' => 586.01, 'piso_padrao' => 2344.04, 'piso_max' => 2344.04, 'referencia' => 'Itens 393-394: mediação R$2.344 ou R$781/hora, conciliação R$1.823 ou R$586/hora'],
        ];

        if (!$areaDireito || !isset($tabela[$areaDireito])) {
            return [
                'fonte' => 'Tabela OAB/SC - Resolução CP 04/2025 (IPCA 12/2024)',
                'area_encontrada' => false,
                'nota' => 'Área não mapeada na tabela OAB/SC. Usar valores de mercado como referência.',
            ];
        }

        $dados = $tabela[$areaDireito];
        return [
            'fonte' => 'Tabela OAB/SC - Resolução CP 04/2025 (IPCA 12/2024)',
            'area' => $areaDireito,
            'area_encontrada' => true,
            'piso_minimo' => $dados['piso_min'],
            'piso_padrao' => $dados['piso_padrao'],
            'piso_maximo' => $dados['piso_max'],
            'detalhamento' => $dados['referencia'],
        ];
    }

}
