<?php

namespace App\Services\Vigilia;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\VigiliaCruzamento;

class VigiliaService
{
    /**
     * Tipos de atividade que são jurídicos e devem ter andamento no processo.
     * Atividades não-jurídicas (Reunião, Atendimento, Treinamento) são marcadas N/A.
     */
    const TIPOS_JURIDICOS = [
        'Compromisso (Prazo)',
        'Compromisso (Elaboração de petição)',
        'Compromisso (Cumprimento de decisão)',
        'Compromisso (Petição)',
        'Compromisso (Petições)',
        'Compromisso (Protocolo de documentos)',
        'Compromisso (Interposição de recurso)',
        'Compromisso (Prazo automático)',
        'Compromisso (Análise processual)',
        'Compromisso (Diligências)',
        'Tarefa',
    ];

    const TIPOS_NAO_APLICAVEIS = [
        'Compromisso (Reunião)',
        'Compromisso (Reunião Interna)',
        'Compromisso (Atendimento)',
        'Compromisso (Atendimento ao cliente)',
        'Compromisso (Treinamento)',
        'Compromisso (Analises)',
    ];

    /**
     * Janela de busca: andamentos até X dias APÓS a data da atividade.
     */
    const JANELA_DIAS_APOS = 15;

    /**
     * Janela de busca: andamentos até X dias ANTES da data da atividade.
     */
    const JANELA_DIAS_ANTES = 5;

    // ─── RESUMO GERAL ───────────────────────────────────────────────

    public function getResumoGeral(?string $periodoInicio = null, ?string $periodoFim = null): array
    {
        $query = DB::table('atividades_datajuri');

        if ($periodoInicio && $periodoFim) {
            $query->whereBetween('data_hora', [$periodoInicio, $periodoFim]);
        }

        $total = (clone $query)->count();
        $concluidos = (clone $query)->where('status', 'Concluído')->count();
        $naoIniciados = (clone $query)->where('status', 'Não iniciado')->count();
        $cancelados = (clone $query)->where('status', 'Cancelado')->count();

        $alertasQuery = DB::table('vigilia_cruzamentos as vc2')
            ->join('atividades_datajuri as ad2', 'ad2.id', '=', 'vc2.atividade_datajuri_id')
            ->whereIn('vc2.status_cruzamento', ['suspeito', 'sem_acao']);

        if ($periodoInicio && $periodoFim) {
            $alertasQuery->whereBetween('ad2.data_hora', [$periodoInicio, $periodoFim]);
        }

        $alertas = $alertasQuery->count();

        $taxa = $total > 0 ? round(($concluidos / $total) * 100, 1) : 0;

        return compact('total', 'concluidos', 'naoIniciados', 'cancelados', 'alertas', 'taxa');
    }

    // ─── PERFORMANCE POR RESPONSÁVEL ─────────────────────────────────

    public function getPerformancePorResponsavel(?string $periodoInicio = null, ?string $periodoFim = null): array
    {
        $query = DB::table('atividades_datajuri')
            ->select(
                'responsavel_nome',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'Concluído' THEN 1 ELSE 0 END) as concluidos"),
                DB::raw("SUM(CASE WHEN status = 'Não iniciado' THEN 1 ELSE 0 END) as nao_iniciados"),
                DB::raw("SUM(CASE WHEN status = 'Cancelado' THEN 1 ELSE 0 END) as cancelados")
            )
            ->whereNotNull('responsavel_nome')
            ->where('responsavel_nome', '!=', '');

        if ($periodoInicio && $periodoFim) {
            $query->whereBetween('data_hora', [$periodoInicio, $periodoFim]);
        }

        $rows = $query->groupBy('responsavel_nome')
            ->orderByDesc('total')
            ->get();

        // Enriquecer com contagem de alertas
        $result = [];
        foreach ($rows as $row) {
            $alertas = DB::table('vigilia_cruzamentos as vc')
                ->join('atividades_datajuri as ad', 'ad.id', '=', 'vc.atividade_datajuri_id')
                ->where('ad.responsavel_nome', $row->responsavel_nome)
                ->whereIn('vc.status_cruzamento', ['suspeito', 'sem_acao'])
                ->when($periodoInicio && $periodoFim, fn($q) => $q->whereBetween('ad.data_hora', [$periodoInicio, $periodoFim]))
                ->count();

            $taxa = $row->total > 0 ? round(($row->concluidos / $row->total) * 100, 1) : 0;

            $result[] = [
                'responsavel_nome' => $row->responsavel_nome,
                'total' => (int) $row->total,
                'concluidos' => (int) $row->concluidos,
                'nao_iniciados' => (int) $row->nao_iniciados,
                'cancelados' => (int) $row->cancelados,
                'alertas' => $alertas,
                'taxa' => $taxa,
            ];
        }

        return $result;
    }

    // ─── ALERTAS ATIVOS ──────────────────────────────────────────────

    public function getAlertasAtivos(?string $responsavel = null): array
    {
        $query = DB::table('vigilia_cruzamentos as vc')
            ->join('atividades_datajuri as ad', 'ad.id', '=', 'vc.atividade_datajuri_id')
            ->whereIn('vc.status_cruzamento', ['suspeito', 'sem_acao'])
            ->select(
                'vc.id',
                'vc.status_cruzamento',
                'vc.dias_gap',
                'vc.data_ultimo_andamento',
                'vc.observacao',
                'ad.responsavel_nome',
                'ad.processo_pasta',
                'ad.tipo_atividade',
                'ad.data_hora',
                'ad.data_prazo_fatal',
                'ad.status as atividade_status',
                'ad.datajuri_id'
            );

        if ($responsavel) {
            $query->where('ad.responsavel_nome', $responsavel);
        }

        return $query->orderByDesc('vc.dias_gap')->get()->map(function ($row) {
            $diasAtraso = null;
            if ($row->data_prazo_fatal) {
                $diasAtraso = Carbon::parse($row->data_prazo_fatal)->diffInDays(Carbon::today(), false);
            }

            $severidade = 'medio';
            if ($row->status_cruzamento === 'sem_acao' && $diasAtraso !== null && $diasAtraso > 0) {
                $severidade = 'critico';
            } elseif ($row->status_cruzamento === 'suspeito') {
                $severidade = 'alto';
            } elseif ($diasAtraso !== null && $diasAtraso > 0) {
                $severidade = 'critico';
            }

            return [
                'id' => $row->id,
                'tipo' => $row->status_cruzamento === 'suspeito' ? 'CONCLUSAO_SUSPEITA' : ($diasAtraso > 0 ? 'PRAZO_VENCIDO' : 'PRAZO_VENCENDO'),
                'severidade' => $severidade,
                'responsavel' => $row->responsavel_nome,
                'processo' => $row->processo_pasta,
                'tipo_atividade' => $row->tipo_atividade,
                'data_atividade' => $row->data_hora,
                'prazo_fatal' => $row->data_prazo_fatal,
                'dias_atraso' => $diasAtraso,
                'dias_gap' => $row->dias_gap,
                'data_ultimo_andamento' => $row->data_ultimo_andamento,
                'tem_andamento' => $row->data_ultimo_andamento !== null,
                'observacao' => $row->observacao,
            ];
        })->toArray();
    }

    // ─── COMPROMISSOS COM FILTROS ────────────────────────────────────

    public function getCompromissos(array $filtros = []): array
    {
        $query = DB::table('atividades_datajuri as ad')
            ->leftJoin('vigilia_cruzamentos as vc', 'vc.atividade_datajuri_id', '=', 'ad.id')
            ->select(
                'ad.id',
                'ad.datajuri_id',
                'ad.status',
                'ad.tipo_atividade',
                'ad.data_hora',
                'ad.data_conclusao',
                'ad.data_prazo_fatal',
                'ad.processo_pasta',
                'ad.responsavel_nome',
                'vc.status_cruzamento',
                'vc.dias_gap',
                'vc.data_ultimo_andamento'
            );

        if (!empty($filtros['responsavel'])) {
            $query->where('ad.responsavel_nome', $filtros['responsavel']);
        }
        if (!empty($filtros['status'])) {
            $query->where('ad.status', $filtros['status']);
        }
        if (!empty($filtros['periodo_inicio']) && !empty($filtros['periodo_fim'])) {
            $query->whereBetween('ad.data_hora', [$filtros['periodo_inicio'], $filtros['periodo_fim']]);
        }
        if (!empty($filtros['tipo_atividade'])) {
            $query->where('ad.tipo_atividade', $filtros['tipo_atividade']);
        }
        if (!empty($filtros['somente_alertas'])) {
            $query->whereIn('vc.status_cruzamento', ['suspeito', 'sem_acao']);
        }

        $perPage = $filtros['per_page'] ?? 50;
        $page = $filtros['page'] ?? 1;

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('ad.data_hora')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'data' => $rows->toArray(),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
        ];
    }

    // ─── EXECUTAR CRUZAMENTO ─────────────────────────────────────────

    public function executarCruzamento(): array
    {
        $stats = ['verificados' => 0, 'suspeitos' => 0, 'sem_acao' => 0, 'nao_aplicavel' => 0, 'futuro' => 0, 'pendente' => 0, 'verificado' => 0, 'suspeito' => 0, 'total' => 0];

        // Buscar somente atividades de 2026 em diante (histórico antigo é irrelevante)
        $atividades = DB::table('atividades_datajuri')
            ->whereIn('status', ['Concluído', 'Não iniciado', 'Aguardando outra pessoa'])
            ->where('data_hora', '>=', '2026-01-01 00:00:00')
            ->get();

        $stats['total'] = $atividades->count();

        // Pré-carregar mapa: processo_pasta → [fase_datajuri_ids]
        $fasesMap = DB::table('fases_processo')
            ->whereNotNull('processo_pasta')
            ->where('processo_pasta', '!=', '')
            ->pluck('datajuri_id', 'processo_pasta')
            ->toArray();

        // Processar em lotes
        $upserts = [];

        foreach ($atividades as $ativ) {
            $resultado = $this->cruzarAtividade($ativ, $fasesMap);
            $key = $resultado['status_cruzamento'];
            if (!isset($stats[$key])) {
                $stats[$key] = 0;
            }
            $stats[$key]++;

            $upserts[] = [
                'atividade_datajuri_id' => $ativ->id,
                'andamento_fase_id' => $resultado['andamento_fase_id'],
                'status_cruzamento' => $resultado['status_cruzamento'],
                'dias_gap' => $resultado['dias_gap'],
                'data_ultimo_andamento' => $resultado['data_ultimo_andamento'],
                'observacao' => $resultado['observacao'],
                'updated_at' => now(),
            ];

            // Inserir em lotes de 500
            if (count($upserts) >= 500) {
                $this->upsertCruzamentos($upserts);
                $upserts = [];
            }
        }

        // Inserir restantes
        if (count($upserts) > 0) {
            $this->upsertCruzamentos($upserts);
        }

        // Remover cruzamentos de atividades canceladas
        DB::table('vigilia_cruzamentos as vc')
            ->join('atividades_datajuri as ad', 'ad.id', '=', 'vc.atividade_datajuri_id')
            ->where('ad.status', 'Cancelado')
            ->delete();

        return $stats;
    }

    /**
     * Padrões de andamento que indicam AÇÃO DO ESCRITÓRIO (advogada trabalhou).
     * Tudo que não bater aqui é considerado ação do tribunal (não conta).
     */
    const ANDAMENTO_ESCRITORIO = [
        'Juntada de Petição',
        'Petição Juntada',
        'PETIÇÃO',
        'Petição juntada',
        'CIÊNCIA, COM RENÚNCIA AO PRAZO',
        'Ciência',
    ];

    const ANDAMENTO_AUDIENCIA = [
        'Audiência',
        'audiência',
        'Realizada audiência',
        'Ata de Audiência',
    ];

    /**
     * Mapeamento: tipo de compromisso → padrões de andamento esperados.
     * Se o compromisso é de petição, busca andamentos de petição.
     * Se é audiência, busca andamentos de audiência.
     */
    private function getPadroesEsperados(?string $tipoAtividade): array
    {
        if (!$tipoAtividade) return self::ANDAMENTO_ESCRITORIO;

        $tipo = strtolower($tipoAtividade);

        // Audiências
        if (str_contains($tipo, 'audiência') || str_contains($tipo, 'audiencia')) {
            return array_merge(self::ANDAMENTO_AUDIENCIA, self::ANDAMENTO_ESCRITORIO);
        }

        // Petições, recursos, contestação, manifestação, elaboração, protocolo
        if (str_contains($tipo, 'petição') || str_contains($tipo, 'petições') ||
            str_contains($tipo, 'recurso') || str_contains($tipo, 'contestação') ||
            str_contains($tipo, 'manifestação') || str_contains($tipo, 'elaboração') ||
            str_contains($tipo, 'protocolo') || str_contains($tipo, 'interposição') ||
            str_contains($tipo, 'cumprimento')) {
            return self::ANDAMENTO_ESCRITORIO;
        }

        // Prazo genérico — pode resultar em petição ou ciência
        if (str_contains($tipo, 'prazo')) {
            return array_merge(self::ANDAMENTO_ESCRITORIO, ['Decurso de prazo']);
        }

        // Diligência, análise — petição é o resultado mais comum
        return self::ANDAMENTO_ESCRITORIO;
    }

    private function cruzarAtividade(object $ativ, array $fasesMap): array
    {
        $default = [
            'andamento_fase_id' => null,
            'status_cruzamento' => 'pendente',
            'dias_gap' => null,
            'data_ultimo_andamento' => null,
            'observacao' => null,
        ];

        // 1. Tipo não-jurídico → N/A
        if (in_array($ativ->tipo_atividade, self::TIPOS_NAO_APLICAVEIS) || !in_array($ativ->tipo_atividade, self::TIPOS_JURIDICOS)) {
            $default['status_cruzamento'] = 'nao_aplicavel';
            $default['observacao'] = 'Tipo não-jurídico: ' . ($ativ->tipo_atividade ?? 'desconhecido');
            return $default;
        }

        // 2. Sem processo vinculado → N/A
        if (empty($ativ->processo_pasta)) {
            $default['status_cruzamento'] = 'nao_aplicavel';
            $default['observacao'] = 'Sem processo vinculado';
            return $default;
        }

        // 3. Atividade futura → Futuro
        $dataAtiv = $ativ->data_hora ? Carbon::parse($ativ->data_hora) : null;
        if ($dataAtiv && $dataAtiv->isFuture()) {
            $default['status_cruzamento'] = 'futuro';
            $default['observacao'] = 'Atividade futura: ' . $dataAtiv->format('d/m/Y');
            return $default;
        }

        // 4. Buscar fases do processo
        $faseIds = [];
        foreach ($fasesMap as $pasta => $faseId) {
            if ($pasta === $ativ->processo_pasta) {
                $faseIds[] = $faseId;
            }
        }

        if (empty($faseIds)) {
            $default['status_cruzamento'] = 'nao_aplicavel';
            $default['observacao'] = 'Processo sem fases cadastradas: ' . $ativ->processo_pasta;
            return $default;
        }

        // 5. Buscar andamentos COMPATÍVEIS na janela de tempo
        $dataRef = $dataAtiv ?? Carbon::today();
        $inicio = $dataRef->copy()->subDays(self::JANELA_DIAS_ANTES);
        $fim = $dataRef->copy()->addDays(self::JANELA_DIAS_APOS);

        $padroesEsperados = $this->getPadroesEsperados($ativ->tipo_atividade);

        // Query: buscar andamentos na janela que contenham algum dos padrões esperados
        $andamentoQuery = DB::table('andamentos_fase')
            ->whereIn('fase_processo_id_datajuri', $faseIds)
            ->whereBetween('data_andamento', [$inicio->toDateString(), $fim->toDateString()])
            ->where(function ($q) use ($padroesEsperados) {
                foreach ($padroesEsperados as $padrao) {
                    $q->orWhere('descricao', 'LIKE', '%' . $padrao . '%');
                }
            })
            ->orderByDesc('data_andamento')
            ->first();

        // 6. Buscar último andamento DO ESCRITÓRIO (sem janela) para calcular gap
        $ultimoAndEscritorio = DB::table('andamentos_fase')
            ->whereIn('fase_processo_id_datajuri', $faseIds)
            ->where(function ($q) {
                foreach (self::ANDAMENTO_ESCRITORIO as $padrao) {
                    $q->orWhere('descricao', 'LIKE', '%' . $padrao . '%');
                }
            })
            ->orderByDesc('data_andamento')
            ->first();

        $dataUltimoAnd = $ultimoAndEscritorio ? $ultimoAndEscritorio->data_andamento : null;
        $diasGap = null;
        if ($dataUltimoAnd && $dataAtiv) {
            $diasGap = abs(Carbon::parse($dataUltimoAnd)->diffInDays($dataAtiv));
        }

        // 7. Determinar status
        if ($ativ->status === 'Concluído') {
            if ($andamentoQuery) {
                $default['status_cruzamento'] = 'verificado';
                $default['andamento_fase_id'] = $andamentoQuery->id;
                $default['observacao'] = 'Andamento compatível: ' . mb_substr($andamentoQuery->descricao ?? '', 0, 120);
            } else {
                $default['status_cruzamento'] = 'suspeito';
                $default['observacao'] = $dataUltimoAnd
                    ? 'Concluído mas sem andamento do escritório na janela. Última ação: ' . Carbon::parse($dataUltimoAnd)->format('d/m/Y')
                    : 'Concluído mas nenhuma petição/ação do escritório encontrada no processo';
            }
        } elseif ($ativ->status === 'Não iniciado' || $ativ->status === 'Aguardando outra pessoa') {
            $prazoFatal = $ativ->data_prazo_fatal ? Carbon::parse($ativ->data_prazo_fatal) : null;
            $vencido = $prazoFatal && $prazoFatal->isPast();
            $dataPassada = $dataAtiv && $dataAtiv->isPast();

            if ($vencido) {
                $default['status_cruzamento'] = 'sem_acao';
                $default['observacao'] = 'Prazo fatal vencido em ' . $prazoFatal->format('d/m/Y') . ' sem conclusão';
            } elseif ($dataPassada && !$andamentoQuery) {
                $default['status_cruzamento'] = 'sem_acao';
                $default['observacao'] = 'Data passada (' . $dataAtiv->format('d/m/Y') . '), sem conclusão e sem ação do escritório no processo';
            } else {
                $default['status_cruzamento'] = 'futuro';
                $default['observacao'] = 'Ainda no prazo';
            }
        }

        $default['dias_gap'] = $diasGap;
        $default['data_ultimo_andamento'] = $dataUltimoAnd;

        return $default;
    }

    private function upsertCruzamentos(array $rows): void
    {
        foreach ($rows as $row) {
            DB::table('vigilia_cruzamentos')->updateOrInsert(
                ['atividade_datajuri_id' => $row['atividade_datajuri_id']],
                $row
            );
        }
    }

    // ─── RELATÓRIO INDIVIDUAL ────────────────────────────────────────

    public function getRelatorioIndividual(string $responsavel, ?string $periodoInicio = null, ?string $periodoFim = null): array
    {
        $filtros = ['responsavel' => $responsavel];
        if ($periodoInicio && $periodoFim) {
            $filtros['periodo_inicio'] = $periodoInicio;
            $filtros['periodo_fim'] = $periodoFim;
        }
        $filtros['per_page'] = 9999;

        $compromissos = $this->getCompromissos($filtros);
        $alertas = $this->getAlertasAtivos($responsavel);

        $data = collect($compromissos['data']);
        $total = $data->count();
        $concluidos = $data->where('status', 'Concluído')->count();
        $naoIniciados = $data->where('status', 'Não iniciado')->count();
        $cancelados = $data->where('status', 'Cancelado')->count();
        $taxa = $total > 0 ? round(($concluidos / $total) * 100, 1) : 0;

        // Confiabilidade
        $verificados = $data->where('status_cruzamento', 'verificado')->count();
        $suspeitos = $data->where('status_cruzamento', 'suspeito')->count();
        $aplicaveis = $verificados + $suspeitos;
        $confiabilidade = $aplicaveis > 0 ? round(($verificados / $aplicaveis) * 100, 1) : null;

        return [
            'responsavel' => $responsavel,
            'total' => $total,
            'concluidos' => $concluidos,
            'nao_iniciados' => $naoIniciados,
            'cancelados' => $cancelados,
            'taxa' => $taxa,
            'alertas' => $alertas,
            'confiabilidade' => $confiabilidade,
            'verificados' => $verificados,
            'suspeitos' => $suspeitos,
            'compromissos' => $compromissos['data'],
        ];
    }

    // ─── RELATÓRIO DE PRAZOS CRÍTICOS ────────────────────────────────

    public function getRelatorioPrazos(): array
    {
        $hoje = Carbon::today();

        // Prazos vencidos: não iniciado + prazo fatal passado
        $vencidos = DB::table('atividades_datajuri')
            ->where('status', 'Não iniciado')
            ->whereNotNull('data_prazo_fatal')
            ->where('data_prazo_fatal', '<', $hoje->toDateString())
            ->orderBy('data_prazo_fatal')
            ->get()
            ->map(fn($r) => $this->enrichPrazo($r, $hoje));

        // Vencendo em 3 dias
        $vencendo = DB::table('atividades_datajuri')
            ->where('status', 'Não iniciado')
            ->whereNotNull('data_prazo_fatal')
            ->whereBetween('data_prazo_fatal', [$hoje->toDateString(), $hoje->copy()->addDays(3)->toDateString()])
            ->orderBy('data_prazo_fatal')
            ->get()
            ->map(fn($r) => $this->enrichPrazo($r, $hoje));

        // Conclusões suspeitas
        $suspeitas = DB::table('vigilia_cruzamentos as vc')
            ->join('atividades_datajuri as ad', 'ad.id', '=', 'vc.atividade_datajuri_id')
            ->where('vc.status_cruzamento', 'suspeito')
            ->orderByDesc('vc.dias_gap')
            ->select('ad.*', 'vc.dias_gap', 'vc.data_ultimo_andamento', 'vc.observacao as vc_obs')
            ->get();

        return [
            'data_geracao' => now()->format('d/m/Y H:i'),
            'vencidos' => $vencidos->toArray(),
            'vencendo' => $vencendo->toArray(),
            'suspeitas' => $suspeitas->toArray(),
            'contadores' => [
                'vencidos' => $vencidos->count(),
                'vencendo' => $vencendo->count(),
                'suspeitas' => $suspeitas->count(),
            ],
        ];
    }

    private function enrichPrazo(object $row, Carbon $hoje): array
    {
        $prazo = Carbon::parse($row->data_prazo_fatal);
        $diasAtraso = $prazo->diffInDays($hoje, false);

        return [
            'id' => $row->id,
            'responsavel' => $row->responsavel_nome,
            'tipo_atividade' => $row->tipo_atividade,
            'processo_pasta' => $row->processo_pasta,
            'data_hora' => $row->data_hora,
            'prazo_fatal' => $row->data_prazo_fatal,
            'dias_atraso' => $diasAtraso,
            'status' => $row->status,
        ];
    }

    // ─── RELATÓRIO CONSOLIDADO MENSAL ────────────────────────────────

    public function getRelatorioConsolidado(string $periodoInicio, string $periodoFim): array
    {
        $resumo = $this->getResumoGeral($periodoInicio, $periodoFim);
        $ranking = $this->getPerformancePorResponsavel($periodoInicio, $periodoFim);

        // Distribuição por tipo
        $distribuicao = DB::table('atividades_datajuri')
            ->select('tipo_atividade', DB::raw('COUNT(*) as qtd'))
            ->whereBetween('data_hora', [$periodoInicio, $periodoFim])
            ->whereNotNull('tipo_atividade')
            ->groupBy('tipo_atividade')
            ->orderByDesc('qtd')
            ->limit(15)
            ->get()
            ->toArray();

        // Cruzamento stats do período
        $cruzStats = DB::table('vigilia_cruzamentos as vc')
            ->join('atividades_datajuri as ad', 'ad.id', '=', 'vc.atividade_datajuri_id')
            ->whereBetween('ad.data_hora', [$periodoInicio, $periodoFim])
            ->select('vc.status_cruzamento', DB::raw('COUNT(*) as qtd'))
            ->groupBy('vc.status_cruzamento')
            ->pluck('qtd', 'status_cruzamento')
            ->toArray();

        return [
            'periodo' => ['inicio' => $periodoInicio, 'fim' => $periodoFim],
            'data_geracao' => now()->format('d/m/Y H:i'),
            'resumo' => $resumo,
            'ranking' => $ranking,
            'distribuicao' => $distribuicao,
            'cruzamento' => $cruzStats,
        ];
    }

    // ─── RELATÓRIO DE CRUZAMENTO DETALHADO ───────────────────────────

    public function getRelatorioCruzamento(?string $periodoInicio = null, ?string $periodoFim = null): array
    {
        $baseQuery = DB::table('vigilia_cruzamentos as vc')
            ->join('atividades_datajuri as ad', 'ad.id', '=', 'vc.atividade_datajuri_id');

        if ($periodoInicio && $periodoFim) {
            $baseQuery->whereBetween('ad.data_hora', [$periodoInicio, $periodoFim]);
        }

        // Stats gerais
        $totalConcluidos = (clone $baseQuery)->where('ad.status', 'Concluído')->count();
        $verificados = (clone $baseQuery)->where('vc.status_cruzamento', 'verificado')->count();
        $suspeitos = (clone $baseQuery)->where('vc.status_cruzamento', 'suspeito')->count();
        $naoAplicavel = (clone $baseQuery)->where('vc.status_cruzamento', 'nao_aplicavel')->where('ad.status', 'Concluído')->count();

        // Confiabilidade por responsável
        $porResponsavel = DB::table('vigilia_cruzamentos as vc')
            ->join('atividades_datajuri as ad', 'ad.id', '=', 'vc.atividade_datajuri_id')
            ->where('ad.status', 'Concluído')
            ->when($periodoInicio && $periodoFim, fn($q) => $q->whereBetween('ad.data_hora', [$periodoInicio, $periodoFim]))
            ->select(
                'ad.responsavel_nome',
                DB::raw('COUNT(*) as total_concluidos'),
                DB::raw("SUM(CASE WHEN vc.status_cruzamento = 'verificado' THEN 1 ELSE 0 END) as verificados"),
                DB::raw("SUM(CASE WHEN vc.status_cruzamento = 'suspeito' THEN 1 ELSE 0 END) as suspeitos"),
                DB::raw("SUM(CASE WHEN vc.status_cruzamento = 'nao_aplicavel' THEN 1 ELSE 0 END) as nao_aplicavel")
            )
            ->groupBy('ad.responsavel_nome')
            ->get()
            ->map(function ($r) {
                $aplicaveis = $r->verificados + $r->suspeitos;
                $r->confiabilidade = $aplicaveis > 0 ? round(($r->verificados / $aplicaveis) * 100, 1) : null;
                return $r;
            })
            ->toArray();

        // Detalhamento suspeitas
        $suspeitas = DB::table('vigilia_cruzamentos as vc')
            ->join('atividades_datajuri as ad', 'ad.id', '=', 'vc.atividade_datajuri_id')
            ->where('vc.status_cruzamento', 'suspeito')
            ->when($periodoInicio && $periodoFim, fn($q) => $q->whereBetween('ad.data_hora', [$periodoInicio, $periodoFim]))
            ->select(
                'ad.responsavel_nome',
                'ad.tipo_atividade',
                'ad.processo_pasta',
                'ad.data_conclusao',
                'vc.data_ultimo_andamento',
                'vc.dias_gap',
                'vc.observacao'
            )
            ->orderByDesc('vc.dias_gap')
            ->get()
            ->toArray();

        return [
            'data_geracao' => now()->format('d/m/Y H:i'),
            'total_concluidos' => $totalConcluidos,
            'verificados' => $verificados,
            'suspeitos' => $suspeitos,
            'nao_aplicavel' => $naoAplicavel,
            'por_responsavel' => $porResponsavel,
            'detalhamento_suspeitas' => $suspeitas,
        ];
    }

    // ─── RESPONSÁVEIS DISTINTOS ──────────────────────────────────────

    public function getResponsaveis(): array
    {
        return DB::table('atividades_datajuri')
            ->select('responsavel_nome')
            ->whereNotNull('responsavel_nome')
            ->where('responsavel_nome', '!=', '')
            ->distinct()
            ->orderBy('responsavel_nome')
            ->pluck('responsavel_nome')
            ->toArray();
    }

    // ─── TIPOS DE ATIVIDADE DISTINTOS ────────────────────────────────

    public function getTiposAtividade(): array
    {
        return DB::table('atividades_datajuri')
            ->select('tipo_atividade', DB::raw('COUNT(*) as qtd'))
            ->whereNotNull('tipo_atividade')
            ->groupBy('tipo_atividade')
            ->orderByDesc('qtd')
            ->pluck('qtd', 'tipo_atividade')
            ->toArray();
    }
}
