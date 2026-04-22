<?php

namespace App\Console\Commands;

use App\Services\Crm\CrmAccountState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CrmReclassificarLifecycle extends Command
{
    protected $signature = 'crm:reclassificar-lifecycle {--dry-run : não grava} {--limit= : limite de contas processadas}';
    protected $description = 'Reclassifica lifecycle de crm_accounts baseado em status_pessoa do DJ + inadimplência (data_vencimento NULL = protesto/judicial)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        $this->line('=== CRM Reclassificar Lifecycle ' . ($dryRun ? '[DRY-RUN]' : '[LIVE]') . ' ===');
        $this->line('Regras (precedência):');
        $this->line('  1. status_pessoa ~ Adversa/Contraparte/Fornecedor -> bloqueado_adversa');
        $this->line('  2. contas_receber c/ data_vencimento NULL OU status_pessoa ~ Inadimplente -> inadimplente');
        $this->line('  3. sem DJ e sem atividade 6m -> arquivado_orfao');
        $this->line('  4. status_pessoa ~ Estrategico/Ativo -> ativo');
        $this->line('  5. status_pessoa ~ Onboarding -> onboarding');
        $this->line('  6. status_pessoa ~ Inativo -> adormecido');
        $this->line('  7. resto -> arquivado');
        $this->line('');

        $q = DB::table('crm_accounts as a')
            ->leftJoin('clientes as c', 'c.datajuri_id', '=', 'a.datajuri_pessoa_id')
            ->select([
                'a.id', 'a.name', 'a.kind', 'a.lifecycle', 'a.datajuri_pessoa_id',
                'a.last_touch_at', 'a.created_at',
                'c.status_pessoa', 'c.is_cliente',
            ]);
        if ($limit) $q->limit((int)$limit);
        $accounts = $q->get();

        $buckets = [
            'bloqueado_adversa' => [],
            'inadimplente'      => [],
            'ativo'             => [],
            'adormecido'        => [],
            'onboarding'        => [],
            'arquivado_orfao'   => [],
            'arquivado'         => [],
        ];
        $semMudanca = 0;

        foreach ($accounts as $acc) {
            [$novo, $motivo] = $this->classificar($acc);

            if ($novo === $acc->lifecycle) {
                $semMudanca++;
                continue;
            }

            $buckets[$novo][] = [
                'id'     => $acc->id,
                'nome'   => mb_substr($acc->name ?? '(sem nome)', 0, 42),
                'kind'   => $acc->kind,
                'de'     => $acc->lifecycle,
                'para'   => $novo,
                'motivo' => $motivo,
                'dj'     => $acc->datajuri_pessoa_id,
                'status_dj' => $acc->status_pessoa,
            ];
        }

        // Resumo
        $this->line('=== RESUMO ===');
        $total = $accounts->count();
        $mudancas = $total - $semMudanca;
        $this->line("Total contas analisadas: {$total}");
        $this->line("Sem mudança: {$semMudanca}");
        $this->line("Mudanças previstas: {$mudancas}");
        $this->line('');
        foreach ($buckets as $bucket => $list) {
            if (count($list) > 0) {
                $this->line(sprintf("  -> %-22s: %d", $bucket, count($list)));
            }
        }
        $this->line('');

        // Detalhamento por bucket (mostra TODOS nos críticos)
        foreach ($buckets as $bucket => $list) {
            if (empty($list)) continue;

            $this->line('');
            $this->line("=== {$bucket} (" . count($list) . ") ===");
            printf("%-5s | %-42s | %-8s | %-20s | %-8s | %s\n",
                'id', 'nome', 'kind', 'de -> para', 'dj', 'motivo');
            echo str_repeat('-', 130) . "\n";

            // Nos críticos mostra todos; nos leves só 20 amostra
            $sample = in_array($bucket, ['bloqueado_adversa','inadimplente'])
                ? $list
                : array_slice($list, 0, 20);

            foreach ($sample as $m) {
                printf("%-5s | %-42s | %-8s | %-20s | %-8s | %s\n",
                    $m['id'],
                    $m['nome'],
                    $m['kind'],
                    $m['de'] . ' -> ' . $m['para'],
                    $m['dj'] ?? '-',
                    $m['motivo']);
            }

            if (count($list) > count($sample)) {
                $this->line('  ... +' . (count($list) - count($sample)) . ' não exibidos');
            }
        }

        $this->line('');
        if ($dryRun) {
            $this->warn('DRY-RUN: nada foi gravado. Valide o output acima.');
            $this->line('Para aplicar: php artisan crm:reclassificar-lifecycle (sem --dry-run)');
            return self::SUCCESS;
        }

        // MODO LIVE: gravar mudanças
        $ts = now()->format('Ymd_His');
        $csvPath = storage_path("app/crm_reclassificacao_{$ts}.csv");
        $csv = fopen($csvPath, 'w');
        fputcsv($csv, ['id','nome','kind','lifecycle_antes','lifecycle_depois','motivo','datajuri_pessoa_id']);

        $gravados = 0;
        foreach ($buckets as $bucket => $list) {
            foreach ($list as $m) {
                DB::table('crm_accounts')
                    ->where('id', $m['id'])
                    ->update([
                        'lifecycle'  => $m['para'],
                        'updated_at' => now(),
                    ]);
                fputcsv($csv, [
                    $m['id'], $m['nome'], $m['kind'],
                    $m['de'], $m['para'], $m['motivo'], $m['dj'] ?? '',
                ]);
                $gravados++;
            }
        }
        fclose($csv);

        \Illuminate\Support\Facades\Log::info('[CrmReclassificarLifecycle] LIVE aplicado', [
            'gravados' => $gravados,
            'csv'      => $csvPath,
        ]);

        $this->info("LIVE: {$gravados} contas atualizadas.");
        $this->info("Auditoria: {$csvPath}");

        return self::SUCCESS;
    }

    /**
     * Delega para CrmAccountState (fonte única da verdade).
     * O $acc vem com status_pessoa e is_cliente já JOINados da query — encapsulamos em um
     * objeto djCache para a assinatura do State.
     */
    private function classificar(object $acc): array
    {
        $djCache = (object) [
            'status_pessoa' => $acc->status_pessoa ?? null,
            'is_cliente'    => $acc->is_cliente ?? 0,
        ];
        return CrmAccountState::computeLifecycle($acc, $djCache);
    }
}
