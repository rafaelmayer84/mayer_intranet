<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Fonte única da verdade para classificação de uma conta CRM.
 *
 * Unifica as regras que antes estavam duplicadas em:
 *   - CrmReclassificarLifecycle (command)
 *   - CrmCarteiraService::calcularLifecycle
 *   - CrmAccountFlagsService::calcular
 *
 * Preserva exatamente o comportamento anterior (validado por baseline
 * em storage/app/baseline-refactor/).
 */
class CrmAccountState
{
    /**
     * Calcula lifecycle + motivo humanizado. Retorna [lifecycle, motivo].
     *
     * $acc precisa conter: id, name, kind, lifecycle, datajuri_pessoa_id,
     *   last_touch_at, created_at
     * $djCache (opcional) precisa conter: status_pessoa, is_cliente.
     *   Se null, é carregado de `clientes` pelo datajuri_pessoa_id.
     */
    public static function computeLifecycle(object $acc, ?object $djCache = null): array
    {
        $djId = $acc->datajuri_pessoa_id ?? null;

        if ($djCache === null && $djId) {
            $djCache = DB::table('clientes')
                ->where('datajuri_id', $djId)
                ->first(['status_pessoa', 'is_cliente']);
        }
        $statusDj = $djCache->status_pessoa ?? null;

        // Regra 1: DJ autoritativo — adversa/contraparte/fornecedor (prioridade máxima)
        if ($statusDj && preg_match('/Adversa|Contraparte|Fornecedor/i', $statusDj)) {
            return ['bloqueado_adversa', "DJ: {$statusDj}"];
        }

        // Sinal de cliente: cache DJ, is_cliente flag, ou figura como cliente em processos.
        // contas_receber sozinho NÃO qualifica — pode ser honorário sucumbencial contra adversa.
        $ehClienteCache = $statusDj && stripos($statusDj, 'Cliente') !== false;
        $ehClienteFlag  = ($djCache->is_cliente ?? 0) == 1;
        $ehClienteProc  = $djId
            ? DB::table('processos')->where('cliente_datajuri_id', $djId)->exists()
            : false;
        $ehCliente = $ehClienteCache || $ehClienteFlag || $ehClienteProc;

        // Regra 2: se NÃO é cliente, heurística adversa ANTES de inadimplência
        if (!$ehCliente) {
            if ($djId) {
                $ehAdv = DB::table('processos')->where('adverso_datajuri_id', $djId)->exists();
                if ($ehAdv) {
                    return ['bloqueado_adversa', 'adverso em processos (não é cliente)'];
                }
            }
            if (!$djId && !empty($acc->name) && mb_strlen(trim($acc->name)) >= 10) {
                $nomeNorm = trim(mb_strtolower($acc->name));
                $ehAdv = DB::table('processos')
                    ->whereRaw('LOWER(adverso_nome) = ?', [$nomeNorm])
                    ->exists();
                if ($ehAdv) {
                    return ['bloqueado_adversa', 'nome = adverso_nome em processos (sem DJ)'];
                }
            }
        }

        // Regra 3: inadimplência — só se É cliente nosso
        if ($ehCliente && $djId) {
            $temContasProtestoJudicial = DB::table('contas_receber')
                ->where('pessoa_datajuri_id', $djId)
                ->where('is_stale', false)
                ->whereNull('data_vencimento')
                ->whereNotIn('status', ['Excluido', 'Excluído', 'Concluído', 'Concluido'])
                ->where('valor', '>', 0)
                ->exists();
            if ($temContasProtestoJudicial) {
                return ['inadimplente', 'contas_receber c/ data_venc NULL'];
            }
        }
        if ($statusDj && stripos($statusDj, 'Inadimplente') !== false) {
            return ['inadimplente', "DJ: {$statusDj}"];
        }

        // Regra 4: sem DJ e sem atividade 6m+ → arquivado_orfao
        if (!$djId) {
            $seisMeses = Carbon::now()->subMonths(6);
            $lastTouch = !empty($acc->last_touch_at) ? Carbon::parse($acc->last_touch_at) : null;
            $criado = !empty($acc->created_at) ? Carbon::parse($acc->created_at) : Carbon::now();

            $inatividade = (!$lastTouch || $lastTouch->lt($seisMeses)) && $criado->lt($seisMeses);
            if ($inatividade) {
                return ['arquivado_orfao', 'sem DJ e sem atividade 6m+'];
            }
        }

        // Regras 4-6: espelhar DJ (Inativo ANTES de Ativo — substring)
        if ($statusDj) {
            if (stripos($statusDj, 'Inativo') !== false) {
                return ['adormecido', "DJ: {$statusDj}"];
            }
            if (stripos($statusDj, 'Onboarding') !== false) {
                return ['onboarding', "DJ: {$statusDj}"];
            }
            if (stripos($statusDj, 'Estratégico') !== false || stripos($statusDj, 'Estrategico') !== false) {
                return ['ativo', "DJ: {$statusDj}"];
            }
            if (stripos($statusDj, 'Ativo') !== false) {
                return ['ativo', "DJ: {$statusDj}"];
            }
        }

        // Regra 7: fallback
        if (!$djId) {
            return ['arquivado', 'sem DJ'];
        }
        return ['arquivado', 'sem status DJ classificável'];
    }

    /**
     * Versão simplificada quando só o valor interessa.
     */
    public static function lifecycleFor(int $accountId): string
    {
        $acc = DB::table('crm_accounts')
            ->where('id', $accountId)
            ->first(['id', 'name', 'kind', 'lifecycle', 'datajuri_pessoa_id', 'last_touch_at', 'created_at']);
        if (!$acc) return 'arquivado';
        [$lifecycle, ] = self::computeLifecycle($acc);
        return $lifecycle;
    }

    /**
     * Calcula as flags (marcas) da conta. Delega para CrmAccountFlagsService
     * para preservar comportamento exato. Fica aqui como fachada única.
     */
    public static function flagsFor(int $accountId): array
    {
        return app(CrmAccountFlagsService::class)->calcular($accountId);
    }

    /**
     * Retorna gates ativos da conta (aberto/em_revisao/escalado, não resolvidos).
     */
    public static function activeGatesFor(int $accountId): \Illuminate\Support\Collection
    {
        return DB::table('crm_account_data_gates')
            ->where('account_id', $accountId)
            ->whereIn('status', ['aberto', 'em_revisao', 'escalado'])
            ->get();
    }

    /**
     * Snapshot completo — útil para CrmAccountShowService e Painel do Dono.
     */
    public static function snapshot(int $accountId): array
    {
        $acc = DB::table('crm_accounts')->where('id', $accountId)->first();
        if (!$acc) return [];

        [$lifecycle, $motivo] = self::computeLifecycle($acc);

        return [
            'account_id'          => $accountId,
            'lifecycle_atual'     => $acc->lifecycle,
            'lifecycle_calculado' => $lifecycle,
            'lifecycle_motivo'    => $motivo,
            'lifecycle_divergente' => $lifecycle !== $acc->lifecycle,
            'flags'               => self::flagsFor($accountId),
            'gates_ativos'        => self::activeGatesFor($accountId),
        ];
    }
}
