<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GdpCiclo;
use App\Models\User;
use App\Services\Gdp\GdpPenalizacaoScanner;
use App\Models\SystemEvent;
use Carbon\Carbon;

class GdpPenalizacaoCommand extends Command
{
    protected $signature   = 'gdp:penalizacoes
                              {--mes= : Mes de competencia (default: atual)}
                              {--ano= : Ano de competencia (default: atual)}';

    protected $description = 'Scanner de conformidade GDP — verifica ocorrencias automaticas';

    public function handle(): int
    {
        $now = Carbon::now('America/Sao_Paulo');
        $mes = (int) ($this->option('mes') ?? $now->month);
        $ano = (int) ($this->option('ano') ?? $now->year);

        $this->info("[GDP-Scanner] Iniciando scan {$mes}/{$ano}...");

        $ciclo = GdpCiclo::ativo();
        if (!$ciclo) {
            $this->error('Nenhum ciclo GDP aberto.');
            return 1;
        }

        $usuarios = User::where('ativo', true)
            ->whereNotNull('datajuri_proprietario_id')
            ->pluck('id', 'name');

        if ($usuarios->isEmpty()) {
            $this->warn('Nenhum usuario ativo com datajuri_proprietario_id.');
            return 0;
        }

        $scanner = new GdpPenalizacaoScanner($ciclo->id, $mes, $ano);

        $totalNovas = 0;
        foreach ($usuarios as $name => $uid) {
            $result = $scanner->scanUsuario($uid);
            $count  = $result['total_penalizacoes'] ?? 0;
            $totalNovas += $count;
            if ($count > 0) {
                $this->line("  {$name}: {$count} ocorrencia(s)");
            }
        }

        $this->info("[GDP-Scanner] Concluido: {$totalNovas} nova(s) ocorrencia(s) para {$usuarios->count()} usuario(s).");

        SystemEvent::sistema('gdp.scanner.executado', 'info',
            "Scanner conformidade {$mes}/{$ano}: {$totalNovas} ocorrencias, {$usuarios->count()} usuarios"
        );

        return 0;
    }
}
