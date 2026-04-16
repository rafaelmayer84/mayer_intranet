<?php

namespace App\Console\Commands;

use App\Jobs\GerarRelatorioCeoJob;
use App\Models\RelatorioCeo;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GerarRelatorioCeoCommand extends Command
{
    protected $signature   = 'relatorio:gerar-ceo {--force : Gera mesmo que já existe um relatório para o período}';
    protected $description = 'Gera o relatório executivo quinzenal para o CEO';

    public function handle(): int
    {
        $fim    = now()->subDay()->endOfDay();
        $inicio = $fim->copy()->subDays(14)->startOfDay();

        // Evita duplicata
        if (!$this->option('force')) {
            $existente = RelatorioCeo::where('periodo_inicio', $inicio->toDateString())
                ->where('periodo_fim', $fim->toDateString())
                ->whereIn('status', ['queued', 'running', 'success'])
                ->first();

            if ($existente) {
                $this->info("Relatório para {$inicio->format('d/m/Y')} a {$fim->format('d/m/Y')} já existe (ID #{$existente->id}, status: {$existente->status}). Use --force para regerar.");
                return self::SUCCESS;
            }
        }

        $relatorio = RelatorioCeo::create([
            'periodo_inicio' => $inicio->toDateString(),
            'periodo_fim'    => $fim->toDateString(),
            'status'         => 'queued',
        ]);

        GerarRelatorioCeoJob::dispatch($relatorio->id);

        $this->info("Relatório CEO #{$relatorio->id} enfileirado · Período: {$inicio->format('d/m/Y')} a {$fim->format('d/m/Y')}");

        return self::SUCCESS;
    }
}
