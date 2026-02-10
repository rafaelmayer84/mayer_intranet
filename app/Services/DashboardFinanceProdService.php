<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Models\ContaReceber;
use App\Models\Movimento;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DashboardFinanceProdService
 *
 * Fase 1: Financeiro (Visão Gerencial Executiva)
 *
 * Este service consolida as consultas necessárias para a dashboard executiva.
 * As metas são lidas da tabela "configuracoes" usando o mesmo padrão já
 * existente em DashboardController (meta_pf_{ano}_{mes}, etc).
 */
class DashboardFinanceProdService
{
/**
 * Palavras-chave de rubricas que NÃO devem ser tratadas como despesa operacional
 * (distribuição de lucros, retirada de sócios, dividendos etc.).
 *
 * Observação: usamos "contém" (substring) para funcionar mesmo quando a rubrica vem com código.
 */
public const RUBRICAS_EXCLUIDAS = [
    'distribuição',
    'distribuicao',
    'retirada',
    'dividendo',
    'dividendos',
    'lucro',
    'lucros',
];

    /**
     * Cache (curto) de valores distintos do campo "classificacao" em movimentos.
     *
     * Importante: este sistema teve ao menos duas versões de schema no histórico:
     * - classificacao = PF / PJ / DESPESA / OUTRO
     * - classificacao = RECEITA_PF / RECEITA_PJ / DESPESA / OUTRO
     *
     * Para evitar "gráficos em branco" quando o banco estiver na versão antiga,
     * resolvemos dinamicamente quais valores representam PF/PJ.
     */
    private function distinctClassificacoes(): array
    {
        return Cache::remember('dash_fin_exec:movimentos:classificacoes', 600, function () {
            try {
                return Movimento::query()
                    ->select('classificacao')
                    ->distinct()
                    ->whereNotNull('classificacao')
                    ->limit(200)
                    ->pluck('classificacao')
                    ->filter()
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    private function normalizeKey(?string $value): string
    {
        $v = (string) ($value ?? '');
        $v = trim($v);
        if ($v === '') return '';

        // tenta remover acentos (sem depender de extensões externas)
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
        if (is_string($ascii) && $ascii !== '') {
            $v = $ascii;
        }

        $v = mb_strtolower($v, 'UTF-8');
        // remove separadores comuns para facilitar matching
        $v = str_replace([' ', '-', '_', '.', '/', '\\'], '', $v);
        // remove tudo que não for alfanumérico
        $v = preg_replace('/[^a-z0-9]/', '', $v) ?? '';
        return $v;
    }

    /**
     * Retorna quais valores de "classificacao" devem ser tratados como Receita PF/PJ.
     *
     * @return array{pf: array<int,string>, pj: array<int,string>}
     */
    private function resolveReceitaClassificacoes(): array
    {
        $pf = [];
        $pj = [];

        foreach ($this->distinctClassificacoes() as $raw) {
            $k = $this->normalizeKey((string) $raw);
            if ($k === '') continue;

            // exemplos aceitos: PF, RECEITA_PF, RECEITAPF, RECEITA PF, PESSOA FISICA (etc)
            if ($k === 'pf' || $k === 'receitapf' || str_ends_with($k, 'pf') || str_contains($k, 'pessoafisica')) {
                $pf[] = (string) $raw;
            }
            if ($k === 'pj' || $k === 'receitapj' || str_ends_with($k, 'pj') || str_contains($k, 'pessoajuridica')) {
                $pj[] = (string) $raw;
            }
        }

        // Fallbacks seguros (cobrem as duas versões do schema e variações comuns)
        if (count($pf) === 0) {
            $pf = ['RECEITA_PF', 'PF', 'Receita PF', 'RECEITA PF', 'receita_pf', 'receita pf'];
        }
        if (count($pj) === 0) {
            $pj = ['RECEITA_PJ', 'PJ', 'Receita PJ', 'RECEITA PJ', 'receita_pj', 'receita pj'];
        }

        // remove duplicatas preservando ordem
        $pf = array_values(array_unique($pf));
        $pj = array_values(array_unique($pj));

        return ['pf' => $pf, 'pj' => $pj];
    }

    /**
     * Identifica a coluna mais provável que armazena o código do plano de contas,
     * para permitir fallback PF/PJ por prefixos quando a "classificacao" não separar.
     */
    private function planoCodigoColumn(): ?string
    {
        try {
            foreach (['codigo_plano', 'plano_conta_codigo', 'plano_contas'] as $col) {
                if (Schema::hasColumn('movimentos', $col)) {
                    return $col;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }

    /**
     * Aplica filtros de Receita PF/PJ em uma query de Movimentos.
     *
     * @param 'pf'|'pj' $tipo
     */
    private function applyReceitaTipoFilter($query, string $tipo): void
    {
        $map = $this->resolveReceitaClassificacoes();
        $vals = $tipo === 'pj' ? $map['pj'] : $map['pf'];
        $planCol = $this->planoCodigoColumn();
        $planos = $tipo === 'pj' ? (Movimento::PLANOS_PJ ?? []) : (Movimento::PLANOS_PF ?? []);

        $query->where(function ($q) use ($vals, $planCol, $planos, $tipo) {
            // 1) classificação direta (cobre PF/PJ e RECEITA_PF/RECEITA_PJ)
            if (count($vals) > 0) {
                $q->whereIn('classificacao', $vals);
            }

            // 2) variações (ex: "Receita PF") em bancos com VARCHAR + collation case-insensitive
            // Evita depender de constantes do Model.
            $needle = $tipo === 'pj' ? 'pj' : 'pf';
            $q->orWhereRaw('LOWER(COALESCE(classificacao,\'\')) LIKE ?', ["%{$needle}%"]);

            // 3) fallback por código do plano de contas (quando classificação não separa)
            if ($planCol && is_array($planos) && count($planos) > 0) {
                $q->orWhere(function ($qq) use ($planCol, $planos) {
                    foreach ($planos as $i => $prefix) {
                        if (!$prefix) continue;
                        $method = $i === 0 ? 'where' : 'orWhere';
                        $qq->{$method}($planCol, 'like', $prefix . '%');
                    }
                });
            }
        });
    }

    private function sumReceitaTipo(int $ano, int $mes, string $tipo): float
    {
        $q = Movimento::query()->where('ano', $ano)->where('mes', $mes);
        $this->applyReceitaTipoFilter($q, $tipo);
        return (float) $q->sum('valor');
    }


    /**
     * Alias mantido para compatibilidade com prompts/implementações externas.
     */
    public function getReceituaByMonth(int $ano): array
    {
        return $this->getReceitaByMonth($ano);
    }

    /**
     * Monta o payload completo da dashboard.
     *
     * @return array<string,mixed>
     */
    public function getDashboardData(int $ano, int $mes): array
    {
        $mes = max(1, min(12, $mes));

        // Cache de 1h (pedido do projeto). O cache é por competência.
        return Cache::remember("dash_fin_exec:v1:{$ano}:{$mes}", 3600, function () use ($ano, $mes) {
            $resumo = $this->getResumoExecutivo($ano, $mes);
            $saude = $this->getIndicadoresFinanceiros($ano, $mes, $resumo['receitaTotal']);

            $receitaByMonth = $this->getReceitaByMonth($ano);
            $metasPF = $this->getMetasMensais('meta_pf', $ano);
            $metasPJ = $this->getMetasMensais('meta_pj', $ano);

            $despesasRubrica = $this->getDespesasByRubrica($ano, $mes);
            $contasAtrasoLista = $this->getContasEmAtrasoLista($ano, $mes);
            try {
                $topAtrasoClientes = $this->getTopAtrasoClientes($ano, $mes);
            } catch (\Exception $e) {
                \Log::error('Erro ao calcular top atraso clientes: ' . $e->getMessage());
                $this->warnings[] = 'Não foi possível calcular concentração de atraso de clientes';
                $topAtrasoClientes = ['refDate' => date('Y-m-d'), 'totalVencido' => 0, 'top' => [], 'top3SharePct' => 0];
            }
            $aging = $this->getAgingContas($ano, $mes);
            $comparativo = $this->getComparativoMensal($ano, $mes);
            // Diagnóstico de qualidade de dados (para evitar dashboard "bonita" com base vazia).
            $movimentosTotal = (int) Movimento::query()->count();
            $movimentosComClassificacao = (int) Movimento::query()
                ->whereNotNull('classificacao')
                ->where('classificacao', '<>', '')
                ->count();

            $contasReceberTotal = (int) ContaReceber::query()->count();

            $metasConfiguradasCount = (int) Configuracao::query()
                ->where('chave', 'like', 'meta_%')
                ->count();

            $warnings = [];

            // CORRIGIDO: Detectar PENDENTE_CLASSIFICACAO
            $movimentosPendentes = (int) Movimento::query()
                ->where('classificacao', 'PENDENTE_CLASSIFICACAO')
                ->orWhere('classificacao', '')
                ->orWhereNull('classificacao')
                ->count();

            if ($movimentosPendentes > 0) {
                $warnings[] = "Há {$movimentosPendentes} movimentos sem classificação; Mix PF/PJ indisponível até classificar.";
            }

            if ($movimentosTotal > 0 && $movimentosComClassificacao === 0) {
                $warnings[] = 'movimentos.classificacao vazia: KPIs PF/PJ podem ficar 0 (rode o backfill).';
            }

            // CORRIGIDO: Detectar data_pagamento vazia
            $contasComPagamento = (int) ContaReceber::query()
                ->whereNotNull('data_pagamento')
                ->count();

            if ($contasReceberTotal > 0 && $contasComPagamento === 0) {
                $warnings[] = 'Sem base de pagamentos (data_pagamento vazia); taxa de cobrança indisponível.';
            }

            if ($contasReceberTotal === 0) {
                $warnings[] = 'contas_receber vazia: KPIs de atraso/cobrança ficarão 0 (rode a sync).';
            }

            if ($metasConfiguradasCount === 0) {
                $warnings[] = 'metas não cadastradas: metas ficarão 0 (preencha em /configurar-metas).';
            }

            return [
                'ano' => $ano,
                'mes' => $mes,
                'resumoExecutivo' => $resumo,
                'saudeFinanceira' => $saude,
                'receitaPF12Meses' => [
                    'meses' => $this->getMesesAbrev(),
                    'meta' => array_values($metasPF),
                    'realizado' => $receitaByMonth['pf'],
                ],
                'receitaPJ12Meses' => [
                    'meses' => $this->getMesesAbrev(),
                    'meta' => array_values($metasPJ),
                    'realizado' => $receitaByMonth['pj'],
                ],
                'lucratividade12Meses' => $this->getLucratividadeByMonth($ano),
                'despesasRubrica' => $despesasRubrica,
                'contasAtrasoLista' => $contasAtrasoLista,
                'topAtrasoClientes' => $topAtrasoClientes,
                'agingContas' => $aging,
                'mixReceita' => [
                    'pfValor' => $resumo['receitaPf'] ?? 0,
                    'pjValor' => $resumo['receitaPj'] ?? 0,
                    'pfPct' => $resumo['receitaTotal'] > 0 ? round(($resumo['receitaPf'] / $resumo['receitaTotal']) * 100, 1) : 0,
                    'pjPct' => $resumo['receitaTotal'] > 0 ? round(($resumo['receitaPj'] / $resumo['receitaTotal']) * 100, 1) : 0,
                ],
                'receitaYoY' => [
                    'yoyPct' => $resumo['receitaTrend'] ?? 0,
                    'atual' => $resumo['receitaTotal'] ?? 0,
                    'anoAnterior' => $resumo['receitaPrev'] ?? 0,
                ],
                'expenseRatio' => [
                    'pct' => $resumo['receitaTotal'] > 0 ? round((($resumo['despesasTotal'] + ($resumo['deducoesTotal'] ?? 0)) / $resumo['receitaTotal']) * 100, 1) : 0,
                    'despesas' => $resumo['despesasTotal'] ?? 0,
                    'deducoes' => $resumo['deducoesTotal'] ?? 0,
                    'receita' => $resumo['receitaTotal'] ?? 0,
                ],
                'inadimplencia' => [
                    'pctVencidoSobreAberto' => $saude['inadimplencia'] ?? 0,
                    'totalVencido' => $saude['totalVencido'] ?? 0,
                    'totalAberto' => $saude['totalAberto'] ?? 0,
                ],
                'qualidadeDados' => [
                    'pctConciliadoCount' => $movimentosComClassificacao > 0 ? round(($movimentosComClassificacao / $movimentosTotal) * 100, 1) : 0,
                    'receitaQualificada' => $resumo['receitaTotal'] ?? 0,
                    'receitaTotal' => $resumo['receitaTotal'] ?? 0,
                ],
                'comparativoMensal' => $comparativo,
                'dataQuality' => [
                    'movimentosTotal' => $movimentosTotal,
                    'movimentosComClassificacao' => $movimentosComClassificacao,
                    'contasReceberTotal' => $contasReceberTotal,
                    'metasConfiguradasCount' => $metasConfiguradasCount,
                ],
                'rubricasMoM' => $this->getRubricasVariacaoMoM($ano, $mes),
                'warnings' => $warnings,
            ];
        });
    }

    /**
     * Receitas PF/PJ por mês (12 meses).
     *
     * @return array{pf: array<int,float>, pj: array<int,float>}
     */
    public function getReceitaByMonth(int $ano): array
    {
        $pf = array_fill(0, 12, 0.0);
        $pj = array_fill(0, 12, 0.0);

        $pfQ = Movimento::select(DB::raw('mes'), DB::raw('SUM(valor) as total'))
            ->where('ano', $ano)
            ->groupBy('mes');
        $this->applyReceitaTipoFilter($pfQ, 'pf');
        $pfRows = $pfQ->pluck('total', 'mes')->toArray();

        $pjQ = Movimento::select(DB::raw('mes'), DB::raw('SUM(valor) as total'))
            ->where('ano', $ano)
            ->groupBy('mes');
        $this->applyReceitaTipoFilter($pjQ, 'pj');
        $pjRows = $pjQ->pluck('total', 'mes')->toArray();

        foreach ($pfRows as $m => $t) {
            $idx = ((int) $m) - 1;
            if ($idx >= 0 && $idx < 12) $pf[$idx] = (float) $t;
        }
        foreach ($pjRows as $m => $t) {
            $idx = ((int) $m) - 1;
            if ($idx >= 0 && $idx < 12) $pj[$idx] = (float) $t;
        }

        return ['pf' => array_map('floatval', $pf), 'pj' => array_map('floatval', $pj)];
    }

/**
 * Série mensal (12 meses) da Receita PF (meta x realizado).
 */
public function getReceitaPFByMonth(int $ano): array
{
    $metas = $this->getMetasMensais('meta_pf', $ano);
    $real = $this->getReceitaByMonth($ano)['pf'];

    return [
        'meses' => $this->getMesesAbrev(),
        'meta' => array_values($metas),
        'realizado' => $real,
    ];
}

/**
 * Série mensal (12 meses) da Receita PJ (meta x realizado).
 */
public function getReceitaPJByMonth(int $ano): array
{
    $metas = $this->getMetasMensais('meta_pj', $ano);
    $real = $this->getReceitaByMonth($ano)['pj'];

    return [
        'meses' => $this->getMesesAbrev(),
        'meta' => array_values($metas),
        'realizado' => $real,
    ];
}

/**
 * Lucratividade mensal (12 meses): Receita Total - Deducoes - Despesas Operacionais.
 *
 * FIX v3.0: Inclui deducoes (DEDUCAO) no calculo.
 *
 * @return array{meses: array<int,string>, receita: array<int,float>, despesas: array<int,float>, lucratividade: array<int,float>}
 */
public function getLucratividadeByMonth(int $ano): array
{
    $receitas = $this->getReceitaByMonth($ano);
    $deducoes = $this->deducoesByMonth($ano);
    $despesas = $this->despesasOperacionaisByMonth($ano);

    $receitaTotal = [];
    $lucro = [];

    for ($i = 0; $i < 12; $i++) {
        $rt = (float) ($receitas['pf'][$i] ?? 0) + (float) ($receitas['pj'][$i] ?? 0);
        $dd = (float) ($deducoes[$i] ?? 0);
        $dt = (float) ($despesas[$i] ?? 0);
        $receitaTotal[$i] = round($rt, 2);
        $lucro[$i] = round($rt - $dd - $dt, 2);
    }

    return [
        'meses' => $this->getMesesAbrev(),
        'receita' => $receitaTotal,
        'despesas' => array_map(fn($v) => round((float) $v, 2), $despesas),
        'lucratividade' => $lucro,
    ];
}

    /**
     * Despesas por rubrica (agrupadas por código do plano) dentro do mês.
     * Filtra rubricas não-operacionais (distribuição/retirada/dividendos/lucros).
     */
    public function getDespesasByRubrica(int $ano, int $mes): array
    {
        $mes = max(1, min(12, $mes));
        [$pAno, $pMes] = $this->prevCompetencia($ano, $mes);

        $q = Movimento::select(
                'codigo_plano',
                'plano_contas',
                DB::raw('SUM(valor) as total')
            )
            ->where('ano', $ano)
            ->where('mes', $mes)
            ->where('classificacao', Movimento::DESPESA);

        $this->applyRubricasExcluidas($q);

        $rows = $q->groupBy('codigo_plano', 'plano_contas')
            ->orderByDesc('total')
            ->limit(30)
            ->get();

        // Totais do mês anterior por rubrica (para tendência)
        $qPrev = Movimento::select('codigo_plano', DB::raw('SUM(valor) as total'))
            ->where('ano', $pAno)
            ->where('mes', $pMes)
            ->where('classificacao', Movimento::DESPESA);

        $this->applyRubricasExcluidas($qPrev);

        $prev = $qPrev->groupBy('codigo_plano')
            ->pluck('total', 'codigo_plano')
            ->toArray();

        $out = [];
        foreach ($rows as $r) {
            $codigo = (string) ($r->codigo_plano ?? '');
            // FIX v3.0: Usar codigo_plano como fallback quando plano_contas vazio
            $planoContas = (string) ($r->plano_contas ?? '');
            $rubrica = $this->normalizarRubrica($planoContas !== '' ? $planoContas : $codigo);

            if (!$this->isDespesaOperacional($rubrica)) {
                continue;
            }

            $valor = (float) $r->total;
            $meta = (float) Configuracao::get("meta_despesa_rubrica_{$ano}_{$mes}_{$codigo}", 0);
            $prevVal = isset($prev[$codigo]) ? (float) $prev[$codigo] : 0.0;

            $out[] = [
                'rubrica' => $rubrica,
                'valor' => round($valor, 2),
                'meta' => round($meta, 2),
                'trend' => $this->percentChange($valor, $prevVal),
            ];
        }

        return $out;
    }

    /**
     * Wrapper conforme nomenclatura do prompt.
     * Retorna lista de contas em atraso para a competência (default: atual).
     */
    public function getContasEmAtraso(?int $ano = null, ?int $mes = null): array
    {
        $ano = $ano ?? (int) date('Y');
        $mes = $mes ?? (int) date('n');
        return $this->getContasEmAtrasoLista($ano, $mes);
    }

    /**
     * Lista de contas vencidas (top 10 por dias de atraso).
     */
    public function getContasEmAtrasoLista(int $ano, int $mes): array
    {
        $refDate = $this->refDateCompetencia($ano, $mes);

        $rows = ContaReceber::query()
            ->whereNull('data_pagamento')
            ->where('status', 'Não lançado')
            ->whereNotNull('data_vencimento')
            ->whereDate('data_vencimento', '<=', $refDate->toDateString())
            ->whereDate('data_vencimento', '>=', $refDate->copy()->subDays(90)->toDateString())
            ->orderBy('data_vencimento')
            ->limit(50)
            ->get();

        $mapped = [];
        foreach ($rows as $c) {
            $dias = $c->data_vencimento ? $c->data_vencimento->diffInDays($refDate, false) : 0;
            $dias = max(0, (int) $dias);
            // CORRIGIDO: Usar coluna 'cliente' (não 'cliente_nome' que não existe)
            // Usar datajuri_id como numero, fallback para id
            $mapped[] = [
                'numero' => (int) ($c->datajuri_id ?? $c->id ?? 0),
                'cliente' => (string) ($c->cliente ?? '(Sem cliente)'),
                'valor' => (float) $c->valor,
                'diasAtraso' => $dias,
                'status' => $this->statusAtraso($dias),
            ];
        }

        usort($mapped, function ($a, $b) {
            return ($b['diasAtraso'] <=> $a['diasAtraso']) ?: ($b['valor'] <=> $a['valor']);
        });

        return array_slice($mapped, 0, 10);
    }

    /**
     * KPI: Top clientes em atraso (agrupado por cliente_nome).
     *
     * Compatível com MySQL 5.7+ em modo ONLY_FULL_GROUP_BY:
     * - agrupa pela MESMA expressão usada no SELECT.
     *
     * Retorna sempre estrutura estável:
     * - totalVencido (float)
     * - top (array)
     * - top3SharePct (float)
     */
    private function getTopAtrasoClientes(int $ano, int $mes): array
    {
        $ref = $this->refDateCompetencia($ano, $mes);

        // Coluna confirmada no schema do projeto: contas_receber.cliente
        $col = Schema::hasColumn('contas_receber', 'cliente')
            ? 'cliente'
            : null;

        $base = $this->overdueQuery($ref);

        $totalVencido = (float) $base->clone()->sum('valor');

        if (!$col) {
            return [
                'refDate' => $ref->toDateString(),
                'totalVencido' => $totalVencido,
                'top' => [],
                'top3SharePct' => 0.0,
            ];
        }

        // Evita ONLY_FULL_GROUP_BY fazendo a normalização em subquery e agrupando por alias.
        $expr = "COALESCE(NULLIF(TRIM({$col}), ''), '(Sem cliente)')";

        $sub = $base->clone()->selectRaw("{$expr} as cliente_nome, valor");

        $rows = DB::query()
            ->fromSub($sub, 't')
            ->selectRaw("cliente_nome, SUM(valor) as total")
            ->groupBy('cliente_nome')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $top = [];
        $top3Sum = 0.0;

        foreach ($rows as $idx => $r) {
            $valor = (float) ($r->total ?? 0);

            if ($idx < 3) {
                $top3Sum += $valor;
            }

            $sharePct = $totalVencido > 0 ? round(($valor / $totalVencido) * 100, 2) : 0.0;

            $top[] = [
                'cliente_nome' => (string) ($r->cliente_nome ?? '(Sem cliente)'),
                'valor' => $valor,
                'sharePct' => $sharePct,
            ];
        }

        $top3SharePct = $totalVencido > 0 ? round(($top3Sum / $totalVencido) * 100, 2) : 0.0;

        return [
            'refDate' => $ref->toDateString(),
            'totalVencido' => $totalVencido,
            'top' => $top,
            'top3SharePct' => $top3SharePct,
        ];
    }

    /**
     * Indicadores de saúde financeira.
     */
    public function getIndicadoresFinanceiros(int $ano, int $mes, float $receitaTotalMes = 0.0): array
    {
        [$pAno, $pMes] = $this->prevCompetencia($ano, $mes);

        $ref = $this->refDateCompetencia($ano, $mes);
        $refPrev = $this->refDateCompetencia($pAno, $pMes);

        $contas = $this->overdueQuery($ref)->get();
        $contasPrev = $this->overdueQuery($refPrev)->get();

        $totalAtraso = (float) $contas->sum('valor');
        $totalAtrasoPrev = (float) $contasPrev->sum('valor');

        $percent = $receitaTotalMes > 0 ? round(($totalAtraso / $receitaTotalMes) * 100, 1) : 0.0;
        $percentPrev = $receitaTotalMes > 0 ? round(($totalAtrasoPrev / $receitaTotalMes) * 100, 1) : 0.0;

        $avgDias = $this->avgDiasAtraso($contas, $ref);
        $avgDiasPrev = $this->avgDiasAtraso($contasPrev, $refPrev);

        [$taxa, $taxaPrev] = $this->taxaCobrancaMesVsPrev($ano, $mes);

        return [
            'contasAtraso' => round($totalAtraso, 2),
            'diasAtraso' => $avgDias,
            'diasAtrasoMeta' => (int) Configuracao::get("meta_dias_atraso_{$ano}_{$mes}", 30),
            'diasAtrasoTrend' => $avgDiasPrev > 0 ? (int) round($avgDias - $avgDiasPrev) : (int) $avgDias,
            'contasAtrasoPercent' => $percent,
            'contasAtrasoTrend' => $this->percentChange($totalAtraso, $totalAtrasoPrev),
            'diasMedioAtraso' => $avgDias,
            'diasMedioAtrasoMeta' => (int) Configuracao::get("meta_dias_atraso_{$ano}_{$mes}", 30),
            'diasMedioAtrasoTrend' => $avgDiasPrev > 0 ? (int) round($avgDias - $avgDiasPrev) : (int) $avgDias,
            'taxaCobranca' => $taxa,
            'taxaCobrancaMeta' => (float) Configuracao::get("meta_taxa_cobranca_{$ano}_{$mes}", 95),
            'taxaCobrancaTrend' => $this->percentChange($taxa, $taxaPrev),
            'totalVencido' => $totalAtraso,
            'totalAberto' => (float) ContaReceber::query()->whereNull('data_pagamento')->where('status', 'Não lançado')->sum('valor'),
            'inadimplencia' => $this->calcularInadimplencia($ano, $mes),
        ];
    }

    /**
     * Calcula inadimplência (% de contas vencidas sobre total em aberto).
     */
    private function calcularInadimplencia(int $ano, int $mes): float
    {
        $ref = $this->refDateCompetencia($ano, $mes);
        $totalAberto = (float) ContaReceber::query()
            ->whereNull('data_pagamento')
            ->where('status', 'Não lançado')
            ->sum('valor');

        if ($totalAberto <= 0) return 0.0;

        $totalVencido = (float) $this->overdueQuery($ref)->sum('valor');
        return round(($totalVencido / $totalAberto) * 100, 1);
    }

    /**
     * Comparativo dos últimos 3 meses.
     */
    /**
     * Comparativo dos ultimos 3 meses.
     * FIX v3.0: Adicionada linha de Deducoes; Resultado inclui deducoes.
     */
    public function getComparativoMensal(int $ano, int $mes): array
    {
        [$ano2, $mes2] = $this->prevCompetencia($ano, $mes);
        [$ano1, $mes1] = $this->prevCompetencia($ano2, $mes2);

        $m1 = $this->resumoBasico($ano1, $mes1);
        $m2 = $this->resumoBasico($ano2, $mes2);
        $m3 = $this->resumoBasico($ano, $mes);

        // Contas em atraso e taxa de cobranca (por competencia)
        $ref1 = $this->refDateCompetencia($ano1, $mes1);
        $ref2 = $this->refDateCompetencia($ano2, $mes2);
        $ref3 = $this->refDateCompetencia($ano, $mes);
        $at1 = (float) $this->overdueQuery($ref1)->sum('valor');
        $at2 = (float) $this->overdueQuery($ref2)->sum('valor');
        $at3 = (float) $this->overdueQuery($ref3)->sum('valor');

        [$taxa1] = $this->taxaCobrancaMes($ano1, $mes1);
        [$taxa2] = $this->taxaCobrancaMes($ano2, $mes2);
        [$taxa3] = $this->taxaCobrancaMes($ano, $mes);

        return [
            ['metrica' => 'Receita', 'mes1' => $m1['receita'], 'mes2' => $m2['receita'], 'mes3' => $m3['receita'], 'trend' => $this->percentChange($m3['receita'], $m2['receita'])],
            ['metrica' => 'Deduções', 'mes1' => $m1['deducoes'], 'mes2' => $m2['deducoes'], 'mes3' => $m3['deducoes'], 'trend' => $this->percentChange($m3['deducoes'], $m2['deducoes'])],
            ['metrica' => 'Despesas', 'mes1' => $m1['despesas'], 'mes2' => $m2['despesas'], 'mes3' => $m3['despesas'], 'trend' => $this->percentChange($m3['despesas'], $m2['despesas'])],
            ['metrica' => 'Resultado Líquido', 'mes1' => $m1['resultado'], 'mes2' => $m2['resultado'], 'mes3' => $m3['resultado'], 'trend' => $this->percentChange($m3['resultado'], $m2['resultado'])],
            ['metrica' => 'Margem Líquida', 'mes1' => $m1['margem'], 'mes2' => $m2['margem'], 'mes3' => $m3['margem'], 'trend' => $this->percentChange($m3['margem'], $m2['margem'])],
            ['metrica' => 'Contas Atraso', 'mes1' => $at1, 'mes2' => $at2, 'mes3' => $at3, 'trend' => $this->percentChange($at3, $at2)],
            ['metrica' => 'Taxa Cobrança', 'mes1' => $taxa1, 'mes2' => $taxa2, 'mes3' => $taxa3, 'trend' => $this->percentChange($taxa3, $taxa2)],
        ];
    }

    /**
     * Resumo executivo (cards principais).
     */
    /**
     * Resumo executivo (cards principais).
     *
     * FIX v3.0: Resultado agora inclui DEDUCAO (3.01.03.*).
     * Formula: Resultado = ReceitaTotal - Deducoes - Despesas
     * Validado contra Arvore do Plano de Contas (DataJuri).
     */
    private function getResumoExecutivo(int $ano, int $mes): array
    {
        [$pAno, $pMes] = $this->prevCompetencia($ano, $mes);

        $receitaPf = $this->sumReceitaTipo($ano, $mes, 'pf');
        $receitaPj = $this->sumReceitaTipo($ano, $mes, 'pj');
        $receitaTotal = $receitaPf + $receitaPj;

        // FIX v3.0: Incluir deducoes (Simples Nacional, INSS, etc.)
        $deducoesTotal = $this->deducoesTotal($ano, $mes);
        $despesasTotal = (float) $this->despesasOperacionaisTotal($ano, $mes);

        // FIX v3.0: Resultado = Receita - Deducoes - Despesas
        $resultado = $receitaTotal - $deducoesTotal - $despesasTotal;
        $margem = $receitaTotal > 0 ? ($resultado / $receitaTotal) * 100 : 0.0;

        $receitaPfPrev = $this->sumReceitaTipo($pAno, $pMes, 'pf');
        $receitaPjPrev = $this->sumReceitaTipo($pAno, $pMes, 'pj');
        $receitaPrev = $receitaPfPrev + $receitaPjPrev;
        $deducoesPrev = $this->deducoesTotal($pAno, $pMes);
        $despesasPrev = (float) $this->despesasOperacionaisTotal($pAno, $pMes);
        $resultadoPrev = $receitaPrev - $deducoesPrev - $despesasPrev;
        $margemPrev = $receitaPrev > 0 ? ($resultadoPrev / $receitaPrev) * 100 : 0.0;

        $metaPf = (float) Configuracao::get("meta_pf_{$ano}_{$mes}", 0);
        $metaPj = (float) Configuracao::get("meta_pj_{$ano}_{$mes}", 0);
        $metaReceita = $metaPf + $metaPj;
        $metaDespesas = (float) Configuracao::get("meta_despesas_{$ano}_{$mes}", 0);

        $metaResultado = (float) Configuracao::get("meta_resultado_{$ano}_{$mes}", max($metaReceita - $metaDespesas, 0));
        $metaMargem = (float) Configuracao::get(
            "meta_margem_{$ano}_{$mes}",
            $metaReceita > 0 ? round((($metaReceita - $metaDespesas) / $metaReceita) * 100, 1) : 0
        );

        return [
            'receitaPf' => round($receitaPf, 2),
            'receitaPj' => round($receitaPj, 2),
            'receitaTotal' => round($receitaTotal, 2),
            'receitaMeta' => round($metaReceita, 2),
            'receitaTrend' => $this->percentChange($receitaTotal, $receitaPrev),
            'receitaPrev' => round($receitaPrev, 2),
            'deducoesTotal' => round($deducoesTotal, 2),
            'deducoesTrend' => $this->percentChange($deducoesTotal, $deducoesPrev),
            'despesasTotal' => round($despesasTotal, 2),
            'despesasMeta' => round($metaDespesas, 2),
            'despesasTrend' => $this->percentChange($despesasTotal, $despesasPrev),
            'resultadoLiquido' => round($resultado, 2),
            'resultadoMeta' => round($metaResultado, 2),
            'resultadoTrend' => $this->percentChange($resultado, $resultadoPrev),
            'margemLiquida' => round($margem, 1),
            'margemMeta' => round($metaMargem, 1),
            'margemTrend' => $this->percentChange($margem, $margemPrev),
        ];
    }

    /**
     * Totais básicos do mês.
     */
    /**
     * Totais basicos do mes.
     * FIX v3.0: Inclui deducoes no calculo do resultado.
     */
    private function resumoBasico(int $ano, int $mes): array
    {
        $receitaPf = $this->sumReceitaTipo($ano, $mes, 'pf');
        $receitaPj = $this->sumReceitaTipo($ano, $mes, 'pj');
        $receita = $receitaPf + $receitaPj;

        $deducoes = $this->deducoesTotal($ano, $mes);
        $despesas = (float) $this->despesasOperacionaisTotal($ano, $mes);
        $resultado = $receita - $deducoes - $despesas;
        $margem = $receita > 0 ? ($resultado / $receita) * 100 : 0.0;

        return [
            'receita' => round($receita, 2),
            'deducoes' => round($deducoes, 2),
            'despesas' => round($despesas, 2),
            'resultado' => round($resultado, 2),
            'margem' => round($margem, 1),
        ];
    }

    private function overdueQuery(Carbon $ref)
    {
        return ContaReceber::query()
            ->where('status', 'Não lançado')
            ->whereNull('data_pagamento')
            ->whereNotNull('data_vencimento')
            ->whereDate('data_vencimento', '<=', $ref->toDateString())
            ->whereDate('data_vencimento', '>=', $ref->copy()->subDays(90)->toDateString());
    }

    private function avgDiasAtraso($contas, Carbon $ref): int
    {
        if ($contas->count() === 0) return 0;

        $sum = 0;
        $n = 0;
        foreach ($contas as $c) {
            if (!$c->data_vencimento) continue;
            $sum += max(0, (int) $c->data_vencimento->diffInDays($ref, false));
            $n++;
        }

        return $n > 0 ? (int) round($sum / $n) : 0;
    }

    /**
     * Taxa de cobrança de um mês.
     *
     * Heurística:
     * - total_due: contas com vencimento no mês
     * - total_paid: contas com pagamento no mês
     */
    private function taxaCobrancaMes(int $ano, int $mes): array
    {
        $start = Carbon::create($ano, $mes, 1)->startOfMonth();
        $end = Carbon::create($ano, $mes, 1)->endOfMonth();

        $totalDue = (float) ContaReceber::query()
            ->where('status', 'Não lançado')
            ->whereNotNull('data_vencimento')
            ->whereBetween('data_vencimento', [$start->toDateString(), $end->toDateString()])
            ->sum('valor');

        $totalPaid = (float) ContaReceber::query()
            ->whereNotNull('data_pagamento')
            ->whereBetween('data_pagamento', [$start->toDateString(), $end->toDateString()])
            ->sum('valor');

        $taxa = $totalDue > 0 ? round(($totalPaid / $totalDue) * 100, 1) : 0.0;
        return [$taxa, $totalDue, $totalPaid];
    }

    private function taxaCobrancaMesVsPrev(int $ano, int $mes): array
    {
        [$pAno, $pMes] = $this->prevCompetencia($ano, $mes);
        [$taxa] = $this->taxaCobrancaMes($ano, $mes);
        [$taxaPrev] = $this->taxaCobrancaMes($pAno, $pMes);
        return [$taxa, $taxaPrev];
    }

    private function getAgingContas(int $ano, int $mes): array
    {
        $ref = $this->refDateCompetencia($ano, $mes);
        $contas = $this->overdueQuery($ref)->get();
        $buckets = [
            'dias0_15' => 0.0,
            'dias16_30' => 0.0,
            'dias31_60' => 0.0,
            'dias61_90' => 0.0,
            'dias91_120' => 0.0,
            'dias120_plus' => 0.0,
        ];
        foreach ($contas as $c) {
            $dias = max(0, (int) $c->data_vencimento->diffInDays($ref, false));
            $v = (float) $c->valor;
            if ($dias <= 15) $buckets['dias0_15'] += $v;
            elseif ($dias <= 30) $buckets['dias16_30'] += $v;
            elseif ($dias <= 60) $buckets['dias31_60'] += $v;
            elseif ($dias <= 90) $buckets['dias61_90'] += $v;
            elseif ($dias <= 120) $buckets['dias91_120'] += $v;
            else $buckets['dias120_plus'] += $v;
        }
        foreach ($buckets as $k => $v) {
            $buckets[$k] = round((float) $v, 2);
        }
        return $buckets;
    }

    private function prevCompetencia(int $ano, int $mes): array
    {
        $c = Carbon::create($ano, $mes, 1)->subMonth();
        return [(int) $c->year, (int) $c->month];
    }

    /**
     * Data de referência para "atraso" dentro da competência.
     * - para meses passados: último dia do mês
     * - para o mês atual: hoje
     */
    private function refDateCompetencia(int $ano, int $mes): Carbon
    {
        $now = Carbon::now();
        $endOfMonth = Carbon::create($ano, $mes, 1)->endOfMonth();
        if ($endOfMonth->greaterThan($now)) {
            return $now->copy()->endOfDay();
        }
        return $endOfMonth;
    }

    private function getMetasMensais(string $tipo, int $ano): array
    {
        $metas = [];
        for ($m = 1; $m <= 12; $m++) {
            $metas[$m] = (float) Configuracao::get("{$tipo}_{$ano}_{$m}", 0);
        }
        return $metas;
    }

    /**
     * Aplica filtro de exclusão de rubricas não operacionais a uma query de Movimento.
     */
    private function applyRubricasExcluidas($query)
    {
        foreach (self::RUBRICAS_EXCLUIDAS as $kw) {
            $like = '%' . $kw . '%';
            // Filtra por plano_contas e descricao (quando houver)
            $query->whereRaw(
                "LOWER(CONCAT(COALESCE(plano_contas,''), ' ', COALESCE(descricao,''))) NOT LIKE ?",
                [$like]
            );
        }
        return $query;
    }

    private function isDespesaOperacional(string $rubrica): bool
    {
        $r = mb_strtolower($rubrica);
        foreach (self::RUBRICAS_EXCLUIDAS as $kw) {
            if (str_contains($r, $kw)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Soma deducoes da receita do mes (classificacao = DEDUCAO).
     *
     * FIX v3.0: Metodo novo. Deducoes sao 3.01.03.* (Simples Nacional,
     * INSS, Salarios, Distribuicao de lucros, etc.).
     * Valores estao armazenados como POSITIVOS no DB (abs no sync).
     * Retorna valor positivo (para subtrair da receita no caller).
     */
    private function deducoesTotal(int $ano, int $mes): float
    {
        return (float) abs(Movimento::where('ano', $ano)
            ->where('mes', $mes)
            ->where('classificacao', Movimento::DEDUCAO)
            ->sum('valor'));
    }

    /**
     * Deducoes por mes (12 meses).
     * FIX v3.0: Metodo novo para getLucratividadeByMonth.
     */
    private function deducoesByMonth(int $ano): array
    {
        $out = array_fill(0, 12, 0.0);

        $rows = Movimento::select(DB::raw('mes'), DB::raw('SUM(valor) as total'))
            ->where('ano', $ano)
            ->where('classificacao', Movimento::DEDUCAO)
            ->groupBy('mes')
            ->pluck('total', 'mes')
            ->toArray();

        foreach ($rows as $m => $t) {
            $idx = ((int) $m) - 1;
            if ($idx >= 0 && $idx < 12) {
                $out[$idx] = (float) abs($t);
            }
        }

        return array_map('floatval', $out);
    }

    /**
     * Soma despesas operacionais do mês (aplicando exclusões).
     */
    private function despesasOperacionaisTotal(int $ano, int $mes): float
    {
        $q = Movimento::where('ano', $ano)
            ->where('mes', $mes)
            ->where('classificacao', Movimento::DESPESA);

        $this->applyRubricasExcluidas($q);

        // ✅ CORREÇÃO: Despesas retornam como valores POSITIVOS usando abs()
        return (float) abs($q->sum('valor'));
    }

    /**
     * Despesas operacionais por mês (12 meses).
     *
     * @return array<int,float>
     */
    private function despesasOperacionaisByMonth(int $ano): array
    {
        $out = array_fill(0, 12, 0.0);

        $q = Movimento::select(DB::raw('mes'), DB::raw('SUM(valor) as total'))
            ->where('ano', $ano)
            ->where('classificacao', Movimento::DESPESA);
        $this->applyRubricasExcluidas($q);

        $rows = $q->groupBy('mes')->pluck('total', 'mes')->toArray();

        foreach ($rows as $m => $t) {
            $idx = ((int) $m) - 1;
            // ✅ CORREÇÃO: Despesas retornam como valores POSITIVOS usando abs()
            if ($idx >= 0 && $idx < 12) $out[$idx] = (float) abs($t);
        }

        return array_map('floatval', $out);
    }

    private function getMesesAbrev(): array
    {
        return ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    }

    private function percentChange(float $current, float $previous): float
    {
        if ($previous == 0.0) {
            return $current == 0.0 ? 0.0 : 100.0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function statusAtraso(int $dias): string
    {
        if ($dias > 45) return 'critico';
        if ($dias >= 31) return 'atencao';
        if ($dias >= 16) return 'aviso';
        return 'ok';
    }

    private function normalizarRubrica(string $plano): string
    {
        $plano = trim($plano);
        if ($plano === '') return '—';

        // Preferir o trecho após o último "-" (padrão: "3.02.01.01 - Aluguel")
        if (str_contains($plano, '-')) {
            $parts = explode('-', $plano);
            $last = trim(end($parts));
            return $last !== '' ? $last : $plano;
        }
        // Ou o trecho após o último ":" (padrão: hierarquia do DataJuri)
        if (str_contains($plano, ':')) {
            $parts = explode(':', $plano);
            $last = trim(end($parts));
            return $last !== '' ? $last : $plano;
        }
        return $plano;
    }

    private function getRubricasVariacaoMoM(int $ano, int $mes): array
    {
        [$prevAno, $prevMes] = $this->prevCompetencia($ano, $mes);
        
        $atualRaw = $this->getDespesasByRubrica($ano, $mes);
        $anteriorRaw = $this->getDespesasByRubrica($prevAno, $prevMes);
        
        $atual = [];
        foreach ($atualRaw as $item) {
            $atual[$item['rubrica']] = (float) $item['valor'];
        }
        
        $anterior = [];
        foreach ($anteriorRaw as $item) {
            $anterior[$item['rubrica']] = (float) $item['valor'];
        }
        
        $variacoes = [];
        $todasRubricas = array_unique(array_merge(array_keys($atual), array_keys($anterior)));
        
        foreach ($todasRubricas as $rubrica) {
            $vAtual = $atual[$rubrica] ?? 0.0;
            $vAnterior = $anterior[$rubrica] ?? 0.0;
            
            $diff = $vAtual - $vAnterior;
            $pct = 0.0;
            if ($vAnterior > 0) {
                $pct = ($diff / $vAnterior) * 100;
            } elseif ($vAtual > 0) {
                $pct = 100.0;
            }
            
            $variacoes[] = [
                'rubrica' => $this->normalizarRubrica($rubrica),
                'atual' => $vAtual,
                'anterior' => $vAnterior,
                'diff' => $diff,
                'pct' => round($pct, 1)
            ];
        }
        
        usort($variacoes, fn($a, $b) => $b['diff'] <=> $a['diff']);
        $maioresAumentos = array_slice(array_filter($variacoes, fn($v) => $v['diff'] > 0), 0, 5);
        
        usort($variacoes, fn($a, $b) => $a['diff'] <=> $b['diff']);
        $maioresReducoes = array_slice(array_filter($variacoes, fn($v) => $v['diff'] < 0), 0, 5);
        
        return [
            'topAumentos' => $maioresAumentos,
            'topReducoes' => $maioresReducoes
        ];
    }
}
