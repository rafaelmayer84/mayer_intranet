<?php

namespace App\Console\Commands;

use App\Services\NexoQa\NexoQaAggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NexoQaAggregateCommand extends Command
{
    protected $signature = 'nexo:qa-aggregate {week_start? : Data da segunda-feira (Y-m-d). Default: semana atual}';
    protected $description = 'Executa agregação semanal da pesquisa de qualidade NEXO QA (manual)';

    public function handle(NexoQaAggregationService $aggregationService): int
    {
        $weekStartInput = $this->argument('week_start');

        if ($weekStartInput) {
            try {
                $weekStart = Carbon::parse($weekStartInput)->startOfDay();
            } catch (\Exception $e) {
                $this->error("Data inválida: {$weekStartInput}. Use formato Y-m-d.");
                return 1;
            }
        } else {
            $weekStart = Carbon::now('America/Sao_Paulo')
                ->startOfWeek(Carbon::MONDAY)
                ->startOfDay();
        }

        $this->info("Agregando semana de {$weekStart->format('d/m/Y')} a {$weekStart->copy()->addDays(6)->format('d/m/Y')}");

        $count = $aggregationService->aggregateWeek($weekStart);

        $this->info("→ {$count} agregados gravados (+ GDP snapshots atualizados).");
        return 0;
    }
}
