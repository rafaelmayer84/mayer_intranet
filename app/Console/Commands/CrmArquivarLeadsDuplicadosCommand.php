<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Cliente;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Arquiva leads ativos que já têm um cliente cadastrado com mesmo telefone ou crm_account.
 *
 * Regra de negócio: quando um lead vira cliente, o registro de lead deve ser
 * marcado como 'convertido' com o cliente_id preenchido.
 * Um cliente e um lead ativo para a mesma pessoa não podem coexistir.
 *
 * Uso:
 *   php artisan crm:arquivar-leads-duplicados --dry-run
 *   php artisan crm:arquivar-leads-duplicados
 */
class CrmArquivarLeadsDuplicadosCommand extends Command
{
    protected $signature = 'crm:arquivar-leads-duplicados {--dry-run : Simular sem gravar}';
    protected $description = 'Arquiva leads ativos que já têm cliente com mesmo telefone ou crm_account vinculado a cliente';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY-RUN] Nenhuma alteração será gravada.');
        }

        $converted = 0;

        // ── MÉTODO 1: Lead com crm_account_id que aponta para um client (com datajuri) ──
        $this->info('=== Método 1: lead.crm_account_id → crm_accounts (kind=client ou tem datajuri) ===');

        $leadsViaAccount = DB::table('leads')
            ->join('crm_accounts', 'leads.crm_account_id', '=', 'crm_accounts.id')
            ->whereNotNull('crm_accounts.datajuri_pessoa_id')
            ->whereNotIn('leads.status', ['arquivado', 'convertido'])
            ->whereNull('leads.cliente_id')
            ->select(
                'leads.id as lead_id',
                'leads.nome as lead_nome',
                'leads.status as lead_status',
                'leads.crm_account_id',
                'crm_accounts.datajuri_pessoa_id'
            )
            ->get();

        $this->line("  Encontrados: {$leadsViaAccount->count()}");

        foreach ($leadsViaAccount as $row) {
            // Tentar encontrar o cliente via datajuri_id
            $cliente = Cliente::where('datajuri_id', $row->datajuri_pessoa_id)->first();
            $clienteId = $cliente?->id;

            $this->line("  lead#{$row->lead_id} ({$row->lead_nome}) status={$row->lead_status} → convertido (cliente_id=" . ($clienteId ?? 'sem cliente') . ")");

            if (!$dryRun) {
                Lead::where('id', $row->lead_id)->update([
                    'status'     => 'convertido',
                    'cliente_id' => $clienteId,
                ]);
            }

            $converted++;
        }

        // ── MÉTODO 2: Lead com telefone igual ao de um cliente ──
        $this->info('');
        $this->info('=== Método 2: lead.telefone = clientes.telefone_normalizado ===');

        // Busca todos os leads ativos com telefone
        $leadsAtivos = Lead::whereNotIn('status', ['arquivado', 'convertido'])
            ->whereNull('cliente_id')
            ->whereNotNull('telefone')
            ->where('telefone', '!=', '')
            ->get();

        $found = 0;
        foreach ($leadsAtivos as $lead) {
            $norm = preg_replace('/\D/', '', $lead->telefone);
            if (!str_starts_with($norm, '55') && strlen($norm) >= 10 && strlen($norm) <= 11) {
                $norm = '55' . $norm;
            }
            if (strlen($norm) < 10) continue;

            // Buscar cliente pelo telefone normalizado (telefone ou celular)
            $cliente = Cliente::where('telefone_normalizado', $norm)->first();

            if (!$cliente) {
                // Fallback: normalizar celular e comparar
                $cliente = Cliente::whereNotNull('celular')
                    ->where('celular', '!=', '')
                    ->get()
                    ->first(function ($c) use ($norm) {
                        $cn = preg_replace('/\D/', '', $c->celular);
                        if (!str_starts_with($cn, '55') && strlen($cn) >= 10) {
                            $cn = '55' . $cn;
                        }
                        return $cn === $norm;
                    });
            }

            if ($cliente) {
                $this->line("  lead#{$lead->id} ({$lead->nome}) tel={$lead->telefone} → cliente#{$cliente->id} ({$cliente->nome})");

                if (!$dryRun) {
                    $lead->update([
                        'status'     => 'convertido',
                        'cliente_id' => $cliente->id,
                    ]);
                }

                $found++;
                $converted++;
            }
        }

        $this->line("  Encontrados: {$found}");

        $this->info('');
        $this->info("Total convertidos: {$converted}");

        if ($dryRun) {
            $this->warn('[DRY-RUN] Nenhuma alteração foi gravada.');
        }

        return 0;
    }
}
