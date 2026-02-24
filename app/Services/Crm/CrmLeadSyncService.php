<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmEvent;
use App\Models\Crm\CrmIdentity;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmLeadSyncService
{
    /**
     * Mapeamento de status do lead → lifecycle do CRM account
     */
    private const STATUS_MAP = [
        'novo'       => 'onboarding',
        'contatado'  => 'ativo',
        'proposta'   => 'ativo',
        'negociacao' => 'ativo',
        'convertido' => 'ativo',
        'descartado' => 'arquivado',
        'arquivado'  => 'arquivado',
    ];

    /**
     * Sincroniza lead com CRM account.
     * Cria account se não existir, atualiza lifecycle se mudou.
     */
    public function syncLead(Lead $lead): ?CrmAccount
    {
        try {
            $account = $this->findOrCreateAccount($lead);

            if (!$account) {
                return null;
            }

            // Vincular lead ao account se ainda não vinculado
            if (!$lead->crm_account_id || $lead->crm_account_id !== $account->id) {
                $lead->crm_account_id = $account->id;
                $lead->save();
            }

            // Atualizar lifecycle do account conforme status do lead
            $newLifecycle = self::STATUS_MAP[$lead->status] ?? 'onboarding';
            if ($account->lifecycle !== $newLifecycle) {
                $oldLifecycle = $account->lifecycle;
                $account->lifecycle = $newLifecycle;
                $account->last_touch_at = now();
                $account->save();

                CrmEvent::create([
                    'account_id'         => $account->id,
                    'type'               => 'lead_status_changed',
                    'payload'            => [
                        'lead_id'    => $lead->id,
                        'lead_status' => $lead->status,
                        'from'       => $oldLifecycle,
                        'to'         => $newLifecycle,
                    ],
                    'happened_at'        => now(),
                    'created_by_user_id' => null,
                ]);

                Log::info('[CrmLeadSync] Lifecycle atualizado', [
                    'account_id' => $account->id,
                    'lead_id'    => $lead->id,
                    'from'       => $oldLifecycle,
                    'to'         => $newLifecycle,
                ]);
            }

            return $account;

        } catch (\Exception $e) {
            Log::error('[CrmLeadSync] Erro', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Encontra account existente por identidade (phone/email/doc) ou cria novo.
     */
    private function findOrCreateAccount(Lead $lead): ?CrmAccount
    {
        // 1. Se lead já tem crm_account_id válido
        if ($lead->crm_account_id) {
            $existing = CrmAccount::find($lead->crm_account_id);
            if ($existing) return $existing;
        }

        // 2. Buscar por telefone normalizado
        $phone = preg_replace('/\D/', '', $lead->telefone ?? '');
        if (strlen($phone) >= 10) {
            $phoneSuffix = substr($phone, -9);
            $identity = CrmIdentity::where('kind', 'phone')
                ->where('value_norm', 'LIKE', '%' . $phoneSuffix)
                ->first();
            if ($identity) {
                return CrmAccount::find($identity->account_id);
            }
        }

        // 3. Buscar por email
        if ($lead->email) {
            $identity = CrmIdentity::where('kind', 'email')
                ->where('value_norm', strtolower(trim($lead->email)))
                ->first();
            if ($identity) {
                return CrmAccount::find($identity->account_id);
            }
        }

        // 4. Criar novo account (prospect)
        $account = CrmAccount::create([
            'kind'          => 'prospect',
            'name'          => $lead->nome ?? 'Lead #' . $lead->id,
            'email'         => $lead->email,
            'phone_e164'    => $phone ? '+55' . $phone : null,
            'lifecycle'     => 'onboarding',
            'owner_user_id' => $this->assignOwnerRoundRobin(),
            'last_touch_at' => now(),
        ]);

        // Registrar identidades
        if ($phone) {
            CrmIdentity::create([
                'account_id' => $account->id,
                'kind'       => 'phone',
                'value'  => $lead->telefone,
                'value_norm' => $phone,
            ]);
        }
        if ($lead->email) {
            CrmIdentity::create([
                'account_id' => $account->id,
                'kind'       => 'email',
                'value'  => $lead->email,
                'value_norm' => strtolower(trim($lead->email)),
            ]);
        }

        CrmEvent::create([
            'account_id'  => $account->id,
            'type'        => 'account_created_from_lead',
            'payload'     => ['lead_id' => $lead->id, 'source' => $lead->origem ?? 'marketing'],
            'happened_at' => now(),
        ]);

        Log::info('[CrmLeadSync] Account criado', [
            'account_id' => $account->id,
            'lead_id'    => $lead->id,
            'name'       => $account->name,
        ]);

        return $account;
    }

    /**
     * Rodízio entre advogadas para distribuir prospects.
     * Alterna entre Anelise (7) e Franciéli (8).
     */
    private function assignOwnerRoundRobin(): int
    {
        $advogadas = [7, 8]; // Anelise, Franciéli

        // Conta quem tem menos prospects
        $counts = [];
        foreach ($advogadas as $id) {
            $counts[$id] = CrmAccount::where('owner_user_id', $id)
                ->where('kind', 'prospect')
                ->count();
        }

        // Retorna a com menos
        asort($counts);
        return array_key_first($counts);
    }

    /**
     * Converte prospect em client quando lead é convertido.
     */
    public function convertToClient(Lead $lead): ?CrmAccount
    {
        $account = $this->syncLead($lead);
        if (!$account) return null;

        if ($account->kind === 'prospect') {
            $account->kind = 'client';
            $account->lifecycle = 'onboarding';
            $account->save();

            CrmEvent::create([
                'account_id'  => $account->id,
                'type'        => 'prospect_converted',
                'payload'     => ['lead_id' => $lead->id],
                'happened_at' => now(),
            ]);

            Log::info('[CrmLeadSync] Prospect convertido para client', [
                'account_id' => $account->id,
                'lead_id'    => $lead->id,
            ]);
        }

        return $account;
    }
}
