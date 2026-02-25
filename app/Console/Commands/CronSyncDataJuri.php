<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DataJuriSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CronSyncDataJuri extends Command
{
    protected $signature = 'cron:sync-datajuri';
    protected $description = 'Cron: Sincroniza todos os modulos DataJuri (via SyncService_NOVO)';

    public function handle()
    {
        try {
            Log::info('[CRON] Iniciando sync DataJuri (SyncService_NOVO)');

            $service = new DataJuriSyncService();

            if (!$service->authenticate()) {
                Log::error('[CRON] Falha na autenticacao DataJuri');
                return 1;
            }

            $syncId = 'cron_' . now()->format('Ymd_His');
            $service->setSyncId($syncId);

            $results = $service->syncAll();

            // Ciclo stale para ContasReceber (detectar exclusoes no DataJuri)
            try {
                Log::info('[CRON] Iniciando ciclo stale ContasReceber...');
                DB::table('contas_receber')->where('origem', 'datajuri')->update(['is_stale' => true]);
                $service->syncContasReceber();
                $staleRemoved = DB::table('contas_receber')->where('origem', 'datajuri')->where('is_stale', true)->count();
                if ($staleRemoved > 0) {
                    DB::table('contas_receber')->where('origem', 'datajuri')->where('is_stale', true)->delete();
                    Log::info("[CRON] ContasReceber: {$staleRemoved} orfaos removidos");
                }
            } catch (\Exception $e) {
                DB::table('contas_receber')->where('is_stale', true)->update(['is_stale' => false]);
                Log::error('[CRON] Erro ciclo stale ContasReceber: ' . $e->getMessage());
            }

            $stats = $service->getStats();
            Log::info('[CRON] Sync DataJuri OK', $stats);

            return 0;
        } catch (\Exception $e) {
            Log::error('[CRON] Erro sync DataJuri: ' . $e->getMessage());
            return 1;
        }
    }
}
