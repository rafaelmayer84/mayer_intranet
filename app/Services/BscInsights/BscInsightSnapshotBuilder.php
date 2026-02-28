<?php

namespace App\Services\BscInsights;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BscInsightSnapshotBuilder
{
    private Carbon $inicio;
    private Carbon $fim;
    private int $months;
    private array $errors = [];

    public function __construct(?string $inicio = null, ?string $fim = null)
    {
        $this->months = (int) config('bsc_insights.snapshot_months', 6);
        $this->fim    = $fim ? Carbon::parse($fim) : Carbon::now();
        $this->inicio = $inicio ? Carbon::parse($inicio) : $this->fim->copy()->subMonths($this->months)->startOfMonth();
    }

    /**
     * Gera snapshot completo com todas as métricas.
     */
    public function build(): array
    {
        $snapshot = [
            'meta' => [
                'periodo_inicio' => $this->inicio->toDateString(),
                'periodo_fim'    => $this->fim->toDateString(),
                'gerado_em'      => Carbon::now()->toIso8601String(),
                'meses'          => $this->months,
            ],
            'finance'       => $this->blocoFinance(),
            'inadimplencia' => $this->blocoInadimplencia(),
            'clientes'      => $this->blocoClientes(),
            'leads'         => $this->blocoLeads(),
            'crm'           => $this->blocoCrm(),
            'processos'     => $this->blocoProcessos(),
            'atendimento'   => $this->blocoAtendimento(),
            'tickets'       => $this->blocoTickets(),
            'horas'         => $this->blocoHoras(),
            'gdp'           => $this->blocoGdp(),
            '_errors'       => $this->errors,
        ];

        return $snapshot;
    }

    public function getInicio(): string
    {
        return $this->inicio->toDateString();
    }

    public function getFim(): string
    {
        return $this->fim->toDateString();
    }

    // ========================================================================
    // BLOCO: FINANCEIRO
    // ========================================================================

    private function blocoFinance(): array
    {
        return $this->safe('finance', function () {
            $meses = $this->seriesMensal();
            $receitaPF = [];
            $receitaPJ = [];
            $receitaTotal = [];
            $despesas  = [];
            $deducoes  = [];
            $resultado = [];

            $calc = app(FinanceiroCalculatorService::class);

            foreach ($meses as $m) {
                $dre = $calc->dre($m['ano'], $m['mes']);
                $receitaPF[$m['key']]    = $dre['receita_pf'];
                $receitaPJ[$m['key']]    = $dre['receita_pj'];
                $receitaTotal[$m['key']] = $dre['receita_total'];
                $despesas[$m['key']]     = $dre['despesas'];
                $deducoes[$m['key']]     = $dre['deducoes'];
                $resultado[$m['key']]    = $dre['resultado'];
            }

            $lastKey = end($meses)['key'];
            $prevKey = count($meses) >= 2 ? $meses[count($meses) - 2]['key'] : null;

            return [
                'receita_pf_mensal'    => $receitaPF,
                'receita_pj_mensal'    => $receitaPJ,
                'receita_total_mensal' => $receitaTotal,
                'despesas_mensal'      => $despesas,
                'deducoes_mensal'      => $deducoes,
                'resultado_mensal'     => $resultado,
                'mix_pf_pj' => $this->calcMix($receitaPF, $receitaPJ, $lastKey),
                'var_pct' => [
                    'receita_total' => $this->varPct($receitaTotal, $lastKey, $prevKey),
                    'despesas'      => $this->varPct($despesas, $lastKey, $prevKey),
                    'resultado'     => $this->varPct($resultado, $lastKey, $prevKey),
                ],
                'margem_liquida_pct' => $this->calcMargem($receitaTotal, $resultado, $lastKey),
                'top5_planos_despesa' => $this->top5PlanosDespesa(),
                'total_movimentos'    => DB::table('movimentos')->count(),
            ];
        });
    }

    // ========================================================================
    // BLOCO: INADIMPLÊNCIA
    // ========================================================================

    private function blocoInadimplencia(): array
    {
        return $this->safe('inadimplencia', function () {
            $hoje = Carbon::now()->toDateString();

            $vencidas = DB::table('contas_receber')
                ->where('status', '!=', 'Excluido')
                ->whereNull('data_pagamento')
                ->where('data_vencimento', '<', $hoje);

            $totalVencido   = (float) (clone $vencidas)->sum('valor');
            $qtdVencidas    = (clone $vencidas)->count();
            $diasMedioAtraso = (int) (clone $vencidas)
                ->selectRaw('AVG(DATEDIFF(CURDATE(), data_vencimento)) as media')
                ->value('media');

            // Aging buckets
            $aging = [
                '0_30'   => (float) (clone $vencidas)->whereRaw('DATEDIFF(CURDATE(), data_vencimento) BETWEEN 1 AND 30')->sum('valor'),
                '31_60'  => (float) (clone $vencidas)->whereRaw('DATEDIFF(CURDATE(), data_vencimento) BETWEEN 31 AND 60')->sum('valor'),
                '61_90'  => (float) (clone $vencidas)->whereRaw('DATEDIFF(CURDATE(), data_vencimento) BETWEEN 61 AND 90')->sum('valor'),
                '91_180' => (float) (clone $vencidas)->whereRaw('DATEDIFF(CURDATE(), data_vencimento) BETWEEN 91 AND 180')->sum('valor'),
                '180+'   => (float) (clone $vencidas)->whereRaw('DATEDIFF(CURDATE(), data_vencimento) > 180')->sum('valor'),
            ];

            $top5Devedores = DB::table('contas_receber')
                ->select('cliente', DB::raw('SUM(valor) as total'), DB::raw('COUNT(*) as qtd'))
                ->where('status', '!=', 'Excluido')
                ->whereNull('data_pagamento')
                ->where('data_vencimento', '<', $hoje)
                ->groupBy('cliente')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(fn($r) => ['cliente' => $r->cliente ?? '(Sem cliente)', 'total' => (float) $r->total, 'qtd' => $r->qtd])
                ->toArray();

            return [
                'total_vencido'     => $totalVencido,
                'qtd_vencidas'      => $qtdVencidas,
                'dias_medio_atraso' => $diasMedioAtraso,
                'aging_buckets'     => $aging,
                'top5_devedores'    => $top5Devedores,
                'total_contas'      => DB::table('contas_receber')->count(),
            ];
        });
    }

    // ========================================================================
    // BLOCO: CLIENTES
    // ========================================================================

    private function blocoClientes(): array
    {
        return $this->safe('clientes', function () {
            $totalClientes = DB::table('clientes')->count();

            $novosClientesMensal = [];
            foreach ($this->seriesMensal() as $m) {
                $novosClientesMensal[$m['key']] = DB::table('clientes')
                    ->whereNotNull('data_primeiro_contato')
                    ->whereYear('data_primeiro_contato', $m['ano'])
                    ->whereMonth('data_primeiro_contato', $m['mes'])
                    ->count();
            }

            $porArea = DB::table('processos')
                ->select('area_atuacao', DB::raw('COUNT(DISTINCT cliente_datajuri_id) as clientes'))
                ->whereNotNull('area_atuacao')
                ->where('area_atuacao', '!=', '')
                ->groupBy('area_atuacao')
                ->orderByDesc('clientes')
                ->limit(10)
                ->get()
                ->mapWithKeys(fn($r) => [$r->area_atuacao => $r->clientes])
                ->toArray();

            return [
                'total_clientes'         => $totalClientes,
                'novos_clientes_mensal'  => $novosClientesMensal,
                'clientes_por_area'      => $porArea,
            ];
        });
    }

    // ========================================================================
    // BLOCO: LEADS
    // ========================================================================

    private function blocoLeads(): array
    {
        return $this->safe('leads', function () {
            $total = DB::table('leads')->count();

            $porMes = [];
            foreach ($this->seriesMensal() as $m) {
                $porMes[$m['key']] = DB::table('leads')
                    ->whereYear('created_at', $m['ano'])
                    ->whereMonth('created_at', $m['mes'])
                    ->count();
            }

            $porStatus = DB::table('leads')
                ->select('status', DB::raw('COUNT(*) as qtd'))
                ->groupBy('status')
                ->get()
                ->mapWithKeys(fn($r) => [$r->status ?? 'null' => $r->qtd])
                ->toArray();

            return [
                'total'      => $total,
                'leads_mensal' => $porMes,
                'por_status' => $porStatus,
            ];
        });
    }

    // ========================================================================
    // BLOCO: CRM
    // ========================================================================

    private function blocoCrm(): array
    {
        return $this->safe('crm', function () {
            $accounts = DB::table('crm_accounts');

            $totalAccounts = (clone $accounts)->count();
            $clientes      = (clone $accounts)->where('kind', 'client')->count();
            $prospects     = (clone $accounts)->where('kind', 'prospect')->count();

            $opps = DB::table('crm_opportunities');
            $totalOpps = (clone $opps)->count();
            $oppsWon   = (clone $opps)->where('status', 'won')->count();
            $oppsLost  = (clone $opps)->where('status', 'lost')->count();
            $oppsOpen  = (clone $opps)->where('status', 'open')->count();

            $winRate = $totalOpps > 0 ? round(($oppsWon / max(1, $oppsWon + $oppsLost)) * 100, 1) : 0;

            $valorPipeline = (float) (clone $opps)->where('status', 'open')->sum('value_estimated');

            return [
                'total_accounts' => $totalAccounts,
                'clientes'       => $clientes,
                'prospects'      => $prospects,
                'oportunidades'  => [
                    'total'    => $totalOpps,
                    'won'      => $oppsWon,
                    'lost'     => $oppsLost,
                    'open'     => $oppsOpen,
                    'win_rate' => $winRate,
                    'valor_pipeline' => $valorPipeline,
                ],
            ];
        });
    }

    // ========================================================================
    // BLOCO: PROCESSOS
    // ========================================================================

    private function blocoProcessos(): array
    {
        return $this->safe('processos', function () {
            $total = DB::table('processos')->count();
            $ativos = DB::table('processos')->where('status', 'Em andamento')->count();
            $encerrados = DB::table('processos')->where('status', 'Encerrado')->count();

            $porArea = DB::table('processos')
                ->select('area_atuacao', DB::raw('COUNT(*) as qtd'))
                ->whereNotNull('area_atuacao')
                ->where('area_atuacao', '!=', '')
                ->groupBy('area_atuacao')
                ->orderByDesc('qtd')
                ->limit(10)
                ->get()
                ->mapWithKeys(fn($r) => [$r->area_atuacao => $r->qtd])
                ->toArray();

            $porStatus = DB::table('processos')
                ->select('status', DB::raw('COUNT(*) as qtd'))
                ->groupBy('status')
                ->get()
                ->mapWithKeys(fn($r) => [$r->status ?? 'null' => $r->qtd])
                ->toArray();

            // Processos sem movimentação nos últimos 90 dias
            $semMovimentacao = DB::table('processos')
                ->where('status', 'Em andamento')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('andamentos_fase')
                      ->whereColumn('andamentos_fase.processo_id_datajuri', 'processos.datajuri_id')
                      ->where('andamentos_fase.created_at', '>=', Carbon::now()->subDays(90));
                })
                ->count();

            // Novos processos por mês
            $novosMensal = [];
            foreach ($this->seriesMensal() as $m) {
                $novosMensal[$m['key']] = DB::table('processos')
                    ->whereYear('data_abertura', $m['ano'])
                    ->whereMonth('data_abertura', $m['mes'])
                    ->count();
            }

            // Encerrados por mês
            $encerradosMensal = [];
            foreach ($this->seriesMensal() as $m) {
                $encerradosMensal[$m['key']] = DB::table('processos')
                    ->where('status', 'Encerrado')
                    ->whereYear('data_encerramento', $m['ano'])
                    ->whereMonth('data_encerramento', $m['mes'])
                    ->count();
            }

            return [
                'total'                => $total,
                'ativos'               => $ativos,
                'encerrados'           => $encerrados,
                'por_area'             => $porArea,
                'por_status'           => $porStatus,
                'sem_movimentacao_90d' => $semMovimentacao,
                'novos_mensal'         => $novosMensal,
                'encerrados_mensal'    => $encerradosMensal,
            ];
        });
    }

    // ========================================================================
    // BLOCO: ATENDIMENTO WHATSAPP
    // ========================================================================

    private function blocoAtendimento(): array
    {
        return $this->safe('atendimento', function () {
            $totalConversas = DB::table('wa_conversations')->count();
            $totalMensagens = DB::table('wa_messages')->count();

            $conversasMensal = [];
            foreach ($this->seriesMensal() as $m) {
                $conversasMensal[$m['key']] = DB::table('wa_conversations')
                    ->whereYear('created_at', $m['ano'])
                    ->whereMonth('created_at', $m['mes'])
                    ->count();
            }

            $mensagensMensal = [];
            foreach ($this->seriesMensal() as $m) {
                $mensagensMensal[$m['key']] = DB::table('wa_messages')
                    ->whereYear('created_at', $m['ano'])
                    ->whereMonth('created_at', $m['mes'])
                    ->count();
            }

            $semResposta = DB::table('wa_conversations')
                ->whereNull('first_response_at')
                ->where('status', '!=', 'closed')
                ->count();

            $porStatus = DB::table('wa_conversations')
                ->select('status', DB::raw('COUNT(*) as qtd'))
                ->groupBy('status')
                ->get()
                ->mapWithKeys(fn($r) => [$r->status => $r->qtd])
                ->toArray();

            return [
                'total_conversas'    => $totalConversas,
                'total_mensagens'    => $totalMensagens,
                'conversas_mensal'   => $conversasMensal,
                'mensagens_mensal'   => $mensagensMensal,
                'sem_resposta'       => $semResposta,
                'conversas_por_status' => $porStatus,
            ];
        });
    }

    // ========================================================================
    // BLOCO: TICKETS
    // ========================================================================

    private function blocoTickets(): array
    {
        return $this->safe('tickets', function () {
            $total = DB::table('nexo_tickets')->count();

            $porStatus = DB::table('nexo_tickets')
                ->select('status', DB::raw('COUNT(*) as qtd'))
                ->groupBy('status')
                ->get()
                ->mapWithKeys(fn($r) => [$r->status => $r->qtd])
                ->toArray();

            $porPrioridade = DB::table('nexo_tickets')
                ->select('prioridade', DB::raw('COUNT(*) as qtd'))
                ->groupBy('prioridade')
                ->get()
                ->mapWithKeys(fn($r) => [$r->prioridade ?? 'null' => $r->qtd])
                ->toArray();

            $ticketsMensal = [];
            foreach ($this->seriesMensal() as $m) {
                $ticketsMensal[$m['key']] = DB::table('nexo_tickets')
                    ->whereYear('created_at', $m['ano'])
                    ->whereMonth('created_at', $m['mes'])
                    ->count();
            }

            return [
                'total'           => $total,
                'por_status'      => $porStatus,
                'por_prioridade'  => $porPrioridade,
                'tickets_mensal'  => $ticketsMensal,
            ];
        });
    }

    // ========================================================================
    // BLOCO: HORAS TRABALHADAS
    // ========================================================================

    private function blocoHoras(): array
    {
        return $this->safe('horas', function () {
            $total = DB::table('horas_trabalhadas_datajuri')->count();

            $horasMensal = [];
            foreach ($this->seriesMensal() as $m) {
                // duracao_original é VARCHAR 'HH:MM' — converter para horas decimais
                $minutos = (int) DB::table('horas_trabalhadas_datajuri')
                    ->whereYear('data', $m['ano'])
                    ->whereMonth('data', $m['mes'])
                    ->whereNotNull('duracao_original')
                    ->where('duracao_original', '!=', '')
                    ->selectRaw("SUM(CAST(SUBSTRING_INDEX(duracao_original, ':', 1) AS UNSIGNED) * 60 + CAST(SUBSTRING_INDEX(duracao_original, ':', -1) AS UNSIGNED)) as total_min")
                    ->value('total_min');
                $horasMensal[$m['key']] = round($minutos / 60, 2);
            }

            $valorMensal = [];
            foreach ($this->seriesMensal() as $m) {
                $valorMensal[$m['key']] = (float) DB::table('horas_trabalhadas_datajuri')
                    ->whereYear('data', $m['ano'])
                    ->whereMonth('data', $m['mes'])
                    ->sum('valor_total_original');
            }

            $porProprietario = DB::table('horas_trabalhadas_datajuri')
                ->select(
                    'proprietario_id',
                    DB::raw("SUM(CAST(SUBSTRING_INDEX(duracao_original, ':', 1) AS UNSIGNED) * 60 + CAST(SUBSTRING_INDEX(duracao_original, ':', -1) AS UNSIGNED)) as total_min"),
                    DB::raw('COUNT(*) as registros')
                )
                ->whereNotNull('proprietario_id')
                ->whereNotNull('duracao_original')
                ->where('duracao_original', '!=', '')
                ->groupBy('proprietario_id')
                ->orderByDesc('total_min')
                ->limit(10)
                ->get()
                ->map(fn($r) => ['proprietario_id' => $r->proprietario_id, 'horas' => round((float) $r->total_min / 60, 2), 'registros' => $r->registros])
                ->toArray();

            return [
                'total_registros'    => $total,
                'horas_mensal'       => $horasMensal,
                'valor_mensal'       => $valorMensal,
                'por_proprietario'   => $porProprietario,
            ];
        });
    }

    // ========================================================================
    // BLOCO: GDP
    // ========================================================================

    private function blocoGdp(): array
    {
        return $this->safe('gdp', function () {
            $snapshots = DB::table('gdp_snapshots')
                ->select('user_id', 'mes', 'ano', 'score_total', 'ranking')
                ->orderByDesc('ano')
                ->orderByDesc('mes')
                ->limit(50)
                ->get()
                ->map(fn($r) => [
                    'user_id'     => $r->user_id,
                    'mes'         => $r->mes,
                    'ano'         => $r->ano,
                    'score_total' => (float) $r->score_total,
                    'ranking'     => $r->ranking,
                ])
                ->toArray();

            $resultados = DB::table('gdp_resultados_mensais')
                ->select('user_id', 'indicador_id', 'mes', 'ano', 'valor_apurado', 'percentual_atingimento')
                ->orderByDesc('ano')
                ->orderByDesc('mes')
                ->limit(200)
                ->get()
                ->map(fn($r) => [
                    'user_id'       => $r->user_id,
                    'indicador_id'  => $r->indicador_id,
                    'mes'           => $r->mes,
                    'ano'           => $r->ano,
                    'valor_apurado' => (float) ($r->valor_apurado ?? 0),
                    'valor_meta'    => (float) ($r->valor_meta ?? 0),
                    'pct_atingido'  => (float) ($r->percentual_atingimento ?? 0),
                ])
                ->toArray();

            return [
                'snapshots'  => $snapshots,
                'resultados' => $resultados,
            ];
        });
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    private function seriesMensal(): array
    {
        $meses = [];
        $cursor = $this->inicio->copy()->startOfMonth();

        while ($cursor->lte($this->fim)) {
            $meses[] = [
                'key' => $cursor->format('Y-m'),
                'ano' => $cursor->year,
                'mes' => $cursor->month,
            ];
            $cursor->addMonth();
        }

        return $meses;
    }

    private function varPct(array $serie, string $lastKey, ?string $prevKey): ?float
    {
        if (!$prevKey || !isset($serie[$lastKey]) || !isset($serie[$prevKey]) || $serie[$prevKey] == 0) {
            return null;
        }
        return round((($serie[$lastKey] - $serie[$prevKey]) / abs($serie[$prevKey])) * 100, 1);
    }

    private function calcMix(array $pf, array $pj, string $key): array
    {
        $totalPF = $pf[$key] ?? 0;
        $totalPJ = $pj[$key] ?? 0;
        $sum = $totalPF + $totalPJ;
        return [
            'pf_pct' => $sum > 0 ? round(($totalPF / $sum) * 100, 1) : 0,
            'pj_pct' => $sum > 0 ? round(($totalPJ / $sum) * 100, 1) : 0,
        ];
    }

    private function calcMargem(array $receita, array $resultado, string $key): ?float
    {
        $r = $receita[$key] ?? 0;
        if ($r == 0) return null;
        return round(($resultado[$key] / $r) * 100, 1);
    }

    private function top5PlanosDespesa(): array
    {
        return DB::table('movimentos')
            ->select('codigo_plano', 'plano_contas', DB::raw('SUM(ABS(valor)) as total'))
            ->where('classificacao', 'DESPESA')
            ->whereNotNull('codigo_plano')
            ->where('codigo_plano', '!=', '')
            ->groupBy('codigo_plano', 'plano_contas')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn($r) => [
                'codigo' => $r->codigo_plano,
                'nome'   => $r->plano_contas,
                'total'  => (float) $r->total,
            ])
            ->toArray();
    }

    /**
     * Executa bloco com proteção; se falhar, registra erro e retorna unavailable.
     */
    private function safe(string $bloco, callable $fn): array
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            $this->errors[] = [
                'bloco'    => $bloco,
                'mensagem' => $e->getMessage(),
                'arquivo'  => basename($e->getFile()) . ':' . $e->getLine(),
            ];
            Log::warning("BscInsightSnapshotBuilder: bloco '{$bloco}' falhou", [
                'error' => $e->getMessage(),
            ]);
            return ['_status' => 'unavailable', '_error' => $e->getMessage()];
        }
    }
}
