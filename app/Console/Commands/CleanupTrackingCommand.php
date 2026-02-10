<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupTrackingCommand extends Command
{
    /**
     * Nome e assinatura do comando
     *
     * @var string
     */
    protected $signature = 'nexo:cleanup-tracking 
                            {--days=7 : Número de dias para considerar registro antigo}
                            {--dry-run : Mostra quantos registros seriam deletados sem deletar}';

    /**
     * Descrição do comando
     *
     * @var string
     */
    protected $description = 'Remove registros antigos de leads_tracking que não foram matchados';

    /**
     * Execute the console command
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $this->info("═══════════════════════════════════════════════════════════");
        $this->info("NEXO - Cleanup de Tracking WhatsApp");
        $this->info("═══════════════════════════════════════════════════════════");
        $this->newLine();
        
        // ────────────────────────────────────────────────────────
        // Buscar registros a serem deletados
        // ────────────────────────────────────────────────────────
        
        $cutoffDate = now()->subDays($days);
        
        $query = DB::table('leads_tracking')
            ->where('created_at', '<', $cutoffDate)
            ->whereNull('matched_at');
        
        $count = $query->count();
        
        if ($count === 0) {
            $this->info("✓ Nenhum registro antigo encontrado.");
            $this->info("  Critério: criados há mais de {$days} dias e não matchados");
            return 0;
        }
        
        // ────────────────────────────────────────────────────────
        // Estatísticas
        // ────────────────────────────────────────────────────────
        
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Registros encontrados', number_format($count, 0, ',', '.')],
                ['Mais antigo que', $cutoffDate->format('d/m/Y H:i:s')],
                ['Status', 'Não matchados (sem conversa vinculada)']
            ]
        );
        
        $this->newLine();
        
        // ────────────────────────────────────────────────────────
        // Breakdown por origem
        // ────────────────────────────────────────────────────────
        
        $breakdown = DB::table('leads_tracking')
            ->select(
                DB::raw('CASE 
                    WHEN gclid IS NOT NULL THEN "Google Ads"
                    WHEN fbclid IS NOT NULL THEN "Facebook Ads"
                    WHEN utm_source IS NOT NULL THEN CONCAT("UTM: ", utm_source)
                    ELSE "Sem origem"
                END as origem'),
                DB::raw('COUNT(*) as total')
            )
            ->where('created_at', '<', $cutoffDate)
            ->whereNull('matched_at')
            ->groupBy('origem')
            ->get();
        
        if ($breakdown->isNotEmpty()) {
            $this->info("Breakdown por origem:");
            $this->table(
                ['Origem', 'Quantidade'],
                $breakdown->map(fn($item) => [
                    $item->origem,
                    number_format($item->total, 0, ',', '.')
                ])->toArray()
            );
            $this->newLine();
        }
        
        // ────────────────────────────────────────────────────────
        // Dry run ou execução real
        // ────────────────────────────────────────────────────────
        
        if ($dryRun) {
            $this->warn("⚠ DRY RUN MODE");
            $this->warn("  {$count} registros SERIAM deletados");
            $this->info("  Execute sem --dry-run para deletar");
            return 0;
        }
        
        // Confirmar antes de deletar
        if (!$this->confirm("Deletar {$count} registros?", false)) {
            $this->info("Operação cancelada.");
            return 1;
        }
        
        // Deletar
        $deleted = $query->delete();
        
        $this->info("✓ {$deleted} registros deletados com sucesso!");
        
        \Log::info("NEXO Tracking Cleanup executado", [
            'deleted' => $deleted,
            'days' => $days,
            'cutoff_date' => $cutoffDate->toDateTimeString()
        ]);
        
        return 0;
    }
}
