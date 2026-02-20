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

    private function calcularLifecycle(object $acc, ?int $djPessoaId): string
    {
        if (!$djPessoaId) {
            return $acc->kind === 'prospect' ? 'onboarding' : 'adormecido';
        }

        // Processo ativo?
        $processosAtivos = DB::table('processos')
            ->where('cliente_datajuri_id', $djPessoaId)
            ->where('status', 'Ativo')
            ->count();

        // Contrato recente (assinado nos últimos 24 meses)?
        $contratosRecentes = DB::table('contratos')
            ->where('contratante_id_datajuri', $djPessoaId)
            ->where(function ($q) {
                $q->whereNull('data_assinatura')
                  ->orWhere('data_assinatura', '>=', now()->subMonths(24)->toDateString());
            })
            ->count();

        if ($processosAtivos > 0 || $contratosRecentes > 0) {
            return 'ativo';
        }

        // Movimento financeiro no último ano?
        $ultimoMov = DB::table('movimentos')
            ->where('pessoa_id_datajuri', $djPessoaId)
            ->where('valor', '>', 0)
            ->max('data');

        if ($ultimoMov && $ultimoMov >= now()->subMonths(12)->toDateString()) {
            return 'adormecido';
        }

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
