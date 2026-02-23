<?php

namespace App\Console\Commands;

use App\Models\SystemEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OcorrenciasBackfill extends Command
{
    protected $signature = 'ocorrencias:backfill';
    protected $description = 'Popula system_events com dados históricos retroativos';

    public function handle()
    {
        $total = 0;

        // ─── GDP: Penalidades existentes ─────────────────────
        if (DB::getSchemaBuilder()->hasTable('gdp_penalizacoes')) {
            $penalidades = DB::table('gdp_penalizacoes')
                ->leftJoin('users', 'gdp_penalizacoes.user_id', '=', 'users.id')
                ->leftJoin('gdp_penalizacao_tipos', 'gdp_penalizacoes.tipo_id', '=', 'gdp_penalizacao_tipos.id')
                ->select('gdp_penalizacoes.*', 'users.name as user_name', 'gdp_penalizacao_tipos.nome as tipo_nome')
                ->get();

            foreach ($penalidades as $p) {
                $exists = SystemEvent::where('event_type', 'penalidade.criada')
                    ->where('related_model', 'GdpPenalizacao')
                    ->where('related_id', $p->id)
                    ->exists();

                if (!$exists) {
                    SystemEvent::create([
                        'category'      => 'gdp',
                        'severity'      => 'warning',
                        'event_type'    => 'penalidade.criada',
                        'title'         => 'Penalidade: ' . ($p->tipo_nome ?? 'N/A') . ' - ' . ($p->user_name ?? 'N/A'),
                        'metadata'      => json_decode(json_encode(['user_id' => $p->user_id, 'tipo' => $p->tipo_nome, 'status' => $p->status ?? null]), true),
                        'related_model' => 'GdpPenalizacao',
                        'related_id'    => $p->id,
                        'user_name'     => 'Sistema (backfill)',
                        'created_at'    => $p->created_at ?? now(),
                    ]);
                    $total++;
                }
            }
            $this->info("GDP Penalidades: {$total} eventos criados");
        }

        // ─── Sistema: Sync runs ──────────────────────────────
        $syncCount = 0;
        if (DB::getSchemaBuilder()->hasTable('sync_runs')) {
            $syncs = DB::table('sync_runs')->orderBy('created_at')->get();

            foreach ($syncs as $s) {
                $exists = SystemEvent::where('event_type', 'sync.concluido')
                    ->where('related_model', 'SyncRun')
                    ->where('related_id', $s->id)
                    ->exists();

                if (!$exists) {
                    $severity = (!empty($s->error) || (!empty($s->status) && $s->status === 'failed')) ? 'error' : 'info';
                    $eventType = $severity === 'error' ? 'sync.falhou' : 'sync.concluido';

                    SystemEvent::create([
                        'category'      => 'sistema',
                        'severity'      => $severity,
                        'event_type'    => $eventType,
                        'title'         => 'Sync DataJuri' . ($severity === 'error' ? ' (falhou)' : ''),
                        'description'   => $s->error ?? null,
                        'metadata'      => json_decode(json_encode(['duration' => $s->duration_seconds ?? null, 'records' => $s->total_records ?? null]), true),
                        'related_model' => 'SyncRun',
                        'related_id'    => $s->id,
                        'user_name'     => 'Sistema (cron)',
                        'created_at'    => $s->created_at ?? now(),
                    ]);
                    $syncCount++;
                }
            }
            $this->info("Sync runs: {$syncCount} eventos criados");
            $total += $syncCount;
        }

        // ─── Sistema: Failed jobs ────────────────────────────
        $failedCount = 0;
        if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            $faileds = DB::table('failed_jobs')->get();

            foreach ($faileds as $f) {
                $exists = SystemEvent::where('event_type', 'job.falhou')
                    ->where('related_model', 'FailedJob')
                    ->where('related_id', $f->id)
                    ->exists();

                if (!$exists) {
                    SystemEvent::create([
                        'category'      => 'sistema',
                        'severity'      => 'error',
                        'event_type'    => 'job.falhou',
                        'title'         => 'Job falhou: ' . class_basename($f->payload ? (json_decode($f->payload)->displayName ?? 'N/A') : 'N/A'),
                        'description'   => mb_substr($f->exception ?? '', 0, 500),
                        'metadata'      => json_decode(json_encode(['queue' => $f->queue ?? null, 'connection' => $f->connection ?? null]), true),
                        'related_model' => 'FailedJob',
                        'related_id'    => $f->id,
                        'user_name'     => 'Sistema',
                        'created_at'    => $f->failed_at ?? now(),
                    ]);
                    $failedCount++;
                }
            }
            $this->info("Failed jobs: {$failedCount} eventos criados");
            $total += $failedCount;
        }

        // ─── CRM: Oportunidades ganhas/perdidas ─────────────
        $crmCount = 0;
        if (DB::getSchemaBuilder()->hasTable('crm_opportunities')) {
            $opps = DB::table('crm_opportunities')
                ->whereIn('status', ['won', 'lost'])
                ->get();

            foreach ($opps as $o) {
                $exists = SystemEvent::where('related_model', 'CrmOpportunity')
                    ->where('related_id', $o->id)
                    ->whereIn('event_type', ['oportunidade.ganha', 'oportunidade.perdida'])
                    ->exists();

                if (!$exists) {
                    $isWon = $o->status === 'won';
                    SystemEvent::create([
                        'category'      => 'crm',
                        'severity'      => $isWon ? 'info' : 'warning',
                        'event_type'    => $isWon ? 'oportunidade.ganha' : 'oportunidade.perdida',
                        'title'         => 'Oportunidade: ' . ($o->name ?? '#' . $o->id),
                        'metadata'      => json_decode(json_encode(['status' => $o->status, 'valor' => $o->amount ?? null]), true),
                        'related_model' => 'CrmOpportunity',
                        'related_id'    => $o->id,
                        'user_name'     => 'Sistema (backfill)',
                        'created_at'    => $o->updated_at ?? $o->created_at ?? now(),
                    ]);
                    $crmCount++;
                }
            }
            $this->info("CRM Oportunidades: {$crmCount} eventos criados");
            $total += $crmCount;
        }

        $this->info("─────────────────────────────────");
        $this->info("TOTAL: {$total} eventos retroativos criados");

        // Registrar o próprio backfill como evento
        SystemEvent::sistema('backfill.executado', 'info', "Backfill retroativo: {$total} eventos criados", null, ['total' => $total]);

        return 0;
    }
}
