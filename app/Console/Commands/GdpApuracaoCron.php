<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Gdp\GdpScoreService;
use App\Models\GdpCiclo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GdpApuracaoCron extends Command
{
    protected $signature = 'gdp:apurar
        {--mes= : Mes especifico a apurar (default: todos os abertos do ciclo)}
        {--ano= : Ano especifico}';

    protected $description = 'Apura scores GDP para todos os meses abertos do ciclo ativo (cron diario 00:30)';

    public function handle(): int
    {
        $inicio = microtime(true);
        $this->info('[GDP-Cron] Inicio: ' . now()->format('Y-m-d H:i:s'));

        $service = app(GdpScoreService::class);

        // Se mes especifico foi passado, apurar somente ele
        if ($this->option('mes')) {
            $mes = (int) $this->option('mes');
            $ano = (int) ($this->option('ano') ?? now()->year);
            $this->info("Apurando mes especifico: {$mes}/{$ano}");
            $stats = $service->apurarMes($mes, $ano);
            $this->printStats($mes, $ano, $stats);
            return isset($stats['erro']) ? 1 : 0;
        }

        // Modo padrao: apurar todos os meses nao-congelados do ciclo ativo
        $ciclo = GdpCiclo::ativo();
        if (!$ciclo) {
            $this->error('Nenhum ciclo GDP ativo.');
            Log::warning('[GDP-Cron] Nenhum ciclo ativo encontrado.');
            return 1;
        }

        $this->info("Ciclo: {$ciclo->nome}");

        $dataInicio = Carbon::parse($ciclo->data_inicio);
        $dataFim = Carbon::parse($ciclo->data_fim);
        $hoje = Carbon::now();
        $limiteAte = $hoje->lt($dataFim) ? $hoje : $dataFim;

        $totalStats = ['meses_apurados' => 0, 'meses_pulados' => 0, 'erros' => 0];

        $cursor = $dataInicio->copy()->startOfMonth();
        while ($cursor->lte($limiteAte)) {
            $mes = $cursor->month;
            $ano = $cursor->year;

            // Verificar se mes esta congelado
            $congelado = DB::table('gdp_snapshots')
                ->where('ciclo_id', $ciclo->id)
                ->where('mes', $mes)
                ->where('ano', $ano)
                ->where('congelado', true)
                ->exists();

            if ($congelado) {
                $this->line("  {$mes}/{$ano}: CONGELADO - pulando");
                $totalStats['meses_pulados']++;
                $cursor->addMonth();
                continue;
            }

            $this->info("  Apurando {$mes}/{$ano}...");
            $stats = $service->apurarMes($mes, $ano, $ciclo->id);
            $this->printStats($mes, $ano, $stats);

            if (isset($stats['erro'])) {
                $totalStats['erros']++;
            } else {
                $totalStats['meses_apurados']++;
            }

            $cursor->addMonth();
        }

        $duracao = round(microtime(true) - $inicio, 2);
        $resumo = "[GDP-Cron] Concluido em {$duracao}s | Apurados: {$totalStats['meses_apurados']} | Pulados: {$totalStats['meses_pulados']} | Erros: {$totalStats['erros']}";
        $this->info($resumo);
        Log::info($resumo);

        return $totalStats['erros'] > 0 ? 1 : 0;
    }

    private function printStats(int $mes, int $ano, array $stats): void
    {
        if (isset($stats['erro'])) {
            $this->warn("    {$mes}/{$ano}: {$stats['erro']}");
            return;
        }
        foreach ($stats['detalhes'] ?? [] as $detalhe) {
            $this->line("    {$detalhe}");
        }
    }
}
