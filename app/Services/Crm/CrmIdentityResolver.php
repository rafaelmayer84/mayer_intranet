<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmIdentityResolver
{
    /**
     * Resolve ou cria um CrmAccount a partir de identifiers disponíveis.
     *
     * Prioridade: doc > email > phone.
     * Se encontrar match com DataJuri cache (tabela clientes), promove a client.
     */
    public function resolve(
        ?string $phone = null,
        ?string $email = null,
        ?string $doc = null,
        array $defaults = []
    ): CrmAccount {
        $phoneNorm = $this->normalizePhone($phone);
        $emailNorm = $this->normalizeEmail($email);
        $docNorm   = $this->normalizeDoc($doc);

        // 1. Tentar encontrar por identities existentes (doc > email > phone)
        $account = $this->findByIdentity('doc', $docNorm)
                ?? $this->findByIdentity('email', $emailNorm)
                ?? $this->findByIdentity('phone', $phoneNorm);

        if ($account) {
            $this->ensureIdentities($account, $phoneNorm, $emailNorm, $docNorm);
            return $account;
        }

        // 2. Tentar match com DataJuri cache (tabela clientes)
        $djCache = $this->matchDataJuriCache($docNorm, $emailNorm, $phoneNorm);

        if ($djCache) {
            return $this->createFromDataJuri($djCache, $phoneNorm, $emailNorm, $docNorm, $defaults);
        }

        // 3. Criar prospect novo
        return $this->createProspect($phoneNorm, $emailNorm, $docNorm, $defaults);
    }

    /**
     * Busca CrmAccount por identity normalizada.
     */
    private function findByIdentity(string $kind, ?string $valueNorm): ?CrmAccount
    {
        if (empty($valueNorm)) return null;

        $identity = CrmIdentity::where('kind', $kind)
            ->where('value_norm', $valueNorm)
            ->first();

        return $identity ? $identity->account : null;
    }

    /**
     * Busca match na tabela clientes (cache DataJuri) por doc/email/phone.
     */
    private function matchDataJuriCache(?string $docNorm, ?string $emailNorm, ?string $phoneNorm): ?object
    {
        // Busca por CPF/CNPJ (digits only)
        if ($docNorm) {
            $match = DB::table('clientes')
                ->whereRaw("REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), '/', '') = ?", [$docNorm])
                ->first();
            if (!$match && strlen($docNorm) > 11) {
                $match = DB::table('clientes')
                    ->whereRaw("REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '-', ''), '/', '') = ?", [$docNorm])
                    ->first();
            }
            if ($match) return $match;
        }

        // Busca por email
        if ($emailNorm) {
            $match = DB::table('clientes')
                ->whereRaw('LOWER(email) = ?', [$emailNorm])
                ->first();
            if ($match) return $match;
        }

        // Busca por telefone
        if ($phoneNorm) {
            $match = DB::table('clientes')
                ->where(function ($q) use ($phoneNorm) {
                    // Tenta match exato ou pelos últimos 11 dígitos
                    $q->where('telefone', $phoneNorm)
                      ->orWhere('telefone', 'LIKE', '%' . substr($phoneNorm, -11));
                })
                ->first();
            if ($match) return $match;
        }

        return null;
    }

    /**
     * Cria CrmAccount vinculado ao DataJuri cache.
     */
    private function createFromDataJuri(
        object $djCache,
        ?string $phoneNorm,
        ?string $emailNorm,
        ?string $docNorm,
        array $defaults
    ): CrmAccount {
        return DB::transaction(function () use ($djCache, $phoneNorm, $emailNorm, $docNorm, $defaults) {
            $account = CrmAccount::create([
                'datajuri_pessoa_id' => $djCache->datajuri_id ?? null,
                'kind'               => 'client',
                'name'               => $djCache->nome ?? $defaults['name'] ?? 'Sem nome',
                'doc_digits'         => $docNorm ?: $this->extractDocDigits($djCache),
                'email'              => $emailNorm ?: (isset($djCache->email) ? strtolower(trim($djCache->email)) : null),
                'phone_e164'         => $phoneNorm ?: ($djCache->telefone ?? null),
                'owner_user_id'      => $defaults['owner_user_id'] ?? null,
                'lifecycle'          => 'ativo',
            ]);

            $this->ensureIdentities($account, $phoneNorm, $emailNorm, $docNorm);

            // Identity DataJuri
            if ($djCache->datajuri_id ?? null) {
                CrmIdentity::firstOrCreate(
                    ['kind' => 'datajuri', 'value_norm' => (string) $djCache->datajuri_id],
                    ['account_id' => $account->id, 'value' => (string) $djCache->datajuri_id]
                );
            }

            Log::info("[CRM] Account #{$account->id} criado via DataJuri match (pessoa_id={$djCache->datajuri_id})");
            return $account;
        });
    }

    /**
     * Cria prospect sem vínculo DataJuri.
     */
    private function createProspect(
        ?string $phoneNorm,
        ?string $emailNorm,
        ?string $docNorm,
        array $defaults
    ): CrmAccount {
        return DB::transaction(function () use ($phoneNorm, $emailNorm, $docNorm, $defaults) {
            $name = $defaults['name'] ?? 'Prospect';

            $account = CrmAccount::create([
                'datajuri_pessoa_id' => null,
                'kind'               => 'prospect',
                'name'               => $name,
                'doc_digits'         => $docNorm,
                'email'              => $emailNorm,
                'phone_e164'         => $phoneNorm,
                'owner_user_id'      => $defaults['owner_user_id'] ?? null,
                'lifecycle'          => 'onboarding',
            ]);

            $this->ensureIdentities($account, $phoneNorm, $emailNorm, $docNorm);

            Log::info("[CRM] Prospect #{$account->id} criado (name={$name})");
            return $account;
        });
    }

    /**
     * Garante que todas as identities disponíveis estão registradas.
     */
    private function ensureIdentities(CrmAccount $account, ?string $phone, ?string $email, ?string $doc): void
    {
        if ($phone) {
            CrmIdentity::firstOrCreate(
                ['kind' => 'phone', 'value_norm' => $phone],
                ['account_id' => $account->id, 'value' => $phone]
            );
        }
        if ($email) {
            CrmIdentity::firstOrCreate(
                ['kind' => 'email', 'value_norm' => $email],
                ['account_id' => $account->id, 'value' => $email]
            );
        }
        if ($doc) {
            CrmIdentity::firstOrCreate(
                ['kind' => 'doc', 'value_norm' => $doc],
                ['account_id' => $account->id, 'value' => $doc]
            );
        }
    }

    // --- Normalizers ---

    public function normalizePhone(?string $phone): ?string
    {
        return \App\Helpers\PhoneHelper::normalize($phone);
    }

    public function normalizeEmail(?string $email): ?string
    {
        if (empty($email)) return null;
        $email = strtolower(trim($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function normalizeDoc(?string $doc): ?string
    {
        if (empty($doc)) return null;
        $digits = preg_replace('/\D/', '', $doc);
        if (strlen($digits) !== 11 && strlen($digits) !== 14) return null;
        return $digits;
    }

    private function extractDocDigits(object $djCache): ?string
    {
        $cpf = $djCache->cpf ?? null;
        $cnpj = $djCache->cnpj ?? null;
        $raw = $cpf ?: $cnpj;
        if (!$raw) return null;
        return preg_replace('/\D/', '', $raw) ?: null;
    }
}
