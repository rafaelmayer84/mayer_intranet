<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmCarteiraService
{
    /**
     * Mapeamento proprietario_id DataJuri => users.id Intranet
     * Advogados sem user na intranet ficam com owner_user_id = null
     */
    private const DJ_TO_USER = [
        1175  => 1,  // Rafael Mayer
        21412 => 3,  // Patrícia Silveira Martins
        21647 => 7,  // Anelise Muller
        21644 => 8,  // Franciéli Vasconcellos Nogueira
    ];

    /**
     * Recalcula toda a carteira: owner, lifecycle, last_touch_at.
     * Chamado pelo cron após sync DataJuri.
     */
    public function recalcularCarteira(): array
    {
        $inicio = microtime(true);
        $stats = [
            'owner_updated' => 0,
            'lifecycle' => ['ativo' => 0, 'adormecido' => 0, 'arquivado' => 0, 'onboarding' => 0],
            'touch_updated' => 0,
            'total' => 0,
            'lifecycle_changes' => [],
        ];

        $accounts = DB::table('crm_accounts')->get();
        $stats['total'] = $accounts->count();

        foreach ($accounts as $acc) {
            $djPessoaId = $acc->datajuri_pessoa_id;

            // --- Owner ---
            $newOwner = $acc->owner_user_id;
            if ($djPessoaId) {
                $cliente = DB::table('clientes')
                    ->where('datajuri_id', $djPessoaId)
                    ->select('proprietario_id', 'telefone', 'celular')
                    ->first();

                if ($cliente && $cliente->proprietario_id) {
                    $mapped = self::DJ_TO_USER[$cliente->proprietario_id] ?? null;
                    if ($mapped !== $acc->owner_user_id) {
                        $newOwner = $mapped;
                        $stats['owner_updated']++;
                    }
                }
            }

            // --- Lifecycle ---
            $newLifecycle = $this->calcularLifecycle($acc, $djPessoaId);
            $stats['lifecycle'][$newLifecycle]++;

            // --- Last Touch ---
            $newLastTouch = $djPessoaId
                ? $this->calcularLastTouch($djPessoaId, $cliente ?? null)
                : $acc->last_touch_at;

            // --- Update se mudou algo ---
            $updates = [];
            if ($newOwner !== $acc->owner_user_id) {
                $updates['owner_user_id'] = $newOwner;
            }
            if ($newLifecycle !== $acc->lifecycle) {
                $updates['lifecycle'] = $newLifecycle;
                $stats['lifecycle_changes'][] = [
                    'account_id'    => $acc->id,
                    'name'          => $acc->name,
                    'from'          => $acc->lifecycle,
                    'to'            => $newLifecycle,
                    'owner_user_id' => $newOwner ?? $acc->owner_user_id,
                ];
            }
            if ($newLastTouch && $newLastTouch !== $acc->last_touch_at) {
                $updates['last_touch_at'] = $newLastTouch;
                $stats['touch_updated']++;
            }

            if (!empty($updates)) {
                $updates['updated_at'] = now();
                DB::table('crm_accounts')->where('id', $acc->id)->update($updates);
            }
        }

        $stats['tempo_s'] = round(microtime(true) - $inicio, 2);

        Log::info('[CrmCarteira] Recálculo concluído', $stats);

        return $stats;
    }

    /**
     * Lifecycle segue a mesma lógica do comando crm:reclassificar-lifecycle.
     * Precedência:
     *   1) DJ explicitamente marca Adversa/Contraparte/Fornecedor -> bloqueado_adversa
     *   2) contas_receber c/ data_vencimento NULL (protesto/judicial) OU DJ Inadimplente -> inadimplente
     *   3) heurística: DJ-id bate com adverso em processos (se DJ não disse que é cliente) -> bloqueado_adversa
     *   4) espelha status_pessoa do DJ: Inativo/Onboarding/Ativo/Estratégico
     *   5) fallback: arquivado
     */
    private function calcularLifecycle(object $acc, ?int $djPessoaId): string
    {
        $cache = $djPessoaId
            ? DB::table('clientes')->where('datajuri_id', $djPessoaId)->first(['status_pessoa','is_cliente'])
            : null;
        $statusDj = $cache->status_pessoa ?? null;

        // 1) DJ autoritativo: adversa/contraparte/fornecedor
        if ($statusDj && preg_match('/Adversa|Contraparte|Fornecedor/i', $statusDj)) {
            return 'bloqueado_adversa';
        }

        // Sinal de cliente: cache DJ diz "Cliente", is_cliente=1 OU figura como cliente_datajuri_id em processos.
        // contas_receber sozinho NÃO qualifica — pode ser honorário sucumbencial contra adversa.
        $ehClienteCache = $statusDj && stripos($statusDj, 'Cliente') !== false;
        $ehClienteFlag  = ($cache->is_cliente ?? 0) == 1;
        $ehClienteProc  = $djPessoaId
            ? DB::table('processos')->where('cliente_datajuri_id', $djPessoaId)->exists()
            : false;
        $ehCliente = $ehClienteCache || $ehClienteFlag || $ehClienteProc;

        // 2) se NÃO é cliente, checar heurística adversa ANTES de inadimplência
        if (!$ehCliente) {
            if ($djPessoaId) {
                $ehAdv = DB::table('processos')->where('adverso_datajuri_id', $djPessoaId)->exists();
                if ($ehAdv) return 'bloqueado_adversa';
            }
            if (!$djPessoaId && !empty($acc->name) && mb_strlen(trim($acc->name)) >= 10) {
                $ehAdv = DB::table('processos')
                    ->whereRaw('LOWER(adverso_nome) = ?', [trim(mb_strtolower($acc->name))])
                    ->exists();
                if ($ehAdv) return 'bloqueado_adversa';
            }
        }

        // 3) inadimplência — só se é cliente nosso
        if ($ehCliente && $djPessoaId) {
            $protesto = DB::table('contas_receber')
                ->where('pessoa_datajuri_id', $djPessoaId)
                ->where('is_stale', false)
                ->whereNull('data_vencimento')
                ->whereNotIn('status', ['Excluido','Excluído','Concluído','Concluido'])
                ->where('valor', '>', 0)
                ->exists();
            if ($protesto) return 'inadimplente';
        }
        if ($statusDj && stripos($statusDj, 'Inadimplente') !== false) {
            return 'inadimplente';
        }

        // 4) espelhar DJ (Inativo ANTES de Ativo — substring)
        if ($statusDj) {
            if (stripos($statusDj, 'Inativo') !== false)    return 'adormecido';
            if (stripos($statusDj, 'Onboarding') !== false) return 'onboarding';
            if (stripos($statusDj, 'Estratégico') !== false || stripos($statusDj, 'Estrategico') !== false) return 'ativo';
            if (stripos($statusDj, 'Ativo') !== false)      return 'ativo';
        }

        // 5) fallback
        return 'arquivado';
    }

    private function calcularLastTouch(?int $djPessoaId, ?object $cliente): ?string
    {
        if (!$djPessoaId) return null;

        $dates = [];

        // Último movimento financeiro
        $d1 = DB::table('movimentos')->where('pessoa_id_datajuri', $djPessoaId)->max('data');
        if ($d1) $dates[] = $d1;

        // Última atualização de processo
        $d2 = DB::table('processos')->where('cliente_datajuri_id', $djPessoaId)->max('updated_at');
        if ($d2) $dates[] = $d2;

        // Última interação WhatsApp
        if ($cliente) {
            $phones = array_filter([$cliente->telefone ?? null, $cliente->celular ?? null]);
            foreach ($phones as $ph) {
                $digits = preg_replace('/\D/', '', $ph);
                if (strlen($digits) >= 9) {
                    $d3 = DB::table('wa_conversations')
                        ->where('phone', 'LIKE', '%' . substr($digits, -9))
                        ->max('last_message_at');
                    if ($d3) $dates[] = $d3;
                }
            }
        }

        return !empty($dates) ? max($dates) : null;
    }

    /**
     * Retorna mapeamento DJ proprietario_id => user_id
     */
    public static function getDjToUserMap(): array
    {
        return self::DJ_TO_USER;
    }
}
