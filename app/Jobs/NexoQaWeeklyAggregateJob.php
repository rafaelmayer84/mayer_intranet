<?php

namespace App\Jobs;

use App\Services\NexoQa\NexoQaAggregationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NexoQaWeeklyAggregateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $weekStartDate;
    public int $tries = 2;
    public int $timeout = 60;

    /**
     * @param string $weekStartDate Formato Y-m-d (segunda-feira da semana)
     */
    public function __construct(string $weekStartDate)
    {
        $this->weekStartDate = $weekStartDate;
        $this->onQueue('default');
    }

    public function handle(NexoQaAggregationService $aggregationService): void
    {
        $weekStart = Carbon::parse($this->weekStartDate)->startOfDay();

        Log::info('[NexoQA] Iniciando agregação semanal', [
            'week_start' => $weekStart->format('Y-m-d'),
        ]);

        $count = $aggregationService->aggregateWeek($weekStart);

        Log::info('[NexoQA] Agregação semanal concluída', [
            'week_start' => $weekStart->format('Y-m-d'),
            'aggregates' => $count,
        ]);
    }
}
