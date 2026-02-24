<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Crm\CrmSegmentationService;

class CrmSegmentarBatch extends Command
{
    protected $signature = 'crm:segmentar-batch {--force : Força recálculo mesmo com cache válido} {--limit=0 : Limita quantidade (0=todos)} {--sleep=1 : Segundos entre chamadas}';
    protected $description = 'Segmenta todos os crm_accounts via IA (gpt-5-mini)';

    public function handle()
    {
        $force = $this->option('force');
        $limit = (int) $this->option('limit');
        $sleep = (int) $this->option('sleep');

        $query = DB::table('crm_accounts')->orderBy('id');

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('segment_cached_at')
                  ->orWhere('segment_cached_at', '<', now()->subDays(7));
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $accounts = $query->pluck('name', 'id');
        $total = $accounts->count();

        $this->info("Segmentando {$total} accounts (force={$force}, sleep={$sleep}s)...");

        if ($total === 0) {
            $this->info('Nenhum account para segmentar.');
            return 0;
        }

        $svc = new CrmSegmentationService();
        $ok = 0;
        $erros = 0;
        $segments = [];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($accounts as $id => $name) {
            try {
                $result = $svc->segmentar($id, $force);
                if ($result && isset($result['segment'])) {
                    $ok++;
                    $seg = $result['segment'];
                    $segments[$seg] = ($segments[$seg] ?? 0) + 1;
                } else {
                    $erros++;
                    $this->newLine();
                    $this->warn("  FALHA: #{$id} {$name}");
                }
            } catch (\Exception $e) {
                $erros++;
                $this->newLine();
                $this->error("  ERRO #{$id}: " . $e->getMessage());
            }

            $bar->advance();

            if ($sleep > 0) {
                sleep($sleep);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Concluído: {$ok} OK / {$erros} erros de {$total} total");
        $this->newLine();
        $this->info("Distribuição por segmento:");
        arsort($segments);
        foreach ($segments as $seg => $qty) {
            $this->line("  {$seg}: {$qty}");
        }

        return 0;
    }
}
