<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DataJuriSyncOrchestrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CronSyncDataJuri extends Command
{
    protected $signature = 'cron:sync-datajuri';
    protected $description = 'Cron: Sincroniza todos os 8 módulos DataJuri';

    public function handle()
    {
        try {
            Log::info('[CRON] Iniciando sync DataJuri completo');
            
            $orchestrator = new DataJuriSyncOrchestrator();
            $orchestrator->cleanupStaleRuns();
            
            $runId = $orchestrator->startRun('cron_full');
            
            // Iterar modulos habilitados do config (FIX 20/02/2026)
            $allModulos = config('datajuri.modulos', []);
            $modulos = [];
            foreach ($allModulos as $nome => $cfg) {
                if (!empty($cfg['enabled'])) $modulos[] = $nome;
            }
            
            $totais = ['processados' => 0, 'criados' => 0, 'atualizados' => 0, 'erros' => 0];
            
            foreach ($modulos as $modulo) {
                try {
                    $result = $orchestrator->syncModule($modulo);
                    $totais['processados'] += $result['processados'] ?? 0;
                    $totais['criados'] += $result['criados'] ?? 0;
                    $totais['atualizados'] += $result['atualizados'] ?? 0;
                    $totais['erros'] += $result['erros'] ?? 0;
                } catch (\Exception $e) {
                    Log::error("[CRON] Erro módulo {$modulo}: " . $e->getMessage());
                    $totais['erros']++;
                }
            }
            
            // Ciclo stale automatico para ContasReceber (detectar exclusoes no DataJuri)
            try {
                Log::info('[CRON] Iniciando ciclo stale ContasReceber...');
                DB::table('contas_receber')->where('origem', 'datajuri')->update(['is_stale' => true]);
                $orchestrator->syncModule('ContasReceber');
                $staleRemoved = DB::table('contas_receber')->where('origem', 'datajuri')->where('is_stale', true)->count();
                if ($staleRemoved > 0) {
                    DB::table('contas_receber')->where('origem', 'datajuri')->where('is_stale', true)->delete();
                    Log::info("[CRON] ContasReceber: {$staleRemoved} orfaos removidos");
                }
            } catch (\Exception $e) {
                DB::table('contas_receber')->where('is_stale', true)->update(['is_stale' => false]);
                Log::error('[CRON] Erro ciclo stale ContasReceber: ' . $e->getMessage());
            }

            $orchestrator->finishRun('completed', 'cron_full');
            
            Log::info('[CRON] Sync DataJuri OK', $totais);
            return 0;
            
        } catch (\Exception $e) {
            Log::error('[CRON] Erro sync DataJuri: ' . $e->getMessage());
            return 1;
        }
    }
}
