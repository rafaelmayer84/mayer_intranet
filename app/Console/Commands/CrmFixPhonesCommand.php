<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrige phones malformados em crm_accounts e crm_identities.
 *
 * Problemas detectados:
 * - phone_e164 com DDI duplicado sem +: 555XXXXXXXXX → 55XXXXXXXXX
 * - phone_e164 com DDI duplicado com +: +5555XXXXXXX → 55XXXXXXXXX
 * - value_norm em crm_identities com mesmo padrão
 *
 * Uso:
 *   php artisan crm:fix-phones --dry-run   (simula sem gravar)
 *   php artisan crm:fix-phones             (aplica correções)
 */
class CrmFixPhonesCommand extends Command
{
    protected $signature = 'crm:fix-phones {--dry-run : Simular sem gravar}';
    protected $description = 'Corrige phone_e164 malformados em crm_accounts e crm_identities (DDI duplicado)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY-RUN] Nenhuma alteração será gravada.');
        }

        $this->info('=== Fase 1: crm_accounts.phone_e164 ===');
        $this->fixCrmAccounts($dryRun);

        $this->info('');
        $this->info('=== Fase 2: crm_identities.value_norm (kind=phone) ===');
        $this->fixCrmIdentities($dryRun);

        $this->info('');
        $this->info('Concluído.');
        return 0;
    }

    /**
     * Normaliza phone_e164 em crm_accounts.
     * Regras (em ordem de aplicação):
     *  1. Remove + do início: +55... → 55...
     *  2. Remove DDI duplicado: 5555... → 55... (quando tem 14+ dígitos)
     */
    private function fixCrmAccounts(bool $dryRun): void
    {
        $accounts = DB::table('crm_accounts')
            ->whereNotNull('phone_e164')
            ->select('id', 'name', 'phone_e164')
            ->orderBy('id')
            ->get();

        $fixed = 0;
        $skipped = 0;

        foreach ($accounts as $acc) {
            $original = $acc->phone_e164;
            $normalized = $this->normalizePhone($original);

            if ($normalized === $original) {
                $skipped++;
                continue;
            }

            // Verificar se o número normalizado já existe em outro account
            $conflict = DB::table('crm_accounts')
                ->where('phone_e164', $normalized)
                ->where('id', '!=', $acc->id)
                ->first();

            if ($conflict) {
                $this->warn("  CONFLITO: account #{$acc->id} ({$acc->name}) | {$original} → {$normalized} já existe no account #{$conflict->id} ({$conflict->name}) — IGNORADO");
                continue;
            }

            $this->line("  #{$acc->id} | {$acc->name} | {$original} → {$normalized}");

            if (!$dryRun) {
                DB::table('crm_accounts')
                    ->where('id', $acc->id)
                    ->update(['phone_e164' => $normalized, 'updated_at' => now()]);
            }

            $fixed++;
        }

        $this->info("  Corrigidos: {$fixed} | Sem alteração: {$skipped}");
    }

    /**
     * Normaliza value e value_norm em crm_identities onde kind='phone'.
     */
    private function fixCrmIdentities(bool $dryRun): void
    {
        $identities = DB::table('crm_identities')
            ->where('kind', 'phone')
            ->select('id', 'account_id', 'value', 'value_norm')
            ->orderBy('id')
            ->get();

        $fixed = 0;
        $skipped = 0;

        foreach ($identities as $ident) {
            $origValue     = $ident->value;
            $origNorm      = $ident->value_norm;
            $newValue      = $this->normalizePhone($origValue);
            $newNorm       = $this->normalizePhone($origNorm);

            if ($newValue === $origValue && $newNorm === $origNorm) {
                $skipped++;
                continue;
            }

            // Verificar conflito de unicidade (kind + value_norm é unique)
            $conflict = DB::table('crm_identities')
                ->where('kind', 'phone')
                ->where('value_norm', $newNorm)
                ->where('id', '!=', $ident->id)
                ->first();

            if ($conflict) {
                $this->warn("  CONFLITO identity #{$ident->id} (account #{$ident->account_id}) | {$origNorm} → {$newNorm} já existe na identity #{$conflict->id} (account #{$conflict->account_id}) — IGNORADO");
                continue;
            }

            $this->line("  identity #{$ident->id} | account #{$ident->account_id} | {$origNorm} → {$newNorm}");

            if (!$dryRun) {
                DB::table('crm_identities')
                    ->where('id', $ident->id)
                    ->update([
                        'value'      => $newValue,
                        'value_norm' => $newNorm,
                        'updated_at' => now(),
                    ]);
            }

            $fixed++;
        }

        $this->info("  Corrigidos: {$fixed} | Sem alteração: {$skipped}");
    }

    /**
     * Remove + do início e DDI duplicado (5555...) de números brasileiros.
     *
     * Telefone BR válido: 55(DDI) + DDD(2 dígitos) + número(8-9 dígitos) = 12-13 dígitos.
     * DDI duplicado detectado quando há 14+ dígitos e começa com 5555:
     *   - 14+ dígitos: excede o máximo válido (13) → DDI duplicado
     *   - Itera removendo "55" enquanto ainda houver duplicação confirmada
     */
    private function normalizePhone(string $raw): string
    {
        // Remove qualquer caractere não-numérico (inclui + e espaços)
        $digits = preg_replace('/\D/', '', $raw);

        // Itera removendo DDI duplicado enquanto:
        //   a) tiver 14+ dígitos (acima do máximo válido), E
        //   b) começa com "55" (DDI presente), E
        //   c) o próximo segmento após remover também começa com "55" (confirmando duplicação)
        while (strlen($digits) >= 14 && str_starts_with($digits, '55')) {
            $candidate = substr($digits, 2);
            if (str_starts_with($candidate, '55')) {
                $digits = $candidate;
            } else {
                break; // próximo segmento não começa com 55 → não é DDI duplicado
            }
        }

        return $digits;
    }
}
