<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EspoCrmService
{
    protected $baseUrl;
    protected $apiKey;
    protected $enabled;

    public function __construct()
    {
        $this->enabled = config('espocrm.enabled', false);
        $this->baseUrl = config('espocrm.base_url');
        $this->apiKey = config('espocrm.api_key');
    }

    public function testConnection()
    {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'ESPOCRM desabilitado na configuração'];
        }

        if (!$this->baseUrl || !$this->apiKey) {
            return ['success' => false, 'message' => 'URL ou API Key não configurados'];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Api-Key' => $this->apiKey])
                ->get($this->baseUrl . '/api/v1/App/user');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Conexão estabelecida com sucesso',
                    'user' => $response->json()
                ];
            }

            return ['success' => false, 'message' => 'Erro na autenticação: ' . $response->status()];
        } catch (\Exception $e) {
            Log::error('ESPOCRM connection test failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erro de conexão: ' . $e->getMessage()];
        }
    }

    public function syncContacts()
    {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'ESPOCRM desabilitado', 'synced' => 0];
        }

        try {
            $offset = 0;
            $limit = 200;
            $totalSynced = 0;

            while (true) {
                $response = Http::timeout(30)
                    ->withHeaders(['X-Api-Key' => $this->apiKey])
                    ->get($this->baseUrl . '/api/v1/Contact', ['offset' => $offset, 'maxSize' => $limit]);

                if (!$response->successful()) break;

                $data = $response->json();
                $contacts = $data['list'] ?? [];

                if (empty($contacts)) break;

                foreach ($contacts as $contact) {
                    $hash = md5(json_encode($contact));

                    DB::table('espocrm_contacts')->updateOrInsert(
                        ['espocrm_id' => $contact['id']],
                        [
                            'name' => $contact['name'] ?? null,
                            'email' => $contact['emailAddress'] ?? null,
                            'phone' => $contact['phoneNumber'] ?? null,
                            'account_id' => $contact['accountId'] ?? null,
                            'description' => $contact['description'] ?? null,
                            'raw_data' => json_encode($contact),
                            'origem' => 'espocrm',
                            'hash' => $hash,
                            'last_sync_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    $totalSynced++;
                }

                if (count($contacts) < $limit) break;
                $offset += $limit;
            }

            return ['success' => true, 'message' => "Sincronizados {$totalSynced} contatos", 'synced' => $totalSynced];
        } catch (\Exception $e) {
            Log::error('ESPOCRM sync contacts failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage(), 'synced' => 0];
        }
    }

    public function syncAccounts()
    {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'ESPOCRM desabilitado', 'synced' => 0];
        }

        try {
            $offset = 0;
            $limit = 200;
            $totalSynced = 0;

            while (true) {
                $response = Http::timeout(30)
                    ->withHeaders(['X-Api-Key' => $this->apiKey])
                    ->get($this->baseUrl . '/api/v1/Account', ['offset' => $offset, 'maxSize' => $limit]);

                if (!$response->successful()) break;

                $data = $response->json();
                $accounts = $data['list'] ?? [];

                if (empty($accounts)) break;

                foreach ($accounts as $account) {
                    $hash = md5(json_encode($account));

                    DB::table('espocrm_accounts')->updateOrInsert(
                        ['espocrm_id' => $account['id']],
                        [
                            'name' => $account['name'] ?? null,
                            'type' => $account['type'] ?? null,
                            'industry' => $account['industry'] ?? null,
                            'website' => $account['website'] ?? null,
                            'description' => $account['description'] ?? null,
                            'raw_data' => json_encode($account),
                            'origem' => 'espocrm',
                            'hash' => $hash,
                            'last_sync_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    $totalSynced++;
                }

                if (count($accounts) < $limit) break;
                $offset += $limit;
            }

            return ['success' => true, 'message' => "Sincronizados {$totalSynced} accounts", 'synced' => $totalSynced];
        } catch (\Exception $e) {
            Log::error('ESPOCRM sync accounts failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage(), 'synced' => 0];
        }
    }

    public function syncLeads()
    {
        if (!$this->enabled) {
            return ['success' => false, 'message' => 'ESPOCRM desabilitado', 'synced' => 0];
        }

        try {
            $offset = 0;
            $limit = 200;
            $totalSynced = 0;

            while (true) {
                $response = Http::timeout(30)
                    ->withHeaders(['X-Api-Key' => $this->apiKey])
                    ->get($this->baseUrl . '/api/v1/Lead', ['offset' => $offset, 'maxSize' => $limit]);

                if (!$response->successful()) break;

                $data = $response->json();
                $leads = $data['list'] ?? [];

                if (empty($leads)) break;

                foreach ($leads as $lead) {
                    $hash = md5(json_encode($lead));

                    DB::table('espocrm_leads')->updateOrInsert(
                        ['espocrm_id' => $lead['id']],
                        [
                            'name' => $lead['name'] ?? null,
                            'status' => $lead['status'] ?? null,
                            'source' => $lead['source'] ?? null,
                            'email' => $lead['emailAddress'] ?? null,
                            'phone' => $lead['phoneNumber'] ?? null,
                            'description' => $lead['description'] ?? null,
                            'raw_data' => json_encode($lead),
                            'origem' => 'espocrm',
                            'hash' => $hash,
                            'last_sync_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    $totalSynced++;
                }

                if (count($leads) < $limit) break;
                $offset += $limit;
            }

            return ['success' => true, 'message' => "Sincronizados {$totalSynced} leads", 'synced' => $totalSynced];
        } catch (\Exception $e) {
            Log::error('ESPOCRM sync leads failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Erro: ' . $e->getMessage(), 'synced' => 0];
        }
    }

    public function syncAll()
    {
        $contacts = $this->syncContacts();
        $accounts = $this->syncAccounts();
        $leads = $this->syncLeads();

        $totalSynced = ($contacts['synced'] ?? 0) + ($accounts['synced'] ?? 0) + ($leads['synced'] ?? 0);

        return [
            'success' => true,
            'message' => "Sincronização ESPOCRM concluída: {$totalSynced} registros",
            'totalSynced' => $totalSynced,
            'details' => ['contacts' => $contacts, 'accounts' => $accounts, 'leads' => $leads]
        ];
    }
}
