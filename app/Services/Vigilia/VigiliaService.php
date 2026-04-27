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

    /**
     * Assuntos de TAREFA que ativam cobrança obrigatória no VIGÍLIA.
     * Quando detectados, o advogado deve preencher parecer no DataJuri.
     * Escalação automática se não resolver no prazo.
     */
    const ASSUNTOS_TRIGGER = [
        'Análise de Decisão',
        'Providências Pós-Audiência',
        'Relatório de Ocorrência',
        'Verificação de Cumprimento',
    ];

    /**
     * Prazo em horas para o advogado resolver a tarefa trigger.
     */
    const PRAZO_TRIGGER_HORAS = 48;

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

    /**
     * Cutoff: VIGÍLIA só exibe itens a partir desta data.
     * Obrigações/cobranças/suspeitas anteriores são legados e ficam de fora
     * da tela Comando.
     */
    const CUTOFF_DATA = '2026-04-01';

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

        // Último cumprimento por advogado (via vigilia_obrigacoes → users.name)
        $ultimos = DB::table('vigilia_obrigacoes as vo')
            ->join('users as u', 'u.id', '=', 'vo.advogado_user_id')
            ->whereNotNull('vo.data_cumprimento')
            ->select('u.name', DB::raw('MAX(vo.data_cumprimento) as ultimo'))
            ->groupBy('u.name')
            ->pluck('ultimo', 'name')
            ->toArray();

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
                'responsavel_nome'   => $row->responsavel_nome,
                'total'              => (int) $row->total,
                'concluidos'         => (int) $row->concluidos,
                'nao_iniciados'      => (int) $row->nao_iniciados,
                'cancelados'         => (int) $row->cancelados,
                'alertas'            => $alertas,
                'taxa'               => $taxa,
                'ultimo_cumprimento' => $ultimos[$row->responsavel_nome] ?? null,
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
                'vc.data_ultimo_andamento',
                'vc.ai_verdict',
                'vc.ai_justificativa'
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
    /**
     * Padroes ampliados com base nos andamentos reais dos tribunais SC.
     * Usa LIKE parcial - basta conter o trecho para reconhecer.
     */
    const ANDAMENTO_ESCRITORIO = [
        'PETI',
        'Peti',
        'peti',
        'Juntada de Peti',
        'Juntada a peti',
        'Juntada - Peti',
        'Tipo Movimento: Juntada - Peti',
        'RECURSO',
        'Recurso',
        'APELA',
        'Apela',
        'AGRAVO',
        'Agravo',
        'EMBARGOS',
        'Embargos',
        'REPLICA',
        'Replica',
        'IMPUGNA',
        'Impugna',
        'CONTESTA',
        'Contesta',
        'MANIFESTA',
        'Manifesta',
        'CONTRARRAZ',
        'Contrarraz',
        'SUBSTABELECIMENTO',
        'Substabelecimento',
        'Confirmada a intima',
        'COM RENUN',
        'Protocolo',
        'PROTOCOLO',
        'Juntado(a)',
        'JUNTADA',
        'CUMPRIMENTO',
        'Cumprimento',
        'Requerimento',
        'REQUERIMENTO',
        'Dilig',
        'DILIG',
    ];
    const ANDAMENTO_AUDIENCIA = [
        'Audiencia',
        'AUDIENCIA',
        'Realizada audiencia',
        'Ata de Audiencia',
        'Ata audiencia',
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

        // 1. Tipo nao-juridico -> N/A
        // Logica: bloqueia apenas o que esta explicitamente listado como nao-aplicavel.
        // Qualquer tipo nao mapeado eh auditado (evita falsos N/A por lista incompleta).
        if (in_array($ativ->tipo_atividade, self::TIPOS_NAO_APLICAVEIS)) {
            $default['status_cruzamento'] = 'nao_aplicavel';
            $default['observacao'] = 'Tipo n\xc3\xa3o-jur\xc3\xaddico: ' . ($ativ->tipo_atividade ?? 'desconhecido');
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

        // 5. Buscar andamentos COMPATIVEIS na janela de tempo
        //    Janela varia por tipo: analises e recursos precisam de mais tempo apos a atividade
        $dataRef = $dataAtiv ?? Carbon::today();
        $tipoLower = strtolower($ativ->tipo_atividade ?? '');
        if (str_contains($tipoLower, 'an\xc3\xa1lise') || str_contains($tipoLower, 'analise') || str_contains($tipoLower, 'dilig\xc3\xaancia')) {
            $janelaApos = 25; // analises podem resultar em peticao dias depois
        } elseif (str_contains($tipoLower, 'recurso') || str_contains($tipoLower, 'interposi\xc3\xa7\xc3\xa3o')) {
            $janelaApos = 20;
        } else {
            $janelaApos = self::JANELA_DIAS_APOS; // padrao 15
        }
        $inicio = $dataRef->copy()->subDays(self::JANELA_DIAS_ANTES);
        $fim = $dataRef->copy()->addDays($janelaApos);
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

        // 6. dias_gap: distancia entre atividade e andamento encontrado na janela
        //    Se nao achou andamento: dias desde a atividade ate hoje (indica urgencia)
        $dataUltimoAnd = $andamentoQuery ? $andamentoQuery->data_andamento : null;
        $diasGap = null;
        if ($andamentoQuery && $dataAtiv) {
            $diasGap = abs(Carbon::parse($andamentoQuery->data_andamento)->diffInDays($dataAtiv));
        } elseif ($dataAtiv && $dataAtiv->isPast()) {
            $diasGap = $dataAtiv->diffInDays(Carbon::today());
        }
        // Fallback: buscar ultimo andamento do escritorio apenas para preencher data_ultimo_andamento
        if (!$dataUltimoAnd) {
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


    // ─── TAREFAS TRIGGER (COBRANÇA OBRIGATÓRIA) ─────────────────────

    /**
     * Retorna tarefas com assuntos trigger que requerem ação.
     * Filtra: tipo_atividade=Tarefa, assunto in ASSUNTOS_TRIGGER, 2026+
     */
    public function getTarefasTrigger(?string $responsavel = null): array
    {
        $query = DB::table('atividades_datajuri')
            ->where('tipo_atividade', 'Tarefa')
            ->where(function($q) { $q->whereIn('assunto', self::ASSUNTOS_TRIGGER)->orWhereIn('assunto_original', self::ASSUNTOS_TRIGGER); })
            ->where('data_hora', '>=', self::CUTOFF_DATA)
            ->orderByDesc('data_hora');

        if ($responsavel) {
            $query->where('responsavel_nome', $responsavel);
        }

        return $query->get()->map(function ($row) {
            $horasDesdeCreacao = null;
            if ($row->created_at) {
                $horasDesdeCreacao = Carbon::parse($row->created_at)->diffInHours(Carbon::now());
            }

            $vencido = $horasDesdeCreacao !== null && $horasDesdeCreacao > self::PRAZO_TRIGGER_HORAS;
            $status_trigger = 'pendente';

            if ($row->status === 'Concluído') {
                $status_trigger = 'concluido';
            } elseif ($row->status === 'Cancelado') {
                $status_trigger = 'cancelado';
            } elseif ($vencido) {
                $status_trigger = 'vencido';
            }

            // Verificar se tem parecer no andamento do processo
            $temParecer = false;
            if ($row->processo_pasta) {
                $faseId = DB::table('fases_processo')
                    ->where('processo_pasta', $row->processo_pasta)
                    ->value('datajuri_id');

                if ($faseId) {
                    $temParecer = DB::table('andamentos_fase')
                        ->where('fase_processo_id_datajuri', $faseId)
                        ->where(function ($q) {
                            $q->where('parecer', '!=', '')
                              ->whereNotNull('parecer')
                              ->where('parecer', '!=', 'Não');
                        })
                        ->where('data_andamento', '>=', Carbon::parse($row->data_hora)->subDays(5)->toDateString())
                        ->exists();
                }
            }

            return [
                'id' => $row->id,
                'datajuri_id' => $row->datajuri_id,
                'assunto' => $row->assunto,
                'tipo_atividade' => $row->tipo_atividade,
                'status' => $row->status,
                'status_trigger' => $status_trigger,
                'responsavel' => $row->responsavel_nome,
                'processo_pasta' => $row->processo_pasta,
                'data_hora' => $row->data_hora,
                'data_conclusao' => $row->data_conclusao,
                'horas_desde_criacao' => $horasDesdeCreacao,
                'prazo_horas' => self::PRAZO_TRIGGER_HORAS,
                'vencido' => $vencido,
                'tem_parecer' => $temParecer,
                'created_at' => $row->created_at,
            ];
        })->toArray();
    }

    /**
     * Retorna resumo das tarefas trigger para o dashboard.
     */
    public function getResumoTriggers(): array
    {
        $triggers = $this->getTarefasTrigger();
        $pendentes = array_filter($triggers, fn($t) => $t['status_trigger'] === 'pendente');
        $vencidos = array_filter($triggers, fn($t) => $t['status_trigger'] === 'vencido');
        $concluidos = array_filter($triggers, fn($t) => $t['status_trigger'] === 'concluido');

        return [
            'total' => count($triggers),
            'pendentes' => count($pendentes),
            'vencidos' => count($vencidos),
            'concluidos' => count($concluidos),
            'detalhes_pendentes' => array_values($pendentes),
            'detalhes_vencidos' => array_values($vencidos),
        ];
    }

    // ─── OBRIGAÇÕES (Machine C) ──────────────────────────────────────

    public function getObrigacoes(array $filtros = []): array
    {
        $query = DB::table('vigilia_obrigacoes as vo')
            ->leftJoin('users as u', 'u.id', '=', 'vo.advogado_user_id')
            ->select(
                'vo.id',
                'vo.processo_pasta',
                'vo.tipo_evento',
                'vo.descricao_evento',
                'vo.data_evento',
                'vo.status',
                'vo.data_limite',
                'vo.data_cumprimento',
                'vo.parecer',
                'vo.created_at',
                'u.name as advogado_nome',
            )
            ->orderBy('vo.status')
            ->orderBy('vo.data_limite');

        if (!empty($filtros['status'])) {
            $query->where('vo.status', $filtros['status']);
        }

        if (!empty($filtros['tipo_evento'])) {
            $query->where('vo.tipo_evento', $filtros['tipo_evento']);
        }

        if (!empty($filtros['advogado'])) {
            $query->where('vo.advogado_user_id', $filtros['advogado']);
        }

        if (!empty($filtros['processo'])) {
            $query->where('vo.processo_pasta', $filtros['processo']);
        }

        $perPage = (int) ($filtros['per_page'] ?? 30);
        $page    = (int) ($filtros['page'] ?? 1);
        $total   = (clone $query)->count();

        $data = $query->offset(($page - 1) * $perPage)->limit($perPage)->get()->map(function ($row) {
            $vencida = $row->data_limite && now()->gt($row->data_limite) && $row->status === 'pendente';
            return [
                'id'              => $row->id,
                'processo_pasta'  => $row->processo_pasta,
                'tipo_evento'     => $row->tipo_evento,
                'descricao_evento'=> mb_substr($row->descricao_evento, 0, 150),
                'data_evento'     => $row->data_evento,
                'status'          => $row->status,
                'vencida'         => $vencida,
                'data_limite'     => $row->data_limite,
                'data_cumprimento'=> $row->data_cumprimento,
                'parecer'         => $row->parecer,
                'advogado_nome'   => $row->advogado_nome ?? 'Não atribuído',
                'created_at'      => $row->created_at,
            ];
        });

        return [
            'data'         => $data->values()->toArray(),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Cumpre uma obrigação e cascateia para "irmãs" do mesmo processo+data_evento.
     *
     * Retorna ['ok' => bool, 'cascata' => int] — cascata é o nº de obrigações irmãs
     * também marcadas como cumpridas (não conta a alvo).
     *
     * Por que cascata: o DataJuri frequentemente registra múltiplos andamentos pra
     * "mesma" decisão lógica (ex.: "Remetido ao DJE" + "Proferidas Outras Decisões").
     * O gerador de obrigações cria 1 linha por andamento, então o usuário vê N
     * pendências pra resolver com o mesmo parecer. Ao cumprir uma, propagamos para
     * as outras pendentes do mesmo processo+dia.
     */
    public function cumpriObrigacao(int $id, string $parecer): array
    {
        $alvo = DB::table('vigilia_obrigacoes')->where('id', $id)->first();
        if (!$alvo) return ['ok' => false, 'cascata' => 0];

        $parecerTrunc = mb_substr($parecer, 0, 1000);
        $now = now();

        $okAlvo = (bool) DB::table('vigilia_obrigacoes')->where('id', $id)->update([
            'status'           => 'cumprida',
            'data_cumprimento' => $now,
            'parecer'          => $parecerTrunc,
            'updated_at'       => $now,
        ]);

        $parecerCascata = '[CASCATA · cumprida via #' . $id . '] ' . $parecerTrunc;
        $cascata = DB::table('vigilia_obrigacoes')
            ->where('id', '!=', $id)
            ->where('processo_pasta', $alvo->processo_pasta)
            ->where('data_evento', $alvo->data_evento)
            ->where('status', 'pendente')
            ->update([
                'status'           => 'cumprida',
                'data_cumprimento' => $now,
                'parecer'          => mb_substr($parecerCascata, 0, 1000),
                'updated_at'       => $now,
            ]);

        return ['ok' => $okAlvo, 'cascata' => (int) $cascata];
    }

    public function getContadorObrigacoesPendentes(): int
    {
        return DB::table('vigilia_obrigacoes')
            ->where('status', 'pendente')
            ->count();
    }

    // ─── INBOX UNIFICADO (editorial v2) ────────────────────────────

    /**
     * Une obrigações, cobranças (triggers) e suspeitas num feed priorizado.
     * Filter: tudo | vencidas | cobrancas | obrigacoes | suspeitas | cumpridas
     */
    public function getInbox(string $filter = 'tudo', int $limit = 40): array
    {
        $items = [];
        $now = Carbon::now();

        // ─── Obrigações (72h parecer) ───
        if (in_array($filter, ['tudo', 'vencidas', 'obrigacoes', 'cumpridas'], true)) {
            $obrQ = DB::table('vigilia_obrigacoes as vo')
                ->leftJoin('users as u', 'u.id', '=', 'vo.advogado_user_id')
                ->leftJoin('processos as p', 'p.pasta', '=', 'vo.processo_pasta')
                ->where('vo.data_evento', '>=', self::CUTOFF_DATA)
                ->select(
                    'vo.id', 'vo.processo_pasta', 'vo.tipo_evento',
                    'vo.descricao_evento', 'vo.status', 'vo.data_limite',
                    'vo.data_cumprimento', 'vo.created_at',
                    'u.name as advogado_nome',
                    'p.cliente_nome'
                );

            if ($filter === 'cumpridas') {
                $obrQ->where('vo.status', 'cumprida')
                    ->whereDate('vo.data_cumprimento', $now->toDateString());
            } elseif ($filter === 'vencidas') {
                $obrQ->where('vo.status', 'pendente')
                    ->where('vo.data_limite', '<', $now);
            } else {
                $obrQ->where('vo.status', 'pendente');
            }

            foreach ($obrQ->orderBy('vo.data_limite')->limit($limit)->get() as $r) {
                $limite = $r->data_limite ? Carbon::parse($r->data_limite) : null;
                $vencida = $limite && $now->gt($limite) && $r->status === 'pendente';
                $diasAtraso = $vencida ? (int) $limite->diffInDays($now) : null;
                $horasAteLimite = $limite && !$vencida ? (int) $now->diffInHours($limite) : null;

                if ($r->status === 'cumprida') {
                    $sev = 'ok';
                    $status_label = 'Cumprida';
                } elseif ($vencida) {
                    $sev = 'critical';
                    $status_label = $diasAtraso > 0 ? "Vencida {$diasAtraso}d" : 'Vencida hoje';
                } elseif ($horasAteLimite !== null && $horasAteLimite <= 24) {
                    $sev = 'medium';
                    $status_label = "Vence {$horasAteLimite}h";
                } else {
                    $sev = 'low';
                    $status_label = 'Pendente';
                }

                $items[] = [
                    'id'            => 'obr:' . $r->id,
                    'obrigacao_id'  => $r->id,
                    'source'        => 'obrigacao',
                    'severity'      => $sev,
                    'status_label'  => $status_label,
                    'time_label'    => $limite ? 'limite ' . $limite->format('d/m · H\\h') : '—',
                    'sort_key'      => $limite ? $limite->timestamp : PHP_INT_MAX,
                    'processo'      => $r->processo_pasta,
                    'tribunal'      => $this->tribunalFromPasta($r->processo_pasta),
                    'event_text'    => mb_substr((string) $r->descricao_evento, 0, 180),
                    'advogado'      => $r->advogado_nome ?? 'Não atribuído',
                    'cliente'       => $r->cliente_nome,
                    'area'          => $this->areaFromPasta($r->processo_pasta),
                    'actions'       => $r->status === 'cumprida' ? [] : [
                        ['kind' => 'parecer',     'label' => 'Registrar parecer'],
                        ['kind' => 'justificar',  'label' => 'Justificar'],
                    ],
                ];
            }
        }

        // ─── Cobranças (Triggers 48h+) ───
        if (in_array($filter, ['tudo', 'cobrancas', 'vencidas'], true)) {
            $triggers = $this->getTarefasTrigger();
            foreach ($triggers as $t) {
                $isVencido = $t['vencido'] === true && $t['status_trigger'] === 'vencido';
                $isPendente = $t['status_trigger'] === 'pendente' || $isVencido;
                if (!$isPendente) continue;
                if ($filter === 'vencidas' && !$isVencido) continue;

                $cliente = DB::table('processos')->where('pasta', $t['processo_pasta'])->value('cliente_nome');
                $horas = (int) ($t['horas_desde_criacao'] ?? 0);
                $prazo = (int) ($t['prazo_horas'] ?? 48);

                $items[] = [
                    'id'           => 'cob:' . $t['id'],
                    'source'       => 'cobranca',
                    'severity'     => $isVencido ? 'high' : 'medium',
                    'status_label' => $isVencido ? 'Sem providência' : "Em curso {$horas}h",
                    'time_label'   => "notificado {$horas}h atrás · prazo {$prazo}h",
                    'sort_key'     => -$horas,
                    'processo'     => $t['processo_pasta'],
                    'tribunal'     => $this->tribunalFromPasta($t['processo_pasta']),
                    'event_text'   => $t['assunto'] . ' — ' . ($t['tem_parecer'] ? 'parecer presente mas sem confirmação' : 'sem parecer registrado no DataJuri'),
                    'advogado'     => $t['responsavel'],
                    'cliente'      => $cliente,
                    'area'         => $this->areaFromPasta($t['processo_pasta']),
                    'actions'      => [
                        ['kind' => 'providencia', 'label' => 'Marcar providência'],
                        ['kind' => 'conversa',    'label' => 'Abrir NEXO'],
                    ],
                ];
            }
        }

        // ─── Suspeitas (cruzamento) ───
        if (in_array($filter, ['tudo', 'suspeitas'], true)) {
            $susp = DB::table('vigilia_cruzamentos as vc')
                ->join('atividades_datajuri as ad', 'ad.id', '=', 'vc.atividade_datajuri_id')
                ->leftJoin('processos as p', 'p.pasta', '=', 'ad.processo_pasta')
                ->where('vc.status_cruzamento', 'suspeito')
                ->where('ad.data_hora', '>=', self::CUTOFF_DATA)
                ->select(
                    'vc.id', 'vc.dias_gap', 'vc.observacao', 'vc.ai_verdict',
                    'ad.responsavel_nome', 'ad.processo_pasta', 'ad.tipo_atividade',
                    'ad.data_hora', 'ad.data_conclusao',
                    'p.cliente_nome'
                )
                ->orderByDesc('vc.dias_gap')
                ->limit($limit)
                ->get();

            foreach ($susp as $r) {
                $items[] = [
                    'id'           => 'sus:' . $r->id,
                    'source'       => 'suspeita',
                    'severity'     => 'suspect',
                    'status_label' => 'Suspeita',
                    'time_label'   => $r->data_conclusao ? 'concluída ' . Carbon::parse($r->data_conclusao)->format('d/m') : 'conclusão sem data',
                    'sort_key'     => (int) $r->dias_gap * -1,
                    'processo'     => $r->processo_pasta,
                    'tribunal'     => $this->tribunalFromPasta($r->processo_pasta),
                    'event_text'   => ($r->tipo_atividade ?? 'Atividade') . ' marcada concluída sem andamento posterior no DataJuri' . ($r->ai_verdict ? " (AI: {$r->ai_verdict})" : '') . '.',
                    'advogado'     => $r->responsavel_nome,
                    'cliente'      => $r->cliente_nome,
                    'area'         => $this->areaFromPasta($r->processo_pasta),
                    'actions'      => [
                        ['kind' => 'auditar', 'label' => 'Auditar'],
                        ['kind' => 'aceitar', 'label' => 'Aceitar'],
                    ],
                ];
            }
        }

        // Ordenação: crítico > alto > suspeita > médio > baixo > ok
        $sevOrder = ['critical' => 0, 'high' => 1, 'suspect' => 2, 'medium' => 3, 'low' => 4, 'ok' => 5];
        usort($items, function ($a, $b) use ($sevOrder) {
            $sa = $sevOrder[$a['severity']] ?? 9;
            $sb = $sevOrder[$b['severity']] ?? 9;
            if ($sa !== $sb) return $sa <=> $sb;
            return $a['sort_key'] <=> $b['sort_key'];
        });

        return array_slice($items, 0, $limit);
    }

    /**
     * Contadores por categoria (pra badges dos chips).
     */
    public function getInboxCounters(): array
    {
        $now = Carbon::now();
        $obrPendentes = DB::table('vigilia_obrigacoes')
            ->where('status', 'pendente')
            ->where('data_evento', '>=', self::CUTOFF_DATA)
            ->count();
        $obrVencidas  = DB::table('vigilia_obrigacoes')
            ->where('status', 'pendente')
            ->where('data_evento', '>=', self::CUTOFF_DATA)
            ->where('data_limite', '<', $now)
            ->count();
        $triggers     = $this->getResumoTriggers();
        $suspeitas    = DB::table('vigilia_cruzamentos as vc')
            ->join('atividades_datajuri as ad', 'ad.id', '=', 'vc.atividade_datajuri_id')
            ->where('vc.status_cruzamento', 'suspeito')
            ->where('ad.data_hora', '>=', self::CUTOFF_DATA)
            ->count();
        $cumpridasHoje = DB::table('vigilia_obrigacoes')
            ->where('status', 'cumprida')
            ->where('data_evento', '>=', self::CUTOFF_DATA)
            ->whereDate('data_cumprimento', $now->toDateString())
            ->count();

        $vencidasTotal = $obrVencidas + (int) ($triggers['vencidos'] ?? 0);

        return [
            'tudo'          => $obrPendentes + (int) ($triggers['pendentes'] ?? 0) + (int) ($triggers['vencidos'] ?? 0) + $suspeitas,
            'vencidas'      => $vencidasTotal,
            'cobrancas'     => (int) ($triggers['pendentes'] ?? 0) + (int) ($triggers['vencidos'] ?? 0),
            'obrigacoes'    => $obrPendentes,
            'suspeitas'     => $suspeitas,
            'cumpridas'     => $cumpridasHoje,
            'sem_providencia_48h' => (int) ($triggers['vencidos'] ?? 0),
        ];
    }

    // ─── CONFIABILIDADE ───────────────────────────────────────────

    public function getConfiabilidade(?int $dias = 30): array
    {
        // created_at pode estar NULL (rows legados) — usa data da atividade + cutoff VIGÍLIA
        $desde = Carbon::now()->subDays($dias);
        $base = DB::table('vigilia_cruzamentos as vc')
            ->join('atividades_datajuri as ad', 'ad.id', '=', 'vc.atividade_datajuri_id')
            ->whereIn('vc.status_cruzamento', ['verificado', 'suspeito', 'sem_acao'])
            ->where('ad.data_hora', '>=', self::CUTOFF_DATA);

        $verificadas = (clone $base)->where('vc.status_cruzamento', 'verificado')->count();
        $suspeitas   = (clone $base)->where('vc.status_cruzamento', 'suspeito')->count();
        $semAcao     = (clone $base)->where('vc.status_cruzamento', 'sem_acao')->count();
        $total       = $verificadas + $suspeitas + $semAcao;
        $pct         = $total > 0 ? round(($verificadas / $total) * 100) : 0;

        $ultimaExec = DB::table('vigilia_cruzamentos')->max('updated_at');

        return [
            'total'        => $total,
            'verificadas'  => $verificadas,
            'suspeitas'    => $suspeitas,
            'sem_acao'     => $semAcao,
            'pct'          => $pct,
            'dias'         => $dias,
            'ultima_exec'  => $ultimaExec,
        ];
    }

    // ─── DETALHE DO ITEM (modal "ver mais") ───────────────────────

    /**
     * Retorna detalhe completo de um item do inbox (obr:N | cob:N | sus:N).
     * Inclui: andamento integral, parecer, observação, metadata, links.
     */
    public function getInboxItemDetail(string $compositeId): ?array
    {
        if (!preg_match('/^(obr|cob|sus):(\d+)$/', $compositeId, $m)) return null;
        [$_, $kind, $id] = $m;
        $id = (int) $id;

        return match ($kind) {
            'obr' => $this->detailObrigacao($id),
            'cob' => $this->detailCobranca($id),
            'sus' => $this->detailSuspeita($id),
            default => null,
        };
    }

    private function detailObrigacao(int $id): ?array
    {
        $obr = DB::table('vigilia_obrigacoes as vo')
            ->leftJoin('users as u', 'u.id', '=', 'vo.advogado_user_id')
            ->where('vo.id', $id)
            ->select('vo.*', 'u.name as advogado_nome')
            ->first();
        if (!$obr) return null;

        $and = $obr->andamento_fase_id
            ? DB::table('andamentos_fase')->where('id', $obr->andamento_fase_id)->first()
            : null;
        $proc = DB::table('processos')->where('pasta', $obr->processo_pasta)->first();

        return [
            'kind'        => 'obrigacao',
            'title'       => 'Obrigação · ' . str_replace('_', ' ', mb_strtolower($obr->tipo_evento ?? '')),
            'processo'    => $obr->processo_pasta,
            'tribunal'    => $this->tribunalFromPasta($obr->processo_pasta),
            'cliente'     => $proc->cliente_nome ?? null,
            'advogado'    => $obr->advogado_nome ?? 'Não atribuído',
            'data_evento' => $obr->data_evento,
            'data_limite' => $obr->data_limite,
            'status'      => $obr->status,
            'parecer'     => $obr->parecer ?: null,
            'andamento'   => $and ? [
                'tipo'       => $and->tipo,
                'data'       => $and->data_andamento,
                'descricao'  => $and->descricao,
                'observacao' => $this->extractFromPayload($and->payload_raw, 'observacao'),
                'parecer'    => $and->parecer ?: $this->extractFromPayload($and->payload_raw, 'parecer'),
            ] : null,
            'links'       => $this->buildLinks($proc, $obr->processo_pasta),
            'note'        => 'O DataJuri não armazena o inteiro teor da decisão — apenas um título do andamento. Para ler o documento completo, abra no DataJuri ou consulte o processo no tribunal.',
        ];
    }

    private function detailCobranca(int $id): ?array
    {
        $ad = DB::table('atividades_datajuri')->where('id', $id)->first();
        if (!$ad) return null;
        $proc = DB::table('processos')->where('pasta', $ad->processo_pasta)->first();

        $ultimoAnd = DB::table('andamentos_fase')
            ->where('processo_pasta', $ad->processo_pasta)
            ->orderByDesc('data_andamento')
            ->first();

        $temParecer = $ultimoAnd && trim((string) ($ultimoAnd->parecer ?? '')) !== '';

        return [
            'kind'        => 'cobranca',
            'title'       => 'Cobrança · ' . ($ad->assunto ?: $ad->tipo_atividade ?: 'Trigger'),
            'processo'    => $ad->processo_pasta,
            'tribunal'    => $this->tribunalFromPasta($ad->processo_pasta),
            'cliente'     => $proc->cliente_nome ?? null,
            'advogado'    => $ad->responsavel_nome,
            'data_evento' => $ad->data_hora,
            'data_limite' => null,
            'status'      => $ad->status,
            'parecer'     => $temParecer ? $ultimoAnd->parecer : null,
            'andamento'   => $ultimoAnd ? [
                'tipo'       => $ultimoAnd->tipo,
                'data'       => $ultimoAnd->data_andamento,
                'descricao'  => $ultimoAnd->descricao,
                'observacao' => $this->extractFromPayload($ultimoAnd->payload_raw, 'observacao'),
                'parecer'    => $ultimoAnd->parecer ?: $this->extractFromPayload($ultimoAnd->payload_raw, 'parecer'),
            ] : null,
            'links'       => $this->buildLinks($proc, $ad->processo_pasta),
            'note'        => $temParecer
                ? 'Parecer já registrado. Falta confirmação no fluxo VIGÍLIA — registre providência no DataJuri ou marque manualmente.'
                : 'Sem parecer registrado no DataJuri. O advogado precisa preencher o parecer da atividade.',
        ];
    }

    private function detailSuspeita(int $id): ?array
    {
        $row = DB::table('vigilia_cruzamentos as vc')
            ->join('atividades_datajuri as ad', 'ad.id', '=', 'vc.atividade_datajuri_id')
            ->where('vc.id', $id)
            ->select(
                'vc.id', 'vc.dias_gap', 'vc.observacao as vc_obs', 'vc.ai_verdict', 'vc.status_cruzamento',
                'ad.id as atividade_id', 'ad.assunto', 'ad.tipo_atividade', 'ad.processo_pasta',
                'ad.data_hora', 'ad.data_conclusao', 'ad.responsavel_nome'
            )
            ->first();
        if (!$row) return null;

        $proc = DB::table('processos')->where('pasta', $row->processo_pasta)->first();
        $ultimoAnd = DB::table('andamentos_fase')
            ->where('processo_pasta', $row->processo_pasta)
            ->orderByDesc('data_andamento')
            ->first();

        return [
            'kind'        => 'suspeita',
            'title'       => 'Suspeita · ' . ($row->tipo_atividade ?: 'Atividade'),
            'processo'    => $row->processo_pasta,
            'tribunal'    => $this->tribunalFromPasta($row->processo_pasta),
            'cliente'     => $proc->cliente_nome ?? null,
            'advogado'    => $row->responsavel_nome,
            'data_evento' => $row->data_conclusao ?: $row->data_hora,
            'data_limite' => null,
            'status'      => $row->status_cruzamento,
            'parecer'     => null,
            'andamento'   => $ultimoAnd ? [
                'tipo'       => $ultimoAnd->tipo,
                'data'       => $ultimoAnd->data_andamento,
                'descricao'  => $ultimoAnd->descricao,
                'observacao' => $this->extractFromPayload($ultimoAnd->payload_raw, 'observacao'),
                'parecer'    => $ultimoAnd->parecer ?: $this->extractFromPayload($ultimoAnd->payload_raw, 'parecer'),
            ] : null,
            'suspeita_meta' => [
                'dias_gap'   => (int) $row->dias_gap,
                'observacao' => $row->vc_obs,
                'ai_verdict' => $row->ai_verdict,
            ],
            'links'       => $this->buildLinks($proc, $row->processo_pasta),
            'note'        => 'Atividade marcada como concluída no DataJuri sem andamento posterior compatível. Audite ou aceite a conclusão.',
        ];
    }

    private function extractFromPayload(?string $rawJson, string $key): ?string
    {
        if (!$rawJson) return null;
        $p = json_decode($rawJson, true);
        if (!is_array($p)) return null;
        $v = $p[$key] ?? null;
        return is_string($v) && trim($v) !== '' ? $v : null;
    }

    private function buildLinks($processo, ?string $pasta): array
    {
        $djUrl = ($processo && !empty($processo->datajuri_id))
            ? "https://dj21.datajuri.com.br/app/#/lista/Processo/{$processo->datajuri_id}"
            : null;

        $tribunalUrl = null;
        $tribunalLabel = null;
        if ($pasta) {
            if (str_contains($pasta, '.8.24.')) {
                $tribunalUrl = 'https://eproc1g.tjsc.jus.br/eproc/externo_controlador.php?acao=processo_consulta_publica';
                $tribunalLabel = 'eproc TJSC';
            } elseif (str_contains($pasta, '.4.04.')) {
                $tribunalUrl = 'https://eproc.trf4.jus.br/eproc2trf4/externo_controlador.php?acao=processo_consulta_publica';
                $tribunalLabel = 'eproc TRF4';
            } elseif (str_contains($pasta, '.5.12.')) {
                $tribunalUrl = 'https://pje.trt12.jus.br/consultaprocessual';
                $tribunalLabel = 'PJe TRT12';
            }
        }

        return [
            'datajuri'        => $djUrl,
            'tribunal_url'    => $tribunalUrl,
            'tribunal_label'  => $tribunalLabel,
            'pasta'           => $pasta,
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────

    private function tribunalFromPasta(?string $pasta): string
    {
        if (!$pasta) return '—';
        if (str_contains($pasta, '.8.24.')) return 'TJSC eproc';
        if (str_contains($pasta, '.5.12.')) return 'TRT12';
        if (str_contains($pasta, '.4.04.')) return 'TRF4';
        if (str_starts_with($pasta, 'STJ')) return 'STJ';
        return 'DataJuri';
    }

    private function areaFromPasta(?string $pasta): ?string
    {
        if (!$pasta) return null;
        if (str_contains($pasta, '.5.12.')) return 'Trabalhista';
        if (str_contains($pasta, '.8.24.')) return 'Cível';
        if (str_contains($pasta, '.4.04.')) return 'Federal';
        return null;
    }

}
