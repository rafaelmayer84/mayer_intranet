<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Gdp\GdpApuracaoService;
use Carbon\Carbon;

class GdpApuracaoCron extends Command
{
    protected $signature = 'gdp:apurar
        {--mes= : Mes a apurar (default: atual)}
        {--ano= : Ano a apurar (default: atual)}';

    protected $description = 'Apura scores GDP + penalizacoes para o mes corrente (cron diario)';

    public function handle(): int
    {
        $mes = $this->option('mes') ?? Carbon::now()->month;
        $ano = $this->option('ano') ?? Carbon::now()->year;

        $this->info("GDP Apuracao: {$mes}/{$ano}");
        $this->info(str_repeat('-', 40));

        $service = new GdpApuracaoService();
        $resultado = $service->apurarMes((int) $mes, (int) $ano);

        if ($resultado['success']) {
            $this->info("Ciclo: {$resultado['ciclo']}");

            foreach ($resultado['resultados'] as $userId => $r) {
                if (isset($r['score_global'])) {
                    $this->line(sprintf(
                        "  User %d: Score %.1f | Penalizacoes: %d | Variavel: %d%%",
                        $userId,
                        $r['score_global'],
                        $r['total_penalizacoes'],
                        $r['percentual_variavel']
                    ));
                } else {
                    $this->warn("  User {$userId}: ERRO - " . ($r['error'] ?? 'desconhecido'));
                }
            }

            $this->info("Apuracao concluida com sucesso.");
        } else {
            $this->error("Falha: " . ($resultado['message'] ?? 'erro desconhecido'));
            return 1;
        }

        return 0;
    }
}
