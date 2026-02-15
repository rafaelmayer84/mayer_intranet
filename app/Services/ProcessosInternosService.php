<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use App\Helpers\KpiMetaHelper;

/**
 * ProcessosInternosService
 *
 * Calcula todos os KPIs da perspectiva "Processos Internos" do BSC.
 * Segue o mesmo padrão do DashboardFinanceProdService e ClientesMercadoService:
 *   - Cálculo on-the-fly com cache TTL 300s
 *   - Filtros por período, responsável, grupo, área, tipo_atividade, status
 *   - Agrupamento temporal (dia/semana/mês) para gráficos de evolução
 *   - Comparação com período anterior
 *
 * Tabelas utilizadas:
 *   - processos (status, area_atuacao, proprietario_id/nome, grupo_responsavel)
 *   - atividades_datajuri (status, data_vencimento, data_conclusao, tipo_atividade, proprietario_id)
 *   - fases_processo (fase_atual, data_ultimo_andamento, dias_fase_ativa, tipo_fase)
 *   - andamentos_fase (data_andamento, processo_pasta, proprietario_id)
 *   - horas_trabalhadas_datajuri (data, total_hora_trabalhada, proprietario_id)
 */
class ProcessosInternosService
{
    /** Cache TTL em segundos (5 minutos, mesmo padrão das outras pages) */
    private const CACHE_TTL = 300;

    /* ================================================================== */
    /*  MÉTODO PRINCIPAL                                                  */
    /* ================================================================== */

    /**
     * Retorna todos os dados do dashboard num único array.
     *
     * @param  array  $filtros  Filtros vindos do Controller (querystring + session)
     * @return array
     */
    public function getDashboardData(array $filtros): array
    {
        $cacheKey = 'proc_internos_' . md5(json_encode($filtros));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filtros) {
            $periodo = $this->parsePeriodo($filtros);
            $periodoAnterior = $this->periodoAnterior($periodo);

            return [
                'filtros'           => $filtros,
                'periodo'           => $periodo,
                'cards'             => $this->getCards($periodo, $periodoAnterior, $filtros),
                'equipe'            => $this->getDesempenhoEquipe($periodo, $filtros),
                'evolucao_sla'      => $this->getEvolucao('sla', $periodo, $filtros),
                'evolucao_backlog'  => $this->getEvolucao('backlog', $periodo, $filtros),
                'evolucao_throughput' => $this->getEvolucao('throughput', $periodo, $filtros),
                'processos_por_fase' => $this->getProcessosPorFase($filtros),
                'top_riscos'        => $this->getTopRiscos($filtros),
                'responsaveis'      => $this->getResponsaveis(),
                'areas'             => $this->getAreas(),
                'tipos_atividade'   => $this->getTiposAtividade(),
            ];
        });
    }

    /* ================================================================== */
    /*  PARSE DE PERÍODO                                                  */
    /* ================================================================== */

    /**
     * Converte o filtro "periodo" em datas inicio/fim.
     * Opções: 7, 15, 30, mes, trimestre, custom (com data_inicio/data_fim).
     */
    private function parsePeriodo(array $filtros): array
    {
        $tipo = $filtros['periodo'] ?? '30';

        switch ($tipo) {
            case '7':
                return ['inicio' => now()->subDays(7)->startOfDay(), 'fim' => now()->endOfDay(), 'tipo' => '7'];
            case '15':
                return ['inicio' => now()->subDays(15)->startOfDay(), 'fim' => now()->endOfDay(), 'tipo' => '15'];
            case '30':
                return ['inicio' => now()->subDays(30)->startOfDay(), 'fim' => now()->endOfDay(), 'tipo' => '30'];
            case 'mes':
                return ['inicio' => now()->startOfMonth(), 'fim' => now()->endOfDay(), 'tipo' => 'mes'];
            case 'trimestre':
                return ['inicio' => now()->firstOfQuarter(), 'fim' => now()->endOfDay(), 'tipo' => 'trimestre'];
            case 'custom':
                $ini = Carbon::parse($filtros['data_inicio'] ?? now()->subDays(30))->startOfDay();
                $fim = Carbon::parse($filtros['data_fim'] ?? now())->endOfDay();
                return ['inicio' => $ini, 'fim' => $fim, 'tipo' => 'custom'];
            default:
                return ['inicio' => now()->subDays(30)->startOfDay(), 'fim' => now()->endOfDay(), 'tipo' => '30'];
        }
    }

    /**
     * Calcula o período anterior com mesma duração (para comparação).
     */
    private function periodoAnterior(array $periodo): array
    {
        $dias = $periodo['inicio']->diffInDays($periodo['fim']);
        return [
            'inicio' => $periodo['inicio']->copy()->subDays($dias + 1),
            'fim'    => $periodo['inicio']->copy()->subDay(),
        ];
    }

    /* ================================================================== */
    /*  CARDS (KPIs PRINCIPAIS)                                           */
    /* ================================================================== */

    /**
     * Calcula os 6 cards de KPIs principais.
     * Cada card retorna: valor, meta (se houver), variacao (vs período anterior), cor.
     */
    private function getCards(array $periodo, array $periodoAnterior, array $filtros): array
    {
        $where = $this->buildWhereAtividades($filtros);
        $whereProc = $this->buildWhereProcessos($filtros);

        // ── SLA (% de atividades concluídas no prazo) ──
        $slaAtual = $this->calcSla($periodo, $where);
        $slaAnterior = $this->calcSla($periodoAnterior, $where);

        // ── Backlog Vencido (atividades abertas com data_vencimento < hoje) ──
        $backlog = $this->calcBacklog($where);

        // ── WIP (atividades/tarefas em andamento) ──
        $wip = $this->calcWip($where);

        // ── Sem Andamento > X dias ──
        $diasLimite = (int) ($filtros['dias_sem_andamento'] ?? 30);
        $semAndamento = $this->calcSemAndamento($diasLimite, $whereProc);

        // ── Throughput (concluídas no período) ──
        $throughputAtual = $this->calcThroughput($periodo, $where);
        $throughputAnterior = $this->calcThroughput($periodoAnterior, $where);

        // ── Horas Trabalhadas ──
        $horasAtual = $this->calcHoras($periodo, $filtros);
        $horasAnterior = $this->calcHoras($periodoAnterior, $filtros);

        $_kpiAno = (int) $periodo['fim']->year;
        $_kpiMes = (int) $periodo['fim']->month;

        return [
            'sla' => [
                'id'        => 'sla',
                'titulo'    => 'SLA (no prazo)',
                'valor'     => $slaAtual,
                'formato'   => 'percent',
                'variacao'  => $this->variacao($slaAtual, $slaAnterior),
                'icon'      => '⏱️',
                'accent'    => $slaAtual >= 80 ? 'green' : ($slaAtual >= 60 ? 'yellow' : 'red'),
                'meta'      => KpiMetaHelper::get('sla_percentual', $_kpiAno, $_kpiMes, 80),
            ],
            'backlog' => [
                'id'        => 'backlog',
                'titulo'    => 'Backlog Vencido',
                'valor'     => $backlog['total'],
                'formato'   => 'integer',
                'subtitulo' => 'Média: ' . number_format($backlog['media_dias'], 1) . ' dias atraso',
                'icon'      => '📋',
                'accent'    => $backlog['total'] == 0 ? 'green' : ($backlog['total'] <= 10 ? 'yellow' : 'red'),
                'meta'      => KpiMetaHelper::get('backlog', $_kpiAno, $_kpiMes, 0),
            ],
            'wip' => [
                'id'        => 'wip',
                'titulo'    => 'WIP (em andamento)',
                'valor'     => $wip,
                'formato'   => 'integer',
                'icon'      => '🔄',
                'accent'    => 'blue',
                'meta'      => 0,
            ],
            'sem_andamento' => [
                'id'        => 'sem_andamento',
                'titulo'    => "Sem andamento >{$diasLimite}d",
                'valor'     => $semAndamento,
                'formato'   => 'integer',
                'icon'      => '⚠️',
                'accent'    => $semAndamento == 0 ? 'green' : ($semAndamento <= 5 ? 'yellow' : 'red'),
                'meta'      => KpiMetaHelper::get('sem_movimentacao', $_kpiAno, $_kpiMes, 0),
            ],
            'throughput' => [
                'id'        => 'throughput',
                'titulo'    => 'Throughput',
                'valor'     => $throughputAtual,
                'formato'   => 'integer',
                'variacao'  => $this->variacao($throughputAtual, $throughputAnterior),
                'icon'      => '✅',
                'accent'    => 'green',
                'meta'      => KpiMetaHelper::get('throughput', $_kpiAno, $_kpiMes, 0),
            ],
            'horas' => [
                'id'        => 'horas',
                'titulo'    => 'Horas Trabalhadas',
                'valor'     => $horasAtual,
                'formato'   => 'decimal',
                'variacao'  => $this->variacao($horasAtual, $horasAnterior),
                'icon'      => '🕐',
                'accent'    => 'purple',
                'meta'      => KpiMetaHelper::get('horas_trabalhadas', $_kpiAno, $_kpiMes, 0),
            ],
        ];
    }

    /* ================================================================== */
    /*  CÁLCULOS INDIVIDUAIS DOS KPIs                                     */
    /* ================================================================== */

    /**
     * SLA = (concluídas no prazo / total concluídas) × 100
     * "No prazo" = data_conclusao <= data_vencimento (ou data_prazo_fatal)
     */
    private function calcSla(array $periodo, array $where): float
    {
        $query = DB::table('atividades_datajuri')
            ->whereNotNull('data_conclusao')
            ->whereBetween('data_conclusao', [$periodo['inicio'], $periodo['fim']]);

        $this->applyWhereAtividades($query, $where);

        $total = (clone $query)->count();
        if ($total === 0) return 0;

        $noPrazo = (clone $query)
            ->whereNotNull('data_vencimento')
            ->whereColumn('data_conclusao', '<=', 'data_vencimento')
            ->count();

        // Atividades sem data_vencimento: considerar como no prazo (sem SLA definido)
        $semPrazo = (clone $query)->whereNull('data_vencimento')->count();

        return round((($noPrazo + $semPrazo) / $total) * 100, 1);
    }

    /**
     * Backlog = atividades vencidas que ainda estão abertas.
     * Retorna total e média de dias de atraso.
     */
    private function calcBacklog(array $where): array
    {
        $query = DB::table('atividades_datajuri')
            ->whereNull('data_conclusao')
            ->whereNotNull('data_vencimento')
            ->where('data_vencimento', '<', now())
            ->where(function ($q) {
                $q->where('status', '!=', 'Concluída')
                  ->orWhereNull('status');
            });

        $this->applyWhereAtividades($query, $where);

        $total = $query->count();
        $mediaDias = $total > 0
            ? (float) $query->selectRaw('AVG(DATEDIFF(NOW(), data_vencimento)) as avg_atraso')->value('avg_atraso')
            : 0;

        return [
            'total'      => $total,
            'media_dias' => round($mediaDias, 1),
        ];
    }

    /**
     * WIP = atividades em andamento (não concluídas, não canceladas).
     */
    private function calcWip(array $where): int
    {
        $query = DB::table('atividades_datajuri')
            ->whereNull('data_conclusao')
            ->where(function ($q) {
                $q->whereNotIn('status', ['Concluída', 'Cancelada', 'Cancelado'])
                  ->orWhereNull('status');
            });

        $this->applyWhereAtividades($query, $where);

        return $query->count();
    }

    /**
     * Processos com fase ativa cuja data_ultimo_andamento é anterior a X dias.
     */
    private function calcSemAndamento(int $dias, array $whereProc): int
    {
        $query = DB::table('fases_processo as fp')
            ->join('processos as p', 'fp.processo_pasta', '=', 'p.pasta')
            ->where('fp.fase_atual', 1)
            ->where('p.status', 'Ativo')
            ->where(function ($q) use ($dias) {
                $q->where('fp.data_ultimo_andamento', '<', now()->subDays($dias))
                  ->orWhereNull('fp.data_ultimo_andamento');
            });

        $this->applyWhereProcessos($query, $whereProc, 'p');

        return $query->count();
    }

    /**
     * Throughput = atividades concluídas no período.
     */
    private function calcThroughput(array $periodo, array $where): int
    {
        $query = DB::table('atividades_datajuri')
            ->whereNotNull('data_conclusao')
            ->whereBetween('data_conclusao', [$periodo['inicio'], $periodo['fim']]);

        $this->applyWhereAtividades($query, $where);

        return $query->count();
    }

    /**
     * Total de horas trabalhadas no período.
     */
    private function calcHoras(array $periodo, array $filtros): float
    {
        $query = DB::table('horas_trabalhadas_datajuri')
            ->whereBetween('data', [$periodo['inicio']->toDateString(), $periodo['fim']->toDateString()]);

        if (!empty($filtros['responsavel'])) {
            $query->whereIn('proprietario_id', (array) $filtros['responsavel']);
        }

        return round((float) $query->sum('total_hora_trabalhada'), 1);
    }

    /* ================================================================== */
    /*  TABELA: DESEMPENHO DA EQUIPE                                      */
    /* ================================================================== */

    /**
     * Retorna métricas agrupadas por responsável (proprietario_id/nome).
     * Colunas: nome, sla, backlog, wip, throughput, horas
     */
    private function getDesempenhoEquipe(array $periodo, array $filtros): array
    {
        $where = $this->buildWhereAtividades($filtros);

        // Pegar lista de responsáveis ativos no período
        $responsaveis = DB::table('atividades_datajuri')
            ->select('proprietario_id')
            ->selectRaw("MAX(COALESCE(responsavel_nome, '')) as nome")
            ->whereNotNull('proprietario_id')
            ->where(function ($q) use ($periodo) {
                $q->whereBetween('data_conclusao', [$periodo['inicio'], $periodo['fim']])
                  ->orWhere(function ($q2) {
                      $q2->whereNull('data_conclusao')
                         ->where(function ($q3) {
                             $q3->whereNotIn('status', ['Concluída', 'Cancelada', 'Cancelado'])
                                ->orWhereNull('status');
                         });
                  });
            });

        $this->applyWhereAtividades($responsaveis, $where);
        $responsaveis = $responsaveis->groupBy('proprietario_id')->get();

        $equipe = [];
        foreach ($responsaveis as $r) {
            $filtroResp = array_merge($filtros, ['responsavel' => [$r->proprietario_id]]);
            $whereResp = $this->buildWhereAtividades($filtroResp);

            // SLA individual
            $sla = $this->calcSla($periodo, $whereResp);

            // Backlog individual
            $backlog = $this->calcBacklog($whereResp);

            // WIP individual
            $wip = $this->calcWip($whereResp);

            // Throughput individual
            $throughput = $this->calcThroughput($periodo, $whereResp);

            // Horas
            $horas = $this->calcHoras($periodo, $filtroResp);

            // Resolver nome: usar responsavel_nome, ou buscar do proprietario
            $nome = $r->nome;
            if (empty($nome)) {
                $nome = DB::table('processos')
                    ->where('proprietario_id', $r->proprietario_id)
                    ->value('proprietario_nome') ?? "ID {$r->proprietario_id}";
            }

            $equipe[] = [
                'proprietario_id' => $r->proprietario_id,
                'nome'            => $nome,
                'sla'             => $sla,
                'backlog'         => $backlog['total'],
                'wip'             => $wip,
                'throughput'      => $throughput,
                'horas'           => $horas,
            ];
        }

        // Ordenar por throughput desc
        usort($equipe, fn($a, $b) => $b['throughput'] <=> $a['throughput']);

        return $equipe;
    }

    /* ================================================================== */
    /*  GRÁFICOS DE EVOLUÇÃO                                              */
    /* ================================================================== */

    /**
     * Retorna série temporal para gráficos de evolução.
     * Agrupa por dia/semana/mês conforme filtro agrupamento_evolucao.
     *
     * @param  string  $metrica  'sla'|'backlog'|'throughput'
     * @param  array   $periodo
     * @param  array   $filtros
     * @return array   ['labels' => [...], 'data' => [...], 'data_anterior' => [...]]
     */
    private function getEvolucao(string $metrica, array $periodo, array $filtros): array
    {
        $agrupamento = $filtros['agrupamento_evolucao'] ?? 'semana';
        $buckets = $this->gerarBuckets($periodo, $agrupamento);
        $compararAnterior = ($filtros['comparar_periodo_anterior'] ?? '0') === '1';

        $where = $this->buildWhereAtividades($filtros);
        $data = [];
        $dataAnterior = [];

        $periodoAnterior = $this->periodoAnterior($periodo);
        $bucketsAnteriores = $compararAnterior ? $this->gerarBuckets($periodoAnterior, $agrupamento) : [];

        foreach ($buckets as $idx => $bucket) {
            $bucketPeriodo = ['inicio' => $bucket['inicio'], 'fim' => $bucket['fim']];

            switch ($metrica) {
                case 'sla':
                    $data[] = $this->calcSla($bucketPeriodo, $where);
                    break;
                case 'backlog':
                    // Para evolução do backlog, contamos vencidas abertas no momento do fim do bucket
                    $data[] = $this->calcBacklogSnapshot($bucket['fim'], $where);
                    break;
                case 'throughput':
                    $data[] = $this->calcThroughput($bucketPeriodo, $where);
                    break;
            }

            if ($compararAnterior && isset($bucketsAnteriores[$idx])) {
                $bucketAnt = ['inicio' => $bucketsAnteriores[$idx]['inicio'], 'fim' => $bucketsAnteriores[$idx]['fim']];
                switch ($metrica) {
                    case 'sla':
                        $dataAnterior[] = $this->calcSla($bucketAnt, $where);
                        break;
                    case 'backlog':
                        $dataAnterior[] = $this->calcBacklogSnapshot($bucketsAnteriores[$idx]['fim'], $where);
                        break;
                    case 'throughput':
                        $dataAnterior[] = $this->calcThroughput($bucketAnt, $where);
                        break;
                }
            }
        }

        return [
            'labels'        => array_map(fn($b) => $b['label'], $buckets),
            'data'          => $data,
            'data_anterior' => $dataAnterior,
        ];
    }

    /**
     * Snapshot do backlog num ponto no tempo.
     * Conta atividades que na data $ate estavam vencidas e ainda não concluídas.
     */
    private function calcBacklogSnapshot(Carbon $ate, array $where): int
    {
        $query = DB::table('atividades_datajuri')
            ->whereNotNull('data_vencimento')
            ->where('data_vencimento', '<', $ate)
            ->where(function ($q) use ($ate) {
                $q->whereNull('data_conclusao')
                  ->orWhere('data_conclusao', '>', $ate);
            });

        $this->applyWhereAtividades($query, $where);

        return $query->count();
    }

    /**
     * Gera array de buckets temporais (dia/semana/mês).
     */
    private function gerarBuckets(array $periodo, string $agrupamento): array
    {
        $buckets = [];
        $cursor = $periodo['inicio']->copy();
        $fim = $periodo['fim']->copy();

        while ($cursor <= $fim) {
            switch ($agrupamento) {
                case 'dia':
                    $bucketFim = $cursor->copy()->endOfDay();
                    $label = $cursor->format('d/m');
                    $next = $cursor->copy()->addDay();
                    break;
                case 'semana':
                    $bucketFim = $cursor->copy()->endOfWeek(Carbon::SUNDAY);
                    if ($bucketFim > $fim) $bucketFim = $fim->copy();
                    $label = $cursor->format('d/m') . '-' . $bucketFim->format('d/m');
                    $next = $bucketFim->copy()->addDay();
                    break;
                case 'mes':
                default:
                    $bucketFim = $cursor->copy()->endOfMonth();
                    if ($bucketFim > $fim) $bucketFim = $fim->copy();
                    $label = $cursor->format('M/y');
                    $next = $cursor->copy()->addMonth()->startOfMonth();
                    break;
            }

            $buckets[] = [
                'inicio' => $cursor->copy(),
                'fim'    => $bucketFim,
                'label'  => $label,
            ];

            $cursor = $next;
        }

        return $buckets;
    }

    /* ================================================================== */
    /*  PROCESSOS PARADOS POR FASE                                        */
    /* ================================================================== */

    /**
     * Agrupa processos parados (sem andamento > 30d) por tipo de fase.
     */
    private function getProcessosPorFase(array $filtros): array
    {
        $diasLimite = (int) ($filtros['dias_sem_andamento'] ?? 30);
        $whereProc = $this->buildWhereProcessos($filtros);

        $query = DB::table('fases_processo as fp')
            ->join('processos as p', 'fp.processo_pasta', '=', 'p.pasta')
            ->select('fp.tipo_fase')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('AVG(fp.dias_fase_ativa) as media_dias')
            ->where('fp.fase_atual', 1)
            ->where('p.status', 'Ativo')
            ->where(function ($q) use ($diasLimite) {
                $q->where('fp.data_ultimo_andamento', '<', now()->subDays($diasLimite))
                  ->orWhereNull('fp.data_ultimo_andamento');
            })
            ->groupBy('fp.tipo_fase')
            ->orderByDesc('total');

        $this->applyWhereProcessos($query, $whereProc, 'p');

        return $query->get()->map(function ($row) {
            return [
                'fase'       => $row->tipo_fase ?: 'Não informada',
                'total'      => $row->total,
                'media_dias' => round($row->media_dias ?? 0, 0),
            ];
        })->toArray();
    }

    /* ================================================================== */
    /*  TOP 20 RISCOS                                                     */
    /* ================================================================== */

    /**
     * Top 20 processos ativos com maior risco:
     * Critério = (dias_fase_ativa * valor_provisionado) desc + processos sem andamento
     */
    private function getTopRiscos(array $filtros): array
    {
        $whereProc = $this->buildWhereProcessos($filtros);

        $query = DB::table('processos as p')
            ->leftJoin('fases_processo as fp', function ($join) {
                $join->on('fp.processo_pasta', '=', 'p.pasta')
                     ->where('fp.fase_atual', 1);
            })
            ->select([
                'p.id',
                'p.pasta',
                'p.numero',
                'p.status',
                'p.proprietario_nome as responsavel',
                'p.valor_provisionado',
                'p.possibilidade',
                'p.area_atuacao',
                'fp.tipo_fase',
                'fp.dias_fase_ativa',
                'fp.data_ultimo_andamento',
            ])
            ->selectRaw('DATEDIFF(NOW(), COALESCE(fp.data_ultimo_andamento, p.created_at)) as dias_parado')
            ->selectRaw('COALESCE(p.valor_provisionado, 0) * COALESCE(fp.dias_fase_ativa, DATEDIFF(NOW(), p.created_at)) as score_risco')
            ->where('p.status', 'Ativo')
            ->orderByDesc('score_risco')
            ->limit(20);

        $this->applyWhereProcessos($query, $whereProc, 'p');

        return $query->get()->map(function ($row) {
            return [
                'id'                    => $row->id,
                'pasta'                 => $row->pasta,
                'numero'                => $row->numero,
                'responsavel'           => $row->responsavel,
                'valor_provisionado'    => $row->valor_provisionado,
                'possibilidade'         => $row->possibilidade,
                'area'                  => $row->area_atuacao,
                'fase'                  => $row->tipo_fase,
                'dias_fase_ativa'       => $row->dias_fase_ativa,
                'data_ultimo_andamento' => $row->data_ultimo_andamento,
                'dias_parado'           => $row->dias_parado,
                'score_risco'           => $row->score_risco,
            ];
        })->toArray();
    }

    /* ================================================================== */
    /*  DRILLDOWN (lista filtrada para clique em card)                     */
    /* ================================================================== */

    /**
     * Retorna lista paginada para drilldown de um KPI específico.
     *
     * @param  string  $tipo     'sla_ok'|'sla_nok'|'backlog'|'wip'|'sem_andamento'|'throughput'
     * @param  array   $filtros
     * @param  int     $page
     * @param  int     $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getDrilldown(string $tipo, array $filtros, int $page = 1, int $perPage = 25)
    {
        $periodo = $this->parsePeriodo($filtros);
        $where = $this->buildWhereAtividades($filtros);
        $whereProc = $this->buildWhereProcessos($filtros);

        switch ($tipo) {
            case 'backlog':
                $query = DB::table('atividades_datajuri as a')
                    ->leftJoin('processos as p', 'a.processo_pasta', '=', 'p.pasta')
                    ->select([
                        'a.id', 'a.datajuri_id', 'a.status', 'a.tipo_atividade',
                        'a.data_vencimento', 'a.responsavel_nome',
                        'a.processo_pasta', 'p.numero as processo_numero',
                        'p.proprietario_nome as responsavel_processo',
                    ])
                    ->selectRaw('DATEDIFF(NOW(), a.data_vencimento) as dias_atraso')
                    ->whereNull('a.data_conclusao')
                    ->whereNotNull('a.data_vencimento')
                    ->where('a.data_vencimento', '<', now())
                    ->orderByDesc('dias_atraso');
                $this->applyWhereAtividades($query, $where, 'a');
                break;

            case 'wip':
                $query = DB::table('atividades_datajuri as a')
                    ->leftJoin('processos as p', 'a.processo_pasta', '=', 'p.pasta')
                    ->select([
                        'a.id', 'a.datajuri_id', 'a.status', 'a.tipo_atividade',
                        'a.data_hora', 'a.data_vencimento', 'a.responsavel_nome',
                        'a.processo_pasta', 'p.numero as processo_numero',
                    ])
                    ->whereNull('a.data_conclusao')
                    ->where(function ($q) {
                        $q->whereNotIn('a.status', ['Concluída', 'Cancelada', 'Cancelado'])
                          ->orWhereNull('a.status');
                    })
                    ->orderBy('a.data_vencimento');
                $this->applyWhereAtividades($query, $where, 'a');
                break;

            case 'sem_andamento':
                $diasLimite = (int) ($filtros['dias_sem_andamento'] ?? 30);
                $query = DB::table('fases_processo as fp')
                    ->join('processos as p', 'fp.processo_pasta', '=', 'p.pasta')
                    ->select([
                        'p.id', 'p.pasta', 'p.numero', 'p.status',
                        'p.proprietario_nome as responsavel',
                        'fp.tipo_fase', 'fp.data_ultimo_andamento',
                        'fp.dias_fase_ativa',
                    ])
                    ->selectRaw('DATEDIFF(NOW(), COALESCE(fp.data_ultimo_andamento, p.created_at)) as dias_parado')
                    ->where('fp.fase_atual', 1)
                    ->where('p.status', 'Ativo')
                    ->where(function ($q) use ($diasLimite) {
                        $q->where('fp.data_ultimo_andamento', '<', now()->subDays($diasLimite))
                          ->orWhereNull('fp.data_ultimo_andamento');
                    })
                    ->orderByDesc('dias_parado');
                $this->applyWhereProcessos($query, $whereProc, 'p');
                break;

            case 'throughput':
                $query = DB::table('atividades_datajuri as a')
                    ->leftJoin('processos as p', 'a.processo_pasta', '=', 'p.pasta')
                    ->select([
                        'a.id', 'a.datajuri_id', 'a.status', 'a.tipo_atividade',
                        'a.data_conclusao', 'a.responsavel_nome',
                        'a.processo_pasta', 'p.numero as processo_numero',
                    ])
                    ->whereNotNull('a.data_conclusao')
                    ->whereBetween('a.data_conclusao', [$periodo['inicio'], $periodo['fim']])
                    ->orderByDesc('a.data_conclusao');
                $this->applyWhereAtividades($query, $where, 'a');
                break;

            default:
                $query = DB::table('atividades_datajuri as a')
                    ->select('a.*')
                    ->limit(0);
                break;
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /* ================================================================== */
    /*  FILTROS AUXILIARES (WHERE BUILDERS)                                */
    /* ================================================================== */

    /**
     * Monta array de condições WHERE para tabela atividades_datajuri.
     */
    private function buildWhereAtividades(array $filtros): array
    {
        return [
            'responsavel'    => array_filter((array) ($filtros['responsavel'] ?? [])),
            'tipo_atividade' => $filtros['tipo_atividade'] ?? null,
        ];
    }

    /**
     * Monta array de condições WHERE para tabela processos.
     */
    private function buildWhereProcessos(array $filtros): array
    {
        return [
            'responsavel'       => array_filter((array) ($filtros['responsavel'] ?? [])),
            'area'              => $filtros['area'] ?? null,
            'grupo_responsavel' => array_filter((array) ($filtros['grupo'] ?? [])),
            'status_processo'   => $filtros['status_processo'] ?? null,
        ];
    }

    /**
     * Aplica condições WHERE na query da tabela atividades_datajuri.
     */
    private function applyWhereAtividades($query, array $where, string $alias = ''): void
    {
        $col = fn(string $c) => $alias ? "{$alias}.{$c}" : $c;

        if (!empty($where['responsavel'])) {
            $query->whereIn($col('proprietario_id'), $where['responsavel']);
        }
        if (!empty($where['tipo_atividade'])) {
            $query->where($col('tipo_atividade'), $where['tipo_atividade']);
        }
    }

    /**
     * Aplica condições WHERE na query da tabela processos.
     */
    private function applyWhereProcessos($query, array $where, string $alias = 'p'): void
    {
        if (!empty($where['responsavel'])) {
            $query->whereIn("{$alias}.proprietario_id", $where['responsavel']);
        }
        if (!empty($where['area'])) {
            $query->where("{$alias}.area_atuacao", $where['area']);
        }
        if (!empty($where['grupo_responsavel'])) {
            $query->whereIn("{$alias}.grupo_responsavel", $where['grupo_responsavel']);
        }
        if (!empty($where['status_processo'])) {
            $query->where("{$alias}.status", $where['status_processo']);
        }
    }

    /* ================================================================== */
    /*  HELPERS                                                           */
    /* ================================================================== */

    /** Calcula variação percentual entre dois valores. */
    private function variacao(float $atual, float $anterior): ?float
    {
        if ($anterior == 0) return $atual > 0 ? 100.0 : null;
        return round((($atual - $anterior) / $anterior) * 100, 1);
    }

    /** Lista de responsáveis distintos (para filtro select). */
    private function getResponsaveis(): array
    {
        return DB::table('processos')
            ->select('proprietario_id as id', 'proprietario_nome as nome')
            ->whereNotNull('proprietario_id')
            ->whereNotNull('proprietario_nome')
            ->where('status', 'Ativo')
            ->groupBy('proprietario_id', 'proprietario_nome')
            ->orderBy('proprietario_nome')
            ->get()
            ->toArray();
    }

    /** Lista de áreas de atuação distintas (para filtro select). */
    private function getAreas(): array
    {
        return DB::table('processos')
            ->select('area_atuacao as area')
            ->whereNotNull('area_atuacao')
            ->groupBy('area_atuacao')
            ->orderBy('area_atuacao')
            ->pluck('area')
            ->toArray();
    }

    /** Lista de tipos de atividade distintos (para filtro select). */
    private function getTiposAtividade(): array
    {
        return DB::table('atividades_datajuri')
            ->select('tipo_atividade')
            ->whereNotNull('tipo_atividade')
            ->groupBy('tipo_atividade')
            ->orderBy('tipo_atividade')
            ->pluck('tipo_atividade')
            ->toArray();
    }
}
