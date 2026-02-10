<?php

namespace App\Services;

use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NexoGerencialService
{
    // ═══════════════════════════════════════════════════════
    // KPIs (CARDS)
    // ═══════════════════════════════════════════════════════

    /**
     * Retorna todos os KPIs para os cards do painel gerencial.
     *
     * @param array $filtros ['periodo' => '7d|30d|custom', 'de' => 'Y-m-d', 'ate' => 'Y-m-d',
     *                        'advogado' => user_id, 'status' => 'open|closed', 'tipo' => 'lead|cliente|indefinido']
     * @return array
     */
    public function getKpis(array $filtros = []): array
    {
        $dateRange = $this->resolveDateRange($filtros);

        return [
            'conversas_abertas'       => $this->conversasAbertas(),
            'nao_lidas'               => $this->naoLidas(),
            'sla_media_hoje'          => $this->slaMedia('today'),
            'sla_media_7d'            => $this->slaMedia('7d'),
            'sla_media_30d'           => $this->slaMedia('30d'),
            'conversas_por_advogado'  => $this->conversasPorAdvogado($dateRange),
            'leads_sim_talvez'        => $this->leadsSimTalvez($dateRange),
            'taxa_conversao'          => $this->taxaConversao($dateRange),
        ];
    }

    private function conversasAbertas(): int
    {
        return WaConversation::open()->count();
    }

    private function naoLidas(): int
    {
        return WaConversation::unread()->count();
    }

    /**
     * SLA média da 1ª resposta humana em minutos.
     */
    private function slaMedia(string $periodo): ?float
    {
        $query = WaConversation::whereNotNull('first_response_at');

        switch ($periodo) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case '7d':
                $query->where('created_at', '>=', now()->subDays(7));
                break;
            case '30d':
                $query->where('created_at', '>=', now()->subDays(30));
                break;
        }

        $avg = $query->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_sla')
            ->value('avg_sla');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    /**
     * Conversas por advogado no período.
     */
    private function conversasPorAdvogado(array $dateRange): array
    {
        return WaConversation::select('assigned_user_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('assigned_user_id')
            ->when($dateRange['de'], fn($q) => $q->where('created_at', '>=', $dateRange['de']))
            ->when($dateRange['ate'], fn($q) => $q->where('created_at', '<=', $dateRange['ate']))
            ->groupBy('assigned_user_id')
            ->with('assignedUser:id,name')
            ->get()
            ->map(fn($item) => [
                'advogado' => $item->assignedUser?->name ?? 'Sem atribuição',
                'total'    => $item->total,
            ])
            ->toArray();
    }

    /**
     * Leads vinculados com intenção sim ou talvez.
     */
    private function leadsSimTalvez(array $dateRange): int
    {
        return Lead::whereIn('intencao_contratar', ['sim', 'talvez'])
            ->when($dateRange['de'], fn($q) => $q->where('created_at', '>=', $dateRange['de']))
            ->when($dateRange['ate'], fn($q) => $q->where('created_at', '<=', $dateRange['ate']))
            ->count();
    }

    /**
     * Taxa de conversão: leads "sim" / total de leads.
     */
    private function taxaConversao(array $dateRange): ?float
    {
        $total = Lead::when($dateRange['de'], fn($q) => $q->where('created_at', '>=', $dateRange['de']))
            ->when($dateRange['ate'], fn($q) => $q->where('created_at', '<=', $dateRange['ate']))
            ->count();

        if ($total === 0) {
            return null;
        }

        $sim = Lead::where('intencao_contratar', 'sim')
            ->when($dateRange['de'], fn($q) => $q->where('created_at', '>=', $dateRange['de']))
            ->when($dateRange['ate'], fn($q) => $q->where('created_at', '<=', $dateRange['ate']))
            ->count();

        return round(($sim / $total) * 100, 1);
    }

    // ═══════════════════════════════════════════════════════
    // GRÁFICOS (6 datasets Chart.js)
    // ═══════════════════════════════════════════════════════

    /**
     * Retorna todos os dados de gráficos.
     */
    public function getCharts(array $filtros = []): array
    {
        $dateRange = $this->resolveDateRange($filtros);

        return [
            'timeline_conversas'       => $this->timelineConversas(),
            'sla_por_dia'              => $this->slaPorDia(),
            'conversas_por_advogado'   => $this->chartConversasPorAdvogado($dateRange),
            'nao_lidas_por_advogado'   => $this->chartNaoLidasPorAdvogado(),
            'funil_intencao'           => $this->funilIntencao($dateRange),
            'faixas_tempo_resposta'    => $this->faixasTempoResposta(),
        ];
    }

    /**
     * Gráfico 1: Timeline de conversas por dia (últimos 30 dias).
     */
    private function timelineConversas(): array
    {
        $results = WaConversation::select(
                DB::raw('DATE(created_at) as dia'),
                DB::raw('COUNT(*) as total')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();

        // Preencher dias sem conversas
        $start = now()->subDays(30)->startOfDay();
        $labels = [];
        $data = [];

        for ($i = 0; $i <= 30; $i++) {
            $date = $start->copy()->addDays($i)->format('Y-m-d');
            $labels[] = $start->copy()->addDays($i)->format('d/m');
            $found = $results->firstWhere('dia', $date);
            $data[] = $found ? $found->total : 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Gráfico 2: SLA 1ª resposta média por dia.
     */
    private function slaPorDia(): array
    {
        $results = WaConversation::select(
                DB::raw('DATE(created_at) as dia'),
                DB::raw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_sla')
            )
            ->whereNotNull('first_response_at')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();

        $start = now()->subDays(30)->startOfDay();
        $labels = [];
        $data = [];

        for ($i = 0; $i <= 30; $i++) {
            $date = $start->copy()->addDays($i)->format('Y-m-d');
            $labels[] = $start->copy()->addDays($i)->format('d/m');
            $found = $results->firstWhere('dia', $date);
            $data[] = $found ? round((float) $found->avg_sla, 1) : 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Gráfico 3: Conversas por advogado (barra).
     */
    private function chartConversasPorAdvogado(array $dateRange): array
    {
        $results = WaConversation::select('assigned_user_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('assigned_user_id')
            ->when($dateRange['de'], fn($q) => $q->where('created_at', '>=', $dateRange['de']))
            ->when($dateRange['ate'], fn($q) => $q->where('created_at', '<=', $dateRange['ate']))
            ->groupBy('assigned_user_id')
            ->with('assignedUser:id,name')
            ->get();

        return [
            'labels' => $results->map(fn($r) => $r->assignedUser?->name ?? 'N/A')->toArray(),
            'data'   => $results->pluck('total')->toArray(),
        ];
    }

    /**
     * Gráfico 4: Não lidas por advogado (barra).
     */
    private function chartNaoLidasPorAdvogado(): array
    {
        $results = WaConversation::select('assigned_user_id', DB::raw('SUM(unread_count) as total_unread'))
            ->whereNotNull('assigned_user_id')
            ->where('unread_count', '>', 0)
            ->groupBy('assigned_user_id')
            ->with('assignedUser:id,name')
            ->get();

        return [
            'labels' => $results->map(fn($r) => $r->assignedUser?->name ?? 'N/A')->toArray(),
            'data'   => $results->pluck('total_unread')->toArray(),
        ];
    }

    /**
     * Gráfico 5: Funil de intenção (donut) — dos leads vinculados.
     */
    private function funilIntencao(array $dateRange): array
    {
        $results = Lead::select('intencao_contratar', DB::raw('COUNT(*) as total'))
            ->whereIn('intencao_contratar', ['sim', 'talvez', 'nao'])
            ->when($dateRange['de'], fn($q) => $q->where('created_at', '>=', $dateRange['de']))
            ->when($dateRange['ate'], fn($q) => $q->where('created_at', '<=', $dateRange['ate']))
            ->groupBy('intencao_contratar')
            ->get()
            ->keyBy('intencao_contratar');

        return [
            'labels' => ['Sim', 'Talvez', 'Não'],
            'data'   => [
                $results->get('sim')?->total ?? 0,
                $results->get('talvez')?->total ?? 0,
                $results->get('nao')?->total ?? 0,
            ],
        ];
    }

    /**
     * Gráfico 6: Tempo desde última msg do cliente — faixas (donut).
     * Faixas: 0-30min, 30min-2h, 2-24h, >24h
     */
    private function faixasTempoResposta(): array
    {
        $now = now();

        $faixa1 = WaConversation::open()
            ->where('last_incoming_at', '>=', $now->copy()->subMinutes(30))
            ->count();

        $faixa2 = WaConversation::open()
            ->where('last_incoming_at', '<', $now->copy()->subMinutes(30))
            ->where('last_incoming_at', '>=', $now->copy()->subHours(2))
            ->count();

        $faixa3 = WaConversation::open()
            ->where('last_incoming_at', '<', $now->copy()->subHours(2))
            ->where('last_incoming_at', '>=', $now->copy()->subHours(24))
            ->count();

        $faixa4 = WaConversation::open()
            ->where('last_incoming_at', '<', $now->copy()->subHours(24))
            ->count();

        return [
            'labels' => ['0-30min', '30min-2h', '2h-24h', '>24h'],
            'data'   => [$faixa1, $faixa2, $faixa3, $faixa4],
        ];
    }

    // ═══════════════════════════════════════════════════════
    // HELPER: RESOLUÇÃO DE PERÍODO
    // ═══════════════════════════════════════════════════════

    private function resolveDateRange(array $filtros): array
    {
        $periodo = $filtros['periodo'] ?? '30d';
        $de = $filtros['de'] ?? null;
        $ate = $filtros['ate'] ?? null;

        if ($de && $ate) {
            return ['de' => Carbon::parse($de)->startOfDay(), 'ate' => Carbon::parse($ate)->endOfDay()];
        }

        return match ($periodo) {
            'today' => ['de' => today(), 'ate' => now()],
            '7d'    => ['de' => now()->subDays(7), 'ate' => now()],
            '30d'   => ['de' => now()->subDays(30), 'ate' => now()],
            '90d'   => ['de' => now()->subDays(90), 'ate' => now()],
            default => ['de' => now()->subDays(30), 'ate' => now()],
        };
    }
}
