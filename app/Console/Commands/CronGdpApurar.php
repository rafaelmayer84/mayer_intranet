<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Gdp\GdpScoreService;
use App\Services\Gdp\GdpDataAdapter;

class CronGdpApurar extends Command
{
    protected $signature = 'cron:gdp-apurar';
    protected $description = 'Apura scores GDP do mes corrente para todos os usuarios ativos';

    public function handle(): int
    {
        $mes = (int) now()->month;
        $ano = (int) now()->year;

        $this->info("[GDP] Iniciando apuracao {$mes}/{$ano}...");

        $service = new GdpScoreService(new GdpDataAdapter());
        $result = $service->apurarMes($mes, $ano);

        if (isset($result['erro'])) {
            $this->error("[GDP] Erro: {$result['erro']}");
            return 1;
        }

        $this->info("[GDP] Concluido: {$result['usuarios']} usuarios, {$result['resultados']} resultados, {$result['snapshots']} snapshots, {$result['erros']} erros");

        foreach ($result['detalhes'] as $d) {
            $this->line("  -> {$d}");
        }

        return 0;
    }
}
