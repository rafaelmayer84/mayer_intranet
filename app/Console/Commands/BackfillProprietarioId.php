<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillProprietarioId extends Command
{
    protected $signature = 'movimentos:backfill-proprietario-id';
    protected $description = 'Preenche proprietario_id a partir do payload_raw dos movimentos';

    public function handle(): int
    {
        $this->info('Iniciando backfill de proprietario_id...');

        $total = 0;
        $updated = 0;
        $skipped = 0;

        DB::table('movimentos')
            ->whereNull('proprietario_id')
            ->whereNotNull('payload_raw')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$total, &$updated, &$skipped) {
                foreach ($rows as $row) {
                    $total++;
                    $payload = json_decode($row->payload_raw, true);
                    $propId = $payload['proprietarioId'] ?? null;

                    if (!empty($propId) && is_numeric($propId)) {
                        DB::table('movimentos')
                            ->where('id', $row->id)
                            ->update(['proprietario_id' => (int) $propId]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $this->info("Concluído: {$total} analisados, {$updated} atualizados, {$skipped} sem proprietarioId no payload.");

        return Command::SUCCESS;
    }
}
