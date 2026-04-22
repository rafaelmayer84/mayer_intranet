<?php

namespace App\Console\Commands;

use App\Models\Crm\CrmAccountDataGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Roda apos sync do DataJuri. Fecha gates cuja divergencia foi corrigida no DJ
 * e escala gates em revisao ha mais de 7 dias sem correcao.
 *
 * - aberto      : ignorado (aguarda usuario abrir a conta)
 * - em_revisao  : tenta resolver_auto; senao escala apos 7d de first_seen_by_owner_at
 * - escalado    : tenta resolver_auto (ainda da pra consertar — pararia de penalizar)
 */
class CrmGatesProcessar extends Command
{
    protected $signature = 'crm:gates-processar {--dry-run}';
    protected $description = 'Fecha gates resolvidos pelo DJ e escala gates em_revisao > 7 dias.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $this->line('=== CRM Gates Processar ' . ($dryRun ? '[DRY-RUN]' : '[LIVE]') . ' ===');

        $stats = ['resolvidos' => 0, 'escalados' => 0, 'mantidos' => 0];

        $gates = CrmAccountDataGate::whereIn('status', [
            CrmAccountDataGate::STATUS_EM_REVISAO,
            CrmAccountDataGate::STATUS_ESCALADO,
        ])->get();

        foreach ($gates as $gate) {
            if ($this->divergenciaResolvida($gate)) {
                $this->line("  ✓ gate #{$gate->id} ({$gate->tipo}) — RESOLVIDO");
                if (!$dryRun) {
                    $gate->update([
                        'status'                 => CrmAccountDataGate::STATUS_RESOLVIDO_AUTO,
                        'resolved_at'            => now(),
                        'dj_valor_no_fechamento' => $this->lerStatusDjAtual($gate),
                    ]);
                }
                $stats['resolvidos']++;
                continue;
            }

            if ($gate->status === CrmAccountDataGate::STATUS_EM_REVISAO
                && $gate->first_seen_by_owner_at
                && $gate->first_seen_by_owner_at->lt(now()->subDays(7))) {
                $this->line("  ✗ gate #{$gate->id} ({$gate->tipo}) — ESCALANDO (>7d sem correcao)");
                if (!$dryRun) {
                    $gate->update([
                        'status'       => CrmAccountDataGate::STATUS_ESCALADO,
                        'escalated_at' => now(),
                    ]);
                    $this->notificarEscalacao($gate);
                }
                $stats['escalados']++;
                continue;
            }

            $stats['mantidos']++;
        }

        $this->line('');
        $this->line("Resolvidos: {$stats['resolvidos']} | Escalados: {$stats['escalados']} | Mantidos: {$stats['mantidos']}");

        if (!$dryRun) {
            Log::info('[CrmGatesProcessar] LIVE', $stats);
        }

        return self::SUCCESS;
    }

    /**
     * Verifica se a divergencia que originou o gate deixou de existir.
     * Usa as mesmas regras do detector, invertidas.
     */
    private function divergenciaResolvida(CrmAccountDataGate $gate): bool
    {
        $account = DB::table('crm_accounts')->where('id', $gate->account_id)->first();
        if (!$account) return true;
        $djId = $account->datajuri_pessoa_id;
        $cliente = $djId ? DB::table('clientes')->where('datajuri_id', $djId)->first() : null;
        $statusDj = $cliente->status_pessoa ?? null;

        switch ($gate->tipo) {
            case CrmAccountDataGate::TIPO_ONBOARDING_COM_CONTRATO:
                // Resolvido se DJ deixou de ser Onboarding
                return !$statusDj || stripos($statusDj, 'Onboarding') === false;

            case CrmAccountDataGate::TIPO_STATUS_CLIENTE_SEM_VINCULO:
                // Resolvido se nao eh mais "Cliente" no DJ OU se passou a ter vinculo
                if (!$statusDj || stripos($statusDj, 'Cliente') === false) return true;
                $temContrato = $djId && DB::table('contratos')->where('contratante_id_datajuri', $djId)->exists();
                $temProcesso = $djId && DB::table('processos')->where('cliente_datajuri_id', $djId)->exists();
                return $temContrato || $temProcesso;

            case CrmAccountDataGate::TIPO_ADVERSA_COM_CONTRATO:
                // Resolvido se DJ deixou de marcar adversa/contraparte/fornecedor
                return !$statusDj || !preg_match('/Adversa|Contraparte|Fornecedor/i', $statusDj);

            case CrmAccountDataGate::TIPO_INADIMPLENCIA_SUSPEITA_2099:
                // Resolvido se (a) DJ agora marca Inadimplente OU (b) nao ha mais contas com 2099-12-31
                if ($statusDj && stripos($statusDj, 'Inadimplente') !== false) return true;
                if (!$djId) return true;
                $ainda = DB::table('contas_receber')
                    ->where('pessoa_datajuri_id', $djId)
                    ->where('is_stale', false)
                    ->whereDate('data_vencimento', '2099-12-31')
                    ->whereNotIn('status', ['Excluido', 'Excluído', 'Concluído', 'Concluido'])
                    ->where('valor', '>', 0)
                    ->exists();
                return !$ainda;

            case CrmAccountDataGate::TIPO_SEM_STATUS_PESSOA:
                return !empty($statusDj);
        }

        return false;
    }

    private function lerStatusDjAtual(CrmAccountDataGate $gate): ?string
    {
        $account = DB::table('crm_accounts')->where('id', $gate->account_id)->first();
        if (!$account || !$account->datajuri_pessoa_id) return null;
        return DB::table('clientes')
            ->where('datajuri_id', $account->datajuri_pessoa_id)
            ->value('status_pessoa');
    }

    private function notificarEscalacao(CrmAccountDataGate $gate): void
    {
        if (!$gate->owner_user_id) return;
        try {
            app(\App\Services\Crm\CrmGateNotifier::class)->notificarEscalacao($gate);
        } catch (\Throwable $e) {
            Log::warning('[CrmGatesProcessar] notificar escalacao falhou: ' . $e->getMessage());
        }
    }
}
