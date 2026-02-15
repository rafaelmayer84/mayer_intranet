<?php

namespace App\Services;

use App\Models\{Cliente, Lead, Processo};
use App\Models\Crm\CrmOpportunity;
use Illuminate\Support\Facades\{DB, Cache};
use Carbon\Carbon;
use App\Helpers\KpiMetaHelper;

/**
 * ClientesMercadoService
 * 
 * Service para cálculos do Dashboard Clientes & Mercado (BSC)
 * Segue o mesmo padrão do DashboardFinanceProdService: cálculo on-the-fly por competência
 * 
 * DEFINIÇÕES FORMAIS:
 * 
 * 1. CLIENTE ATIVO (estoque):
 *    - Cliente com pelo menos 1 processo com status = 'Ativo'
 *    - KPI: COUNT(DISTINCT processos.cliente_id) WHERE processos.status='Ativo'
 * 
 * 2. CONVERSÃO POR MÊS (Leads):
 *    - Leads novos: COUNT WHERE DATE_FORMAT(created_at,'%Y-%m') = competência
 *    - Leads convertidos: COUNT WHERE status='convertido' AND DATE_FORMAT(updated_at,'%Y-%m') = competência
 *    - Taxa conversão: (convertidos_mes / novos_mes) * 100
 * 
 * 3. OPORTUNIDADES (mensal):
 *    - Ganha no mês: status='won' AND DATE_FORMAT(data_fechamento,'%Y-%m')=competência
 *    - Valor ganho: SUM(value_estimated) das ganhas no mês
 *    - Pipeline aberto: SUM(value_estimated) WHERE estagio IN ('prospectando','qualificacao','proposta','negociacao')
 *    - Win rate: ganhas / (ganhas + perdidas) no mês
 * 
 * 4. TOP 10 CLIENTES:
 *    - Critério: quantidade de processos ativos (status='Ativo')
 * 
 * ESTRUTURA TABELA LEADS (07/02/2026):
 *    id, nome, telefone, contact_id, area_interesse, sub_area, complexidade, urgencia,
 *    objecoes, gatilho_emocional, perfil_socioeconomico, potencial_honorarios, origem_canal,
 *    cidade, resumo_demanda, palavras_chave, intencao_contratar, intencao_justificativa,
 *    gclid, status, espocrm_id, erro_processamento, metadata, data_entrada, created_at, updated_at
 * 
 * @author Claude
 * @date 04/02/2026 - Criação
 * @date 07/02/2026 - Correção colunas: origem->origem_canal, data_criacao_lead->created_at, data_conversao->status+updated_at
 */
class ClientesMercadoService
{
    /**
     * Cache TTL em segundos (5 minutos)
     */
    private const CACHE_TTL = 300;

    /**
     * Estágios considerados como "pipeline aberto"
     */
    // Pipeline no CRM nativo = status 'open' (não precisa listar estágios)

    /**
     * Obter todos os dados do dashboard para uma competência
     */
    public function getDashboardData(int $ano, int $mes): array
    {
        $cacheKey = "clientes_mercado_{$ano}_{$mes}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($ano, $mes) {
            $competencia = sprintf('%04d-%02d', $ano, $mes);
            $refDate = Carbon::createFromDate($ano, $mes, 1)->endOfMonth();
            
            return [
                'competencia' => [
                    'ano' => $ano,
                    'mes' => $mes,
                    'label' => $this->getMesLabel($mes) . '/' . $ano
                ],
                'kpis_principais' => $this->getKpisPrincipais($competencia, $refDate),
                'kpis_secundarios' => $this->getKpisSecundarios($competencia, $refDate),
                'serie_12_meses' => $this->getSerie12Meses($ano, $mes),
                'oportunidades_por_estagio' => $this->getOportunidadesPorEstagio($competencia),
                'origem_leads' => $this->getOrigemLeads($competencia),
                'top_10_clientes' => $this->getTop10Clientes(),
                'gerado_em' => now()->format('d/m/Y H:i:s')
            ];
        });
    }

    /**
     * KPIs Principais (4 cards superiores)
     */
    private function getKpisPrincipais(string $competencia, Carbon $refDate): array
    {
        // Mês anterior para cálculo de trend
        $prevDate = $refDate->copy()->subMonth();
        $compAnterior = $prevDate->format('Y-m');

        // Helper de variação %
        $pctChange = function($atual, $anterior) {
            if ($anterior == 0) return $atual > 0 ? 100.0 : 0.0;
            return round((($atual - $anterior) / abs($anterior)) * 100, 1);
        };
        $fmtPrev = function($val, $formato) {
            if ($formato === 'moeda') return 'R$ ' . number_format((float)$val, 2, ',', '.');
            return number_format((float)$val, 0, ',', '.');
        };

        // Leads novos no mês (usando created_at)
        $leadsNovos = Lead::whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$competencia])->count();
        $leadsNovosPrev = Lead::whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$compAnterior])->count();

        // Oportunidades ganhas no mês
        $opsGanhas = CrmOpportunity::where('status', 'won')
            ->whereRaw("DATE_FORMAT(won_at, '%Y-%m') = ?", [$competencia])->count();
        $opsGanhasPrev = CrmOpportunity::where('status', 'won')
            ->whereRaw("DATE_FORMAT(won_at, '%Y-%m') = ?", [$compAnterior])->count();

        // Clientes ativos (estoque)
        $clientesAtivos = Processo::where('status', 'Ativo')
            ->whereNotNull('cliente_id')->distinct('cliente_id')->count('cliente_id');

        // Valor ganho no mês
        $valorGanho = CrmOpportunity::where('status', 'won')
            ->whereRaw("DATE_FORMAT(won_at, '%Y-%m') = ?", [$competencia])->sum('value_estimated') ?? 0;
        $valorGanhoPrev = CrmOpportunity::where('status', 'won')
            ->whereRaw("DATE_FORMAT(won_at, '%Y-%m') = ?", [$compAnterior])->sum('value_estimated') ?? 0;

        return [
            'leads_novos' => [
                'valor' => $leadsNovos,
                'label' => 'Leads Novos',
                'icon' => '👥',
                'cor' => 'blue',
                'formato' => 'numero',
                'trend' => $pctChange($leadsNovos, $leadsNovosPrev),
                'prevValue' => $fmtPrev($leadsNovosPrev, 'numero'),
                'subtitle' => $leadsNovos > 0 ? 'Acumulado mês atual' : 'Nenhum lead no período',
                'meta' => KpiMetaHelper::get('leads_novos', $refDate->year, $refDate->month, 0),
            ],
            'oportunidades_ganhas' => [
                'valor' => $opsGanhas,
                'label' => 'Oportunidades Ganhas',
                'icon' => '🏆',
                'cor' => 'green',
                'formato' => 'numero',
                'trend' => $pctChange($opsGanhas, $opsGanhasPrev),
                'prevValue' => $fmtPrev($opsGanhasPrev, 'numero'),
                'subtitle' => $opsGanhas > 0 ? 'Fechamentos confirmados' : 'Sem fechamentos no mês',
                'meta' => KpiMetaHelper::get('oportunidades_ganhas', $refDate->year, $refDate->month, 0),
            ],
            'clientes_ativos' => [
                'valor' => $clientesAtivos,
                'label' => 'Clientes Ativos (estoque)',
                'icon' => '🏢',
                'cor' => 'purple',
                'formato' => 'numero',
                'meta' => KpiMetaHelper::get('clientes_ativos', $refDate->year, $refDate->month, 0),
            ],
            'valor_ganho' => [
                'valor' => $valorGanho,
                'label' => 'Valor Ganho',
                'icon' => '💰',
                'cor' => 'green',
                'formato' => 'moeda',
                'meta' => KpiMetaHelper::get('valor_ganho', $refDate->year, $refDate->month, 0),
            ]
        ];
    }

    /**
     * KPIs Secundários (4 cards inferiores)
     */
    private function getKpisSecundarios(string $competencia, Carbon $refDate): array
    {
        // Leads novos no mês (usando created_at)
        $leadsNovos = Lead::whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$competencia])
            ->count();
        
        // Leads convertidos no mês (status='convertido' + updated_at como proxy da data de conversão)
        $leadsConvertidos = Lead::where('status', 'convertido')
            ->whereRaw("DATE_FORMAT(updated_at, '%Y-%m') = ?", [$competencia])
            ->count();

        // Taxa de conversão
        $taxaConversao = $leadsNovos > 0 
            ? round(($leadsConvertidos / $leadsNovos) * 100, 1) 
            : 0;

        // Oportunidades ganhas no mês para ticket médio
        $opsGanhasData = CrmOpportunity::where('status', 'won')
            ->whereRaw("DATE_FORMAT(won_at, '%Y-%m') = ?", [$competencia])
            ->selectRaw('COUNT(*) as qtd, SUM(value_estimated) as total')
            ->first();

        $ticketMedio = ($opsGanhasData->qtd ?? 0) > 0 
            ? ($opsGanhasData->total ?? 0) / $opsGanhasData->qtd 
            : 0;

        // Pipeline aberto (estoque - não depende de competência)
        $pipelineAberto = CrmOpportunity::where('status', 'open')
            ->sum('value_estimated') ?? 0;

        // Win rate no mês
        $opsGanhasMes = CrmOpportunity::where('status', 'won')
            ->whereRaw("DATE_FORMAT(won_at, '%Y-%m') = ?", [$competencia])
            ->count();
        
        $opsPerdidasMes = CrmOpportunity::where('status', 'lost')
            ->whereRaw("DATE_FORMAT(lost_at, '%Y-%m') = ?", [$competencia])
            ->count();

        $totalFechadas = $opsGanhasMes + $opsPerdidasMes;
        $winRate = $totalFechadas > 0 
            ? round(($opsGanhasMes / $totalFechadas) * 100, 1) 
            : 0;

        return [
            'taxa_conversao' => [
                'valor' => $taxaConversao,
                'label' => 'Taxa Conversão (mês)',
                'icon' => '📊',
                'cor' => 'orange',
                'formato' => 'percentual',
                'detalhe' => "{$leadsConvertidos} de {$leadsNovos} leads",
                'meta' => 0,
            ],
            'ticket_medio' => [
                'valor' => $ticketMedio,
                'label' => 'Ticket Médio',
                'icon' => '🎫',
                'cor' => 'blue',
                'formato' => 'moeda',
                'meta' => 0,
            ],
            'pipeline_aberto' => [
                'valor' => $pipelineAberto,
                'label' => 'Pipeline Aberto',
                'icon' => '📈',
                'cor' => 'purple',
                'formato' => 'moeda',
                'meta' => 0,
            ],
            'win_rate' => [
                'valor' => $winRate,
                'label' => 'Win Rate',
                'icon' => '🎯',
                'cor' => 'green',
                'formato' => 'percentual',
                'detalhe' => "{$opsGanhasMes} de {$totalFechadas} fechadas",
                'meta' => KpiMetaHelper::get('win_rate', $refDate->year, $refDate->month, 0),
            ]
        ];
    }

    /**
     * Série histórica de 12 meses para gráfico de linha
     */
    private function getSerie12Meses(int $ano, int $mes): array
    {
        $dados = [];
        $dataRef = Carbon::createFromDate($ano, $mes, 1);
        
        for ($i = 11; $i >= 0; $i--) {
            $dataLoop = $dataRef->copy()->subMonths($i);
            $competencia = $dataLoop->format('Y-m');
            $label = $this->getMesAbrev($dataLoop->month) . '/' . $dataLoop->format('y');
            
            // Leads novos no mês (usando created_at)
            $leadsNovos = Lead::whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$competencia])
                ->count();

            // Leads convertidos no mês (status='convertido' + updated_at)
            $leadsConvertidos = Lead::where('status', 'convertido')
                ->whereRaw("DATE_FORMAT(updated_at, '%Y-%m') = ?", [$competencia])
                ->count();

            // Oportunidades ganhas no mês
            $opsGanhas = CrmOpportunity::where('status', 'won')
                ->whereRaw("DATE_FORMAT(won_at, '%Y-%m') = ?", [$competencia])
                ->count();

            // Valor ganho no mês
            $valorGanho = CrmOpportunity::where('status', 'won')
                ->whereRaw("DATE_FORMAT(won_at, '%Y-%m') = ?", [$competencia])
                ->sum('value_estimated') ?? 0;

            $dados[] = [
                'competencia' => $competencia,
                'label' => $label,
                'leads_novos' => $leadsNovos,
                'leads_convertidos' => $leadsConvertidos,
                'oportunidades_ganhas' => $opsGanhas,
                'valor_ganho' => round($valorGanho, 2)
            ];
        }
        
        return $dados;
    }

    /**
     * Oportunidades por estágio para gráfico de barras
     */
    private function getOportunidadesPorEstagio(string $competencia): array
    {
        // Ganhas no mês
        $ganhas = CrmOpportunity::where('status', 'won')
            ->whereRaw("DATE_FORMAT(won_at, '%Y-%m') = ?", [$competencia])
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(value_estimated), 0) as valor')
            ->first();

        // Perdidas no mês
        $perdidas = CrmOpportunity::where('status', 'lost')
            ->whereRaw("DATE_FORMAT(lost_at, '%Y-%m') = ?", [$competencia])
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(value_estimated), 0) as valor')
            ->first();

        // Pipeline aberto (estoque atual)
        $pipeline = CrmOpportunity::where('status', 'open')
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(value_estimated), 0) as valor')
            ->first();

        return [
            'ganhas' => [
                'qtd' => $ganhas->qtd ?? 0,
                'valor' => round($ganhas->valor ?? 0, 2),
                'cor' => '#10b981' // green
            ],
            'perdidas' => [
                'qtd' => $perdidas->qtd ?? 0,
                'valor' => round($perdidas->valor ?? 0, 2),
                'cor' => '#ef4444' // red
            ],
            'pipeline' => [
                'qtd' => $pipeline->qtd ?? 0,
                'valor' => round($pipeline->valor ?? 0, 2),
                'cor' => '#8b5cf6' // purple
            ]
        ];
    }

    /**
     * Origem dos leads para gráfico donut
     * NOTA: Usa coluna 'origem_canal' da tabela leads (não existe coluna 'origem')
     */
    private function getOrigemLeads(string $competencia): array
    {
        // Buscar origens do mês selecionado
        $origens = Lead::whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$competencia])
            ->whereNotNull('origem_canal')
            ->selectRaw('origem_canal as origem, COUNT(*) as total')
            ->groupBy('origem_canal')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        // Se não houver dados no mês, pegar acumulado geral
        if (empty($origens)) {
            $origens = Lead::whereNotNull('origem_canal')
                ->selectRaw('origem_canal as origem, COUNT(*) as total')
                ->groupBy('origem_canal')
                ->orderByDesc('total')
                ->get()
                ->toArray();
        }

        // Cores para o gráfico
        $cores = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];
        
        foreach ($origens as $i => &$origem) {
            $origem['cor'] = $cores[$i % count($cores)];
        }

        return $origens;
    }

    /**
     * Top 10 clientes por quantidade de processos ativos
     */
    private function getTop10Clientes(): array
    {
        return DB::select("
            SELECT 
                c.id,
                c.nome,
                c.tipo,
                COUNT(p.id) as qtd_processos_ativos
            FROM clientes c
            INNER JOIN processos p ON p.cliente_id = c.id
            WHERE p.status = 'Ativo'
            GROUP BY c.id, c.nome, c.tipo
            ORDER BY qtd_processos_ativos DESC
            LIMIT 10
        ");
    }

    /**
     * Totais acumulados (para contexto)
     */
    public function getTotaisAcumulados(): array
    {
        return [
            'total_clientes' => Cliente::count(),
            'total_leads' => Lead::count(),
            'total_oportunidades' => CrmOpportunity::count(),
            'total_processos' => Processo::count(),
            'processos_ativos' => Processo::where('status', 'Ativo')->count()
        ];
    }

    /**
     * Limpar cache do dashboard
     */
    public function limparCache(?int $ano = null, ?int $mes = null): void
    {
        if ($ano && $mes) {
            Cache::forget("clientes_mercado_{$ano}_{$mes}");
        } else {
            // Limpar todos os caches de clientes_mercado
            for ($a = 2024; $a <= 2027; $a++) {
                for ($m = 1; $m <= 12; $m++) {
                    Cache::forget("clientes_mercado_{$a}_{$m}");
                }
            }
        }
    }

    /**
     * Nome completo do mês
     */
    private function getMesLabel(int $mes): string
    {
        $meses = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
        ];
        return $meses[$mes] ?? '';
    }

    /**
     * Abreviação do mês
     */
    private function getMesAbrev(int $mes): string
    {
        $meses = [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr',
            5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'
        ];
        return $meses[$mes] ?? '';
    }
}
