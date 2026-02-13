<?php

namespace App\Console\Commands;

use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmSyncCarteira extends Command
{
    protected $signature = 'crm:sync-carteira {--dry-run : Simula sem gravar}';
    protected $description = 'Sincroniza clientes DataJuri (tabela clientes) para crm_accounts';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $this->info('CRM Sync Carteira' . ($dryRun ? ' [DRY-RUN]' : ''));

        $clientes = DB::table('clientes')
            ->whereNotNull('datajuri_id')
            ->where(function ($q) {
                $q->where('is_cliente', 1)
                  ->orWhereNull('is_cliente');
            })
            ->get();

        $this->info("Clientes DataJuri encontrados: {$clientes->count()}");
        $created = $updated = $skipped = 0;

        foreach ($clientes as $cli) {
            $djId = (int) $cli->datajuri_id;
            if ($djId <= 0) { $skipped++; continue; }

            $docDigits = null;
            if (!empty($cli->cpf)) {
                $docDigits = preg_replace('/\D/', '', $cli->cpf);
            } elseif (!empty($cli->cnpj)) {
                $docDigits = preg_replace('/\D/', '', $cli->cnpj);
            }

            $emailNorm = !empty($cli->email) ? strtolower(trim($cli->email)) : null;
            $phoneNorm = !empty($cli->telefone) ? preg_replace('/\D/', '', $cli->telefone) : null;
            if ($phoneNorm && strlen($phoneNorm) === 11) $phoneNorm = '55' . $phoneNorm;

            if ($dryRun) {
                $this->line("  [DRY] {$cli->nome} (dj={$djId})");
                $created++;
                continue;
            }

            $account = CrmAccount::where('datajuri_pessoa_id', $djId)->first();

            if ($account) {
                // Atualizar dados DataJuri mas preservar campos CRM gerenciais
                $account->update([
                    'name'       => $cli->nome ?? $account->name,
                    'doc_digits' => $docDigits ?: $account->doc_digits,
                    'email'      => $emailNorm ?: $account->email,
                    'phone_e164' => $phoneNorm ?: $account->phone_e164,
                    'kind'       => 'client',
                ]);
                $updated++;
            } else {
                $account = CrmAccount::create([
                    'datajuri_pessoa_id' => $djId,
                    'kind'               => 'client',
                    'name'               => $cli->nome ?? 'Sem nome',
                    'doc_digits'         => $docDigits,
                    'email'              => $emailNorm,
                    'phone_e164'         => $phoneNorm,
                    'lifecycle'          => 'ativo',
                ]);
                $created++;
            }

            // Garantir identities
            $this->ensureIdentity($account->id, 'datajuri', (string) $djId);
            if ($docDigits) $this->ensureIdentity($account->id, 'doc', $docDigits);
            if ($emailNorm) $this->ensureIdentity($account->id, 'email', $emailNorm);
            if ($phoneNorm) $this->ensureIdentity($account->id, 'phone', $phoneNorm);
        }

        $this->info("Resultado: criados={$created}, atualizados={$updated}, ignorados={$skipped}");
        Log::info("[CRM] sync-carteira: created={$created}, updated={$updated}, skipped={$skipped}");

        return self::SUCCESS;
    }

    private function ensureIdentity(int $accountId, string $kind, string $valueNorm): void
    {
        CrmIdentity::firstOrCreate(
            ['kind' => $kind, 'value_norm' => $valueNorm],
            ['account_id' => $accountId, 'value' => $valueNorm]
        );
    }
}
