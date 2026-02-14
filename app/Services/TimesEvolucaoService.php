<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * TimesEvolucaoService
 *
 * Calcula os 8 KPIs de maturidade organizacional (agregados, nunca individuais).
 * Dados exclusivamente de tabelas já existentes.
 */
class TimesEvolucaoService
{
    /**
     * Retorna todos os KPIs para um mês/ano, com cache mensal.
     */
    public function getKpis(int $year, int $month): array
    {
        $cacheKey = "times_evolucao_kpis_{$year}_{$month}";

        // Cache de 1h para mês atual, 24h para meses anteriores
        $now = Carbon::now('America/Sao_Paulo');
        $isCurrentMonth = ($year === (int) $now->format('Y') && $month === (int) $now->format('m'));
        $ttl = $isCurrentMonth ? 3600 : 86400;

        return Cache::remember($cacheKey, $ttl, function () use ($year, $month) {
            $startDate = Carbon::create($year, $month, 1)->startOfDay();
            $endDate   = $startDate->copy()->endOfMonth()->endOfDay();

            return [
                'aderencia_registro'    => $this->calcAderenciaRegistro($startDate, $endDate),
                'pontualidade'          => $this->calcPontualidade($startDate, $endDate),
                'backlog_operacional'   => $this->calcBacklogOperacional($endDate),
                'sla_whatsapp'          => $this->calcSlaWhatsapp($startDate, $endDate),
                'conversas_sem_resposta'=> $this->calcConversasSemResposta($startDate, $endDate),
                'alcance_avisos'        => $this->calcAlcanceAvisos($startDate, $endDate),
                'saude_sincronizacao'   => $this->calcSaudeSincronizacao($startDate, $endDate),
                'adocao_crm'            => $this->calcAdocaoCrm($startDate, $endDate),
            ];
        });
    }

    /**
     * Retorna tendência de 6 meses para o gráfico de linha.
     */
    public function getTrend(int $year, int $month): array
    {
        $cacheKey = "times_evolucao_trend_{$year}_{$month}";

        return Cache::remember($cacheKey, 3600, function () use ($year, $month) {
            $trend = [];
            $current = Carbon::create($year, $month, 1);

            for ($i = 5; $i >= 0; $i--) {
                $ref = $current->copy()->subMonths($i);
                $kpis = $this->getKpis((int) $ref->format('Y'), (int) $ref->format('m'));

                $trend[] = [
                    'label' => $ref->translatedFormat('M/y'),
                    'month' => (int) $ref->format('m'),
                    'year'  => (int) $ref->format('Y'),
                    'aderencia_registro'     => $kpis['aderencia_registro']['valor'],
                    'pontualidade'           => $kpis['pontualidade']['valor'],
                    'sla_whatsapp'           => $kpis['sla_whatsapp']['valor'],
                    'conversas_sem_resposta' => $kpis['conversas_sem_resposta']['valor'],
                    'alcance_avisos'         => $kpis['alcance_avisos']['valor'],
                    'saude_sincronizacao'    => $kpis['saude_sincronizacao']['valor'],
                    'adocao_crm'             => $kpis['adocao_crm']['valor'],
                ];
            }

            return $trend;
        });
    }

    /**
     * Retorna metas do kpi_monthly_targets para o módulo.
     */
    public function getMetas(int $year, int $month): array
    {
        $rows = DB::table('kpi_monthly_targets')
            ->where('modulo', 'times_evolucao')
            ->where('year', $year)
            ->where('month', $month)
            ->get();

        $metas = [];
        foreach ($rows as $row) {
            $metas[$row->kpi_key] = [
                'meta_valor'   => (float) $row->meta_valor,
                'unidade'      => $row->unidade,
                'tipo_meta'    => $row->tipo_meta, // min, max
                'target_value' => $row->target_value,
            ];
        }

        return $metas;
    }

    // ==========================================
    // KPI 1: ADERÊNCIA AO REGISTRO (%)
    // ==========================================
    private function calcAderenciaRegistro(Carbon $start, Carbon $end): array
    {
        // Dias úteis no mês (seg-sex)
        $businessDays = $this->countBusinessDays($start, $end);

        // Dias úteis com pelo menos 1 lançamento em horas_trabalhadas_datajuri
        $daysWithRecord = DB::table('horas_trabalhadas_datajuri')
            ->whereBetween('data', [$start->toDateString(), $end->toDateString()])
            ->select(DB::raw('COUNT(DISTINCT data) as total'))
            ->value('total');

        $valor = $businessDays > 0
            ? round(($daysWithRecord / $businessDays) * 100, 1)
            : 0;

        return [
            'valor'           => $valor,
            'dias_com_lancto' => (int) $daysWithRecord,
            'dias_uteis'      => $businessDays,
            'unidade'         => '%',
        ];
    }

    // ==========================================
    // KPI 2: PONTUALIDADE (%)
    // ==========================================
    private function calcPontualidade(Carbon $start, Carbon $end): array
    {
        // Atividades concluídas no mês
        $concluidas = DB::table('atividades_datajuri')
            ->whereNotNull('data_conclusao')
            ->whereBetween('data_conclusao', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->select(DB::raw('
                COUNT(*) as total,
                SUM(CASE WHEN data_vencimento IS NOT NULL AND data_conclusao <= data_vencimento THEN 1 ELSE 0 END) as no_prazo
            '))
            ->first();

        $total = (int) ($concluidas->total ?? 0);
        $noPrazo = (int) ($concluidas->no_prazo ?? 0);
        $valor = $total > 0 ? round(($noPrazo / $total) * 100, 1) : 0;

        return [
            'valor'    => $valor,
            'no_prazo' => $noPrazo,
            'total'    => $total,
            'unidade'  => '%',
        ];
    }

    // ==========================================
    // KPI 3: BACKLOG OPERACIONAL
    // ==========================================
    private function calcBacklogOperacional(Carbon $end): array
    {
        // Atividades sem conclusão (abertas)
        $backlog = DB::table('atividades_datajuri')
            ->whereNull('data_conclusao')
            ->where('data_hora', '<=', $end->toDateTimeString())
            ->select(DB::raw('
                COUNT(*) as total,
                SUM(CASE WHEN data_vencimento IS NOT NULL AND data_vencimento < NOW() THEN 1 ELSE 0 END) as em_atraso,
                SUM(CASE WHEN data_vencimento IS NULL OR data_vencimento >= NOW() THEN 1 ELSE 0 END) as no_prazo
            '))
            ->first();

        return [
            'valor'     => (int) ($backlog->total ?? 0),
            'em_atraso' => (int) ($backlog->em_atraso ?? 0),
            'no_prazo'  => (int) ($backlog->no_prazo ?? 0),
            'unidade'   => 'un',
        ];
    }

    // ==========================================
    // KPI 4: SLA WHATSAPP (minutos)
    // ==========================================
    private function calcSlaWhatsapp(Carbon $start, Carbon $end): array
    {
        // Tempo médio entre primeiro inbound e primeiro outbound por conversa
        // direction: 1 = inbound, 2 = outbound
        $result = DB::select("
            SELECT AVG(diff_minutes) as media, COUNT(*) as total_conversas
            FROM (
                SELECT
                    c.id,
                    TIMESTAMPDIFF(MINUTE,
                        MIN(CASE WHEN m.direction = 1 THEN m.sent_at END),
                        MIN(CASE WHEN m.direction = 2 THEN m.sent_at END)
                    ) as diff_minutes
                FROM wa_conversations c
                INNER JOIN wa_messages m ON m.conversation_id = c.id
                WHERE m.sent_at BETWEEN ? AND ?
                GROUP BY c.id
                HAVING diff_minutes IS NOT NULL AND diff_minutes >= 0
            ) sub
        ", [$start->toDateTimeString(), $end->toDateTimeString()]);

        $media = round((float) ($result[0]->media ?? 0), 1);
        $totalConversas = (int) ($result[0]->total_conversas ?? 0);

        return [
            'valor'           => $media,
            'total_conversas' => $totalConversas,
            'unidade'         => 'min',
        ];
    }

    // ==========================================
    // KPI 5: CONVERSAS SEM RESPOSTA (%)
    // ==========================================
    private function calcConversasSemResposta(Carbon $start, Carbon $end): array
    {
        // Conversas com inbound no período
        // Sem resposta = sem outbound em até 4h após o último inbound
        $result = DB::select("
            SELECT
                COUNT(*) as total_inbound,
                SUM(CASE WHEN sem_resposta = 1 THEN 1 ELSE 0 END) as sem_resposta
            FROM (
                SELECT
                    c.id,
                    c.last_incoming_at,
                    CASE
                        WHEN NOT EXISTS (
                            SELECT 1 FROM wa_messages m2
                            WHERE m2.conversation_id = c.id
                              AND m2.direction = 2
                              AND m2.sent_at > c.last_incoming_at
                              AND m2.sent_at <= DATE_ADD(c.last_incoming_at, INTERVAL 4 HOUR)
                        ) THEN 1
                        ELSE 0
                    END as sem_resposta
                FROM wa_conversations c
                WHERE c.last_incoming_at BETWEEN ? AND ?
            ) sub
        ", [$start->toDateTimeString(), $end->toDateTimeString()]);

        $total = (int) ($result[0]->total_inbound ?? 0);
        $semResposta = (int) ($result[0]->sem_resposta ?? 0);
        $valor = $total > 0 ? round(($semResposta / $total) * 100, 1) : 0;

        return [
            'valor'        => $valor,
            'sem_resposta' => $semResposta,
            'total'        => $total,
            'unidade'      => '%',
        ];
    }

    // ==========================================
    // KPI 6: ALCANCE DE AVISOS (%)
    // ==========================================
    private function calcAlcanceAvisos(Carbon $start, Carbon $end): array
    {
        // Avisos ativos publicados no mês
        // Lidos em até 48h da publicação
        $totalUsuarios = DB::table('users')->where('ativo', true)->count();
        if ($totalUsuarios === 0) {
            // Fallback: contar todos os usuários
            $totalUsuarios = DB::table('users')->count();
        }
        if ($totalUsuarios === 0) {
            return ['valor' => 0, 'avisos_publicados' => 0, 'unidade' => '%'];
        }

        $avisos = DB::table('avisos')
            ->where('status', 'ativo')
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->select('id', 'created_at')
            ->get();

        if ($avisos->isEmpty()) {
            return ['valor' => 0, 'avisos_publicados' => 0, 'unidade' => '%'];
        }

        $totalAlcance = 0;
        $totalPossivel = 0;

        foreach ($avisos as $aviso) {
            $limite48h = Carbon::parse($aviso->created_at)->addHours(48);

            $leituras = DB::table('avisos_lidos')
                ->where('aviso_id', $aviso->id)
                ->where('lido_em', '<=', $limite48h)
                ->count();

            $totalAlcance  += $leituras;
            $totalPossivel += $totalUsuarios;
        }

        $valor = $totalPossivel > 0
            ? round(($totalAlcance / $totalPossivel) * 100, 1)
            : 0;

        return [
            'valor'              => $valor,
            'avisos_publicados'  => $avisos->count(),
            'leituras_48h'       => $totalAlcance,
            'leituras_possiveis' => $totalPossivel,
            'unidade'            => '%',
        ];
    }

    // ==========================================
    // KPI 7: SAÚDE DE SINCRONIZAÇÃO (%)
    // ==========================================
    private function calcSaudeSincronizacao(Carbon $start, Carbon $end): array
    {
        $stats = DB::table('sync_runs')
            ->whereBetween('started_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->select(DB::raw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as sucesso
            "))
            ->first();

        $total   = (int) ($stats->total ?? 0);
        $sucesso = (int) ($stats->sucesso ?? 0);
        $valor   = $total > 0 ? round(($sucesso / $total) * 100, 1) : 0;

        return [
            'valor'   => $valor,
            'sucesso' => $sucesso,
            'total'   => $total,
            'unidade' => '%',
        ];
    }

    // ==========================================
    // KPI 8: ADOÇÃO DO CRM (%)
    // ==========================================
    private function calcAdocaoCrm(Carbon $start, Carbon $end): array
    {
        // Oportunidades criadas ou atualizadas no mês
        $totalOportunidades = DB::table('crm_opportunities')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                  ->orWhereBetween('updated_at', [$start->toDateTimeString(), $end->toDateTimeString()]);
            })
            ->count();

        // Oportunidades com pelo menos 1 atividade registrada no mês
        $comAtividade = DB::table('crm_opportunities as o')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('o.created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                  ->orWhereBetween('o.updated_at', [$start->toDateTimeString(), $end->toDateTimeString()]);
            })
            ->whereExists(function ($sub) use ($start, $end) {
                $sub->select(DB::raw(1))
                    ->from('crm_activities as a')
                    ->whereColumn('a.opportunity_id', 'o.id')
                    ->whereBetween('a.created_at', [$start->toDateTimeString(), $end->toDateTimeString()]);
            })
            ->count();

        $valor = $totalOportunidades > 0
            ? round(($comAtividade / $totalOportunidades) * 100, 1)
            : 0;

        return [
            'valor'              => $valor,
            'com_atividade'      => $comAtividade,
            'total_oportunidades'=> $totalOportunidades,
            'unidade'            => '%',
        ];
    }

    // ==========================================
    // UTILITÁRIO: Contar dias úteis (seg-sex)
    // ==========================================
    private function countBusinessDays(Carbon $start, Carbon $end): int
    {
        $count = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if ($current->isWeekday()) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }
}
