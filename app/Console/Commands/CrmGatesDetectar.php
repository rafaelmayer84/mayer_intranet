<?php

namespace App\Console\Commands;

use App\Services\Crm\CrmDataGateDetector;
use Illuminate\Console\Command;

class CrmGatesDetectar extends Command
{
    protected $signature = 'crm:gates-detectar {--dry-run} {--limit=}';
    protected $description = 'Detecta divergencias DJ x realidade e abre gates bloqueantes no CRM.';

    public function handle(CrmDataGateDetector $detector): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int)$this->option('limit') : null;

        $this->line('=== CRM Gates Detectar ' . ($dryRun ? '[DRY-RUN]' : '[LIVE]') . ' ===');
        $stats = $detector->detectar($limit, $dryRun);

        $this->line("Analisadas: {$stats['analisadas']}");
        $this->line("Gates {$this->abertosLabel($dryRun)}: {$stats['gates_abertos']}");
        $this->line("Gates ja existentes (ignorados): {$stats['gates_existentes']}");
        $this->line('');
        foreach ($stats['por_tipo'] as $tipo => $n) {
            $this->line(sprintf("  -> %-35s: %d", $tipo, $n));
        }

        if (!empty($stats['exemplos'])) {
            $this->line('');
            $this->line('=== Amostra (ate 20) ===');
            foreach ($stats['exemplos'] as $e) {
                $this->line(sprintf(
                    '  #%-5s %-40s | %-30s | dj=%s',
                    $e['account_id'],
                    mb_substr($e['name'] ?? '-', 0, 40),
                    $e['tipo'],
                    $e['dj'] ?? '-'
                ));
            }
        }

        return self::SUCCESS;
    }

    private function abertosLabel(bool $dryRun): string
    {
        return $dryRun ? 'que seriam abertos' : 'abertos';
    }
}
