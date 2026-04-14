<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Popula linked_crm_account_id em wa_conversations via phone matching.
 *
 * Prioridade de resolução:
 * 1. linked_lead_id → leads.crm_account_id
 * 2. linked_cliente_id → clientes.datajuri_id → crm_accounts.datajuri_pessoa_id
 * 3. phone → crm_identities (kind='phone', value_norm E164) → account_id
 */
class NexoBackfillCrmAccountId extends Command
{
    protected $signature = 'nexo:backfill-crm-account-id {--dry-run : Simular sem gravar}';
    protected $description = 'Popula linked_crm_account_id em wa_conversations via phone matching com crm_accounts';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $updated = 0;
        $notFound = 0;

        // Buscar conversas sem linked_crm_account_id
        $conversations = DB::table('wa_conversations')
            ->whereNull('linked_crm_account_id')
            ->select(['id', 'phone', 'linked_lead_id', 'linked_cliente_id'])
            ->orderBy('id')
            ->get();

        $this->info("Conversas sem crm_account_id: {$conversations->count()}");

        foreach ($conversations as $conv) {
            $accountId = null;

            // 1. Via lead → crm_account_id
            if ($conv->linked_lead_id) {
                $accountId = DB::table('leads')
                    ->where('id', $conv->linked_lead_id)
                    ->whereNotNull('crm_account_id')
                    ->value('crm_account_id');
            }

            // 2. Via cliente legacy → datajuri_id → crm_accounts
            if (!$accountId && $conv->linked_cliente_id) {
                $datajuriId = DB::table('clientes')
                    ->where('id', $conv->linked_cliente_id)
                    ->whereNotNull('datajuri_id')
                    ->value('datajuri_id');

                if ($datajuriId) {
                    $accountId = DB::table('crm_accounts')
                        ->where('datajuri_pessoa_id', $datajuriId)
                        ->value('id');
                }
            }

            // 3. Via phone → crm_identities (E164) com JOIN para garantir account existe
            if (!$accountId && !empty($conv->phone)) {
                $phoneNorm = $this->normalizePhone($conv->phone);

                if ($phoneNorm) {
                    $accountId = DB::table('crm_identities')
                        ->join('crm_accounts', 'crm_accounts.id', '=', 'crm_identities.account_id')
                        ->where('crm_identities.kind', 'phone')
                        ->where('crm_identities.value_norm', $phoneNorm)
                        ->value('crm_identities.account_id');
                }

                // Fallback: últimos 9 dígitos (para variações de DDI)
                if (!$accountId && strlen($phoneNorm ?? '') >= 9) {
                    $last9 = substr(preg_replace('/\D/', '', $conv->phone), -9);
                    $accountId = DB::table('crm_identities')
                        ->join('crm_accounts', 'crm_accounts.id', '=', 'crm_identities.account_id')
                        ->where('crm_identities.kind', 'phone')
                        ->where('crm_identities.value_norm', 'like', '%' . $last9)
                        ->value('crm_identities.account_id');
                }
            }

            // Verificação final: confirmar que o account realmente existe
            if ($accountId && !DB::table('crm_accounts')->where('id', $accountId)->exists()) {
                $accountId = null;
            }

            if ($accountId) {
                if (!$dryRun) {
                    DB::table('wa_conversations')
                        ->where('id', $conv->id)
                        ->update(['linked_crm_account_id' => $accountId]);
                } else {
                    $this->line("  [DRY-RUN] Conversa #{$conv->id} → CrmAccount #{$accountId}");
                }
                $updated++;
            } else {
                $notFound++;
            }
        }

        $this->info("Atualizadas: {$updated} | Sem match: {$notFound}");

        if ($dryRun) {
            $this->warn('Dry-run: nenhuma alteração gravada.');
        }

        return 0;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (empty($phone)) return null;
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) >= 10 && !str_starts_with($digits, '55')) {
            $digits = '55' . $digits;
        }
        return strlen($digits) >= 12 ? $digits : null;
    }
}
