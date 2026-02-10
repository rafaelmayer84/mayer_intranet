<?php

namespace App\Services;

use App\Models\{Cliente, Lead, Oportunidade, Processo};
use Illuminate\Support\Facades\{DB, Cache};
use Carbon\Carbon;

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
 *    - Ganha no mês: estagio='ganha' AND DATE_FORMAT(data_fechamento,'%Y-%m')=competência
 *    - Valor ganho: SUM(valor) das ganhas no mês
 *    - Pipeline aberto: SUM(valor) WHERE estagio IN ('prospectando','qualificacao','proposta','negociacao')
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
    private const ESTAGIOS_PIPELINE = ['prospectando', 'qualificacao', 'proposta', 'negociacao'];

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
        // Leads novos no mês (usando created_at)
        $leadsNovos = Lead::whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$competencia])
            ->count();

        // Oportunidades ganhas no mês
        $opsGanhas = Oportunidade::where('estagio', 'ganha')
            ->whereRaw("DATE_FORMAT(data_fechamento, '%Y-%m') = ?", [$competencia])
            ->count();

        // Clientes ativos (estoque - processos com status Ativo)
        $clientesAtivos = Processo::where('status', 'Ativo')
            ->whereNotNull('cliente_id')
            ->distinct('cliente_id')
            ->count('cliente_id');

        // Valor ganho no mês
        $valorGanho = Oportunidade::where('estagio', 'ganha')
            ->whereRaw("DATE_FORMAT(data_fechamento, '%Y-%m') = ?", [$competencia])
            ->sum('valor') ?? 0;

        return [
            'leads_novos' => [
                'valor' => $leadsNovos,
                'label' => 'Leads Novos',
                'icon' => '👥',
                'cor' => 'blue',
                'formato' => 'numero'
            ],
            'oportunidades_ganhas' => [
                'valor' => $opsGanhas,
                'label' => 'Oportunidades Ganhas',
                'icon' => '🏆',
                'cor' => 'green',
                'formato' => 'numero'
            ],
            'clientes_ativos' => [
                'valor' => $clientesAtivos,
                'label' => 'Clientes Ativos (estoque)',
                'icon' => '🏢',
                'cor' => 'purple',
                'formato' => 'numero'
            ],
            'valor_ganho' => [
                'valor' => $valorGanho,
                'label' => 'Valor Ganho',
                'icon' => '💰',
                'cor' => 'green',
                'formato' => 'moeda'
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
        $opsGanhasData = Oportunidade::where('estagio', 'ganha')
            ->whereRaw("DATE_FORMAT(data_fechamento, '%Y-%m') = ?", [$competencia])
            ->selectRaw('COUNT(*) as qtd, SUM(valor) as total')
            ->first();

        $ticketMedio = ($opsGanhasData->qtd ?? 0) > 0 
            ? ($opsGanhasData->total ?? 0) / $opsGanhasData->qtd 
            : 0;

        // Pipeline aberto (estoque - não depende de competência)
        $pipelineAberto = Oportunidade::whereIn('estagio', self::ESTAGIOS_PIPELINE)
            ->sum('valor') ?? 0;

        // Win rate no mês
        $opsGanhasMes = Oportunidade::where('estagio', 'ganha')
            ->whereRaw("DATE_FORMAT(data_fechamento, '%Y-%m') = ?", [$competencia])
            ->count();
        
        $opsPerdidasMes = Oportunidade::where('estagio', 'perdida')
            ->whereRaw("DATE_FORMAT(data_fechamento, '%Y-%m') = ?", [$competencia])
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
                'detalhe' => "{$leadsConvertidos} de {$leadsNovos} leads"
            ],
            'ticket_medio' => [
                'valor' => $ticketMedio,
                'label' => 'Ticket Médio',
                'icon' => '🎫',
                'cor' => 'blue',
                'formato' => 'moeda'
            ],
            'pipeline_aberto' => [
                'valor' => $pipelineAberto,
                'label' => 'Pipeline Aberto',
                'icon' => '📈',
                'cor' => 'purple',
                'formato' => 'moeda'
            ],
            'win_rate' => [
                'valor' => $winRate,
                'label' => 'Win Rate',
                'icon' => '🎯',
                'cor' => 'green',
                'formato' => 'percentual',
                'detalhe' => "{$opsGanhasMes} de {$totalFechadas} fechadas"
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
            $opsGanhas = Oportunidade::where('estagio', 'ganha')
                ->whereRaw("DATE_FORMAT(data_fechamento, '%Y-%m') = ?", [$competencia])
                ->count();

            // Valor ganho no mês
            $valorGanho = Oportunidade::where('estagio', 'ganha')
                ->whereRaw("DATE_FORMAT(data_fechamento, '%Y-%m') = ?", [$competencia])
                ->sum('valor') ?? 0;

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
        $ganhas = Oportunidade::where('estagio', 'ganha')
            ->whereRaw("DATE_FORMAT(data_fechamento, '%Y-%m') = ?", [$competencia])
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(valor), 0) as valor')
            ->first();

        // Perdidas no mês
        $perdidas = Oportunidade::where('estagio', 'perdida')
            ->whereRaw("DATE_FORMAT(data_fechamento, '%Y-%m') = ?", [$competencia])
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(valor), 0) as valor')
            ->first();

        // Pipeline aberto (estoque atual)
        $pipeline = Oportunidade::whereIn('estagio', self::ESTAGIOS_PIPELINE)
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(valor), 0) as valor')
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
            'total_oportunidades' => Oportunidade::count(),
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
