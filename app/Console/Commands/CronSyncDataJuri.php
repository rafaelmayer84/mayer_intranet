<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DataJuriSyncOrchestrator;
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
            
            // Remover FaseProcesso pois não está configurado
            $modulos = ['Pessoa', 'Processo', 'Movimento', 
                        'Lancamento', 'Contrato', 'AtividadeCampo', 'HoraTrabalhada'];
            
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
            
            $orchestrator->finishRun('completed', 'cron_full');
            
            Log::info('[CRON] Sync DataJuri OK', $totais);
            return 0;
            
        } catch (\Exception $e) {
            Log::error('[CRON] Erro sync DataJuri: ' . $e->getMessage());
            return 1;
        }
    }
}
