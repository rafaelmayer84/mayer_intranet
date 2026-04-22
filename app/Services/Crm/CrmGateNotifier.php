<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmAccountDataGate;
use App\Models\NotificationIntranet;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CrmGateNotifier
{
    public function notificarAbertura(CrmAccountDataGate $gate): void
    {
        if (!$gate->owner_user_id) return;
        $account = CrmAccount::find($gate->account_id);
        if (!$account) return;

        NotificationIntranet::enviar(
            $gate->owner_user_id,
            'Pendência CRM: ' . mb_substr($account->name, 0, 40),
            'Há divergência entre DataJuri e dados locais nesta conta. Revisão obrigatória.',
            route('crm.accounts.show', $account->id),
            'warning',
            'shield-exclamation'
        );
    }

    public function notificarEscalacao(CrmAccountDataGate $gate): void
    {
        if (!$gate->owner_user_id) return;
        $account = CrmAccount::find($gate->account_id);
        if (!$account) return;

        NotificationIntranet::enviar(
            $gate->owner_user_id,
            'ESCALADO: Pendência CRM não resolvida',
            "A divergência em {$account->name} não foi corrigida no DJ em 7 dias. Vai gerar penalidade GDP (PEN-C01, 3 pts, eixo Atendimento).",
            route('crm.accounts.show', $account->id),
            'danger',
            'exclamation-triangle'
        );

        // Email opcional para o owner
        $user = User::find($gate->owner_user_id);
        if ($user && $user->email) {
            try {
                Mail::raw(
                    "A pendência de qualidade de dados na conta {$account->name} não foi corrigida em 7 dias.\n\n" .
                    "Tipo: {$gate->tipo}\n" .
                    "DJ atual: " . ($gate->dj_valor_snapshot ?? '-') . "\n\n" .
                    "Abrir conta: " . route('crm.accounts.show', $account->id) . "\n\n" .
                    "Penalidade GDP (PEN-C01, 3 pts, eixo Atendimento) será registrada na próxima apuração.",
                    function ($m) use ($user, $account) {
                        $m->to($user->email)
                          ->subject('[CRM][ESCALADO] Pendência de dados: ' . $account->name);
                    }
                );
            } catch (\Throwable $e) {
                Log::warning('[CrmGateNotifier] email escalacao falhou: ' . $e->getMessage());
            }
        }
    }
}
