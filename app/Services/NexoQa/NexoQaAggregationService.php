<?php

namespace App\Services\NexoQa;

use App\Models\NexoQaAggregateWeekly;
use App\Models\NexoQaResponseContent;
use App\Models\NexoQaSampledTarget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NexoQaAggregationService
{
    /**
     * Consolida respostas da semana por advogado responsável.
     * Grava nexo_qa_aggregates_weekly e atualiza gdp_snapshots.
     *
     * @param Carbon $weekStart Segunda-feira da semana a consolidar
     * @return int Quantidade de agregados gravados
     */
    public function aggregateWeek(Carbon $weekStart): int
    {
        $weekEnd = $weekStart->copy()->addDays(6); // domingo

        // Buscar todos os targets da semana que foram respondidos
        $data = DB::table('nexo_qa_sampled_targets as t')
            ->join('nexo_qa_responses_content as c', 'c.target_id', '=', 't.id')
            ->join('nexo_qa_responses_identity as i', 'i.target_id', '=', 't.id')
            ->whereNotNull('t.responsible_user_id')
            ->whereBetween('i.answered_at', [$weekStart->startOfDay(), $weekEnd->endOfDay()])
            ->select([
                't.responsible_user_id',
                'c.score_1_5',
                'c.nps',
            ])
            ->get();

        // Contar enviados por responsável na semana
        $sentCounts = DB::table('nexo_qa_sampled_targets')
            ->whereNotNull('responsible_user_id')
            ->where('send_status', 'SENT')
            ->whereBetween('sampled_at', [$weekStart->startOfDay(), $weekEnd->endOfDay()])
            ->select('responsible_user_id', DB::raw('COUNT(*) as total'))
            ->groupBy('responsible_user_id')
            ->pluck('total', 'responsible_user_id');

        // Agrupar por responsável
        $grouped = $data->groupBy('responsible_user_id');

        $count = 0;

        foreach ($grouped as $userId => $responses) {
            $scores = $responses->pluck('score_1_5')->filter()->values();
            $npsValues = $responses->pluck('nps')->filter()->values();

            $avgScore = $scores->isNotEmpty() ? round($scores->avg(), 2) : null;

            // NPS: promoters (9-10), passives (7-8), detractors (0-6)
            $promoters = $npsValues->filter(fn($v) => $v >= 9)->count();
            $passives = $npsValues->filter(fn($v) => $v >= 7 && $v <= 8)->count();
            $detractors = $npsValues->filter(fn($v) => $v <= 6)->count();

            $totalNps = $promoters + $passives + $detractors;
            $npsScore = $totalNps > 0
                ? round((($promoters - $detractors) / $totalNps) * 100, 2)
                : null;

            $targetsSent = $sentCounts->get($userId, 0);

            NexoQaAggregateWeekly::updateOrCreate(
                [
                    'week_start' => $weekStart->format('Y-m-d'),
                    'responsible_user_id' => $userId,
                ],
                [
                    'week_end' => $weekEnd->format('Y-m-d'),
                    'responses_count' => $responses->count(),
                    'avg_score' => $avgScore,
                    'nps_score' => $npsScore,
                    'detractors' => $detractors,
                    'passives' => $passives,
                    'promoters' => $promoters,
                    'targets_sent' => $targetsSent,
                    'created_at' => now(),
                ]
            );

            $count++;
        }

        // Atualizar GDP snapshots com dados QA do mês corrente
        $this->updateGdpSnapshots($weekStart);

        Log::info('[NexoQA] Agregação semanal concluída', [
            'week_start' => $weekStart->format('Y-m-d'),
            'aggregates_count' => $count,
        ]);

        return $count;
    }

    /**
     * Atualiza gdp_snapshots com dados QA consolidados do mês.
     * Calcula a média das semanas do mês para cada usuário.
     */
    private function updateGdpSnapshots(Carbon $weekStart): void
    {
        $mes = $weekStart->month;
        $ano = $weekStart->year;
        $monthStart = Carbon::create($ano, $mes, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();

        // Buscar agregados do mês inteiro
        $monthlyData = DB::table('nexo_qa_aggregates_weekly')
            ->whereBetween('week_start', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
            ->select([
                'responsible_user_id',
                DB::raw('AVG(avg_score) as qa_avg_score'),
                DB::raw('AVG(nps_score) as qa_nps'),
                DB::raw('SUM(responses_count) as qa_responses_count'),
                DB::raw('SUM(targets_sent) as total_sent'),
            ])
            ->groupBy('responsible_user_id')
            ->get();

        foreach ($monthlyData as $row) {
            $responseRate = $row->total_sent > 0
                ? round(($row->qa_responses_count / $row->total_sent) * 100, 2)
                : null;

            // Atualizar apenas as colunas QA (sem tocar nos scores existentes)
            DB::table('gdp_snapshots')
                ->where('user_id', $row->responsible_user_id)
                ->where('mes', $mes)
                ->where('ano', $ano)
                ->update([
                    'qa_avg_score' => $row->qa_avg_score !== null ? round($row->qa_avg_score, 2) : null,
                    'qa_nps' => $row->qa_nps !== null ? round($row->qa_nps, 2) : null,
                    'qa_response_rate' => $responseRate,
                    'qa_responses_count' => (int) $row->qa_responses_count,
                    'updated_at' => now(),
                ]);
        }
    }
}
