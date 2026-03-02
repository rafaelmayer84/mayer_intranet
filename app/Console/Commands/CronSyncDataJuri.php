<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\DataJuriSyncOrchestrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CronSyncDataJuri extends Command
{
    protected $signature = 'cron:sync-datajuri';
    protected $description = 'Cron: Sincroniza todos os modulos DataJuri via Orchestrator (grava sync_runs)';

    public function handle()
    {
        try {
            Log::info('[CRON] Iniciando sync DataJuri (Orchestrator)');

            $orchestrator = new DataJuriSyncOrchestrator();
            $orchestrator->cleanupStaleRuns();

            // Verificar se ja existe sync em andamento
            $running = DB::table('sync_runs')->where('status', 'running')->first();
            if ($running) {
                Log::warning('[CRON] Sync abortada: ja existe sync em andamento', ['run_id' => $running->run_id]);
                return 0;
            }

            // Obter token OAuth
            $token = $orchestrator->getAccessToken();
            if (!$token) {
                Log::error('[CRON] Falha na autenticacao DataJuri');
                return 1;
            }

            // Buscar modulos habilitados
            $datajuriConfig = config('datajuri.modulos', []);
            $modulosHabilitados = [];
            foreach ($datajuriConfig as $key => $mod) {
                if (!empty($mod['enabled'])) {
                    $modulosHabilitados[] = $key;
                }
            }

            if (empty($modulosHabilitados)) {
                Log::warning('[CRON] Nenhum modulo habilitado');
                return 0;
            }

            // Iniciar registro na sync_runs (por modulo individual)
            $totalProcessados = 0;
            $totalCriados = 0;
            $totalAtualizados = 0;
            $totalErros = 0;

            foreach ($modulosHabilitados as $modulo) {
                // Criar um run separado por modulo (igual a UI mostra)
                $moduleOrchestrator = new DataJuriSyncOrchestrator();
                $moduleOrchestrator->cleanupStaleRuns();

                try {
                    $tokenCheck = $moduleOrchestrator->getAccessToken();
                    if (!$tokenCheck) {
                        Log::error("[CRON] Token expirado antes de {$modulo}");
                        break;
                    }

                    $runId = $moduleOrchestrator->startRun("modulo_{$modulo}");
                    $result = $moduleOrchestrator->syncModule($modulo);

                    $processados = $result['processados'] ?? 0;
                    $criados = $result['criados'] ?? 0;
                    $atualizados = $result['atualizados'] ?? 0;
                    $erros = $result['erros'] ?? 0;

                    $totalProcessados += $processados;
                    $totalCriados += $criados;
                    $totalAtualizados += $atualizados;
                    $totalErros += $erros;

                    $mensagem = "{$modulo}: {$processados} processados";
                    if ($criados > 0) $mensagem .= ", {$criados} criados";
                    if ($atualizados > 0) $mensagem .= ", {$atualizados} atualizados";
                    if ($erros > 0) $mensagem .= ", {$erros} erros";

                    DB::table('sync_runs')->where('run_id', $runId)->update([
                        'registros_processados' => $processados,
                        'registros_criados' => $criados,
                        'registros_atualizados' => $atualizados,
                        'erros' => $erros,
                    ]);

                    $moduleOrchestrator->finishRun('completed', $mensagem);
                    Log::info("[CRON] {$mensagem}");

                } catch (\Exception $e) {
                    $totalErros++;
                    try {
                        $moduleOrchestrator->finishRun('failed', "Erro {$modulo}: " . substr($e->getMessage(), 0, 200));
                    } catch (\Exception $fe) {
                        Log::error("[CRON] Falha ao finalizar run de {$modulo}: " . $fe->getMessage());
                    }
                    Log::error("[CRON] Erro sync {$modulo}: " . $e->getMessage());
                }
            }

            // Ciclo stale para ContasReceber
            try {
                Log::info('[CRON] Iniciando ciclo stale ContasReceber...');
                DB::table('contas_receber')->where('origem', 'datajuri')->update(['is_stale' => true]);

                $staleOrchestrator = new DataJuriSyncOrchestrator();
                $staleToken = $staleOrchestrator->getAccessToken();
                if ($staleToken) {
                    $staleOrchestrator->startRun('stale_ContasReceber');
                    $staleOrchestrator->syncModule('ContasReceber');
                    $staleRemoved = DB::table('contas_receber')->where('origem', 'datajuri')->where('is_stale', true)->count();
                    if ($staleRemoved > 0) {
                        DB::table('contas_receber')->where('origem', 'datajuri')->where('is_stale', true)->delete();
                        Log::info("[CRON] ContasReceber: {$staleRemoved} orfaos removidos");
                    }
                    $staleOrchestrator->finishRun('completed', "Stale CR: {$staleRemoved} removidos");
                }
            } catch (\Exception $e) {
                DB::table('contas_receber')->where('is_stale', true)->update(['is_stale' => false]);
                Log::error('[CRON] Erro ciclo stale ContasReceber: ' . $e->getMessage());
            }

            Log::info('[CRON] Sync DataJuri OK', [
                'processados' => $totalProcessados,
                'criados' => $totalCriados,
                'atualizados' => $totalAtualizados,
                'erros' => $totalErros,
            ]);

            return 0;

        } catch (\Exception $e) {
            Log::error('[CRON] Erro sync DataJuri: ' . $e->getMessage());
            return 1;
        }
    }
}
