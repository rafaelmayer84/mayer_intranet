<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Oportunidade;
use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EspoCrmSyncService
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('ESPOCRM_URL'), '/');
        $this->apiKey = env('ESPOCRM_API_KEY');
    }

    /**
     * Headers para requisições
     */
    private function getHeaders()
    {
        return [
            'X-Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json'
        ];
    }

    /**
     * Sincronizar todas as entidades
     */
    public function syncAll()
    {
        $results = [
            'accounts' => $this->syncAccounts(),
            'leads' => $this->syncLeads(),
            'opportunities' => $this->syncOpportunities(),
        ];

        return [
            'success' => true,
            'results' => $results
        ];
    }

    /**
     * Sincronizar Contas (Accounts) → Clientes
     */
    public function syncAccounts()
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/api/v1/Account", [
                    'maxSize' => 200
                ]);

            if (!$response->successful()) {
                throw new Exception('Falha ao buscar contas: ' . $response->body());
            }

            $data = $response->json();
            $accounts = $data['list'] ?? [];
            $imported = 0;
            $updated = 0;

            foreach ($accounts as $account) {
                $cliente = Cliente::updateOrCreate(
                    ['espocrm_id' => $account['id']],
                    [
                        'nome' => $account['name'] ?? '',
                        'email' => $account['emailAddress'] ?? null,
                        'telefone' => $account['phoneNumber'] ?? null,
                        'endereco' => $account['billingAddressStreet'] ?? null,
                        'tipo_pessoa' => $account['type'] === 'Customer' ? 'PJ' : 'PF',
                        'metadata' => json_encode($account)
                    ]
                );

                if ($cliente->wasRecentlyCreated) {
                    $imported++;
                } else {
                    $updated++;
                }
            }

            $this->logSuccess('ESPO CRM', 'Accounts', "Importados: {$imported}, Atualizados: {$updated}");

            return [
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'total' => count($accounts)
            ];

        } catch (Exception $e) {
            $this->logError('ESPO CRM', 'Accounts', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Sincronizar Leads
     */
    public function syncLeads()
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/api/v1/Lead", [
                    'maxSize' => 200
                ]);

            if (!$response->successful()) {
                throw new Exception('Falha ao buscar leads: ' . $response->body());
            }

            $data = $response->json();
            $leads = $data['list'] ?? [];
            $imported = 0;
            $updated = 0;

            foreach ($leads as $leadData) {
                // Buscar ou criar cliente
                $cliente = null;
                if (!empty($leadData['accountId'])) {
                    $cliente = Cliente::where('espocrm_id', $leadData['accountId'])->first();
                }

                $lead = Lead::updateOrCreate(
                    ['espocrm_id' => $leadData['id']],
                    [
                        'cliente_id' => $cliente?->id,
                        'nome' => ($leadData['firstName'] ?? '') . ' ' . ($leadData['lastName'] ?? ''),
                        'email' => $leadData['emailAddress'] ?? null,
                        'telefone' => $leadData['phoneNumber'] ?? null,
                        'origem' => $leadData['source'] ?? 'Desconhecido',
                        'status' => $leadData['status'] ?? 'Novo',
                        'metadata' => json_encode($leadData)
                    ]
                );

                if ($lead->wasRecentlyCreated) {
                    $imported++;
                } else {
                    $updated++;
                }
            }

            $this->logSuccess('ESPO CRM', 'Leads', "Importados: {$imported}, Atualizados: {$updated}");

            return [
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'total' => count($leads)
            ];

        } catch (Exception $e) {
            $this->logError('ESPO CRM', 'Leads', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Sincronizar Oportunidades
     */
    public function syncOpportunities()
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get("{$this->baseUrl}/api/v1/Opportunity", [
                    'maxSize' => 200
                ]);

            if (!$response->successful()) {
                throw new Exception('Falha ao buscar oportunidades: ' . $response->body());
            }

            $data = $response->json();
            $opportunities = $data['list'] ?? [];
            $imported = 0;
            $updated = 0;

            foreach ($opportunities as $opp) {
                // Buscar cliente e lead relacionados
                $cliente = null;
                $lead = null;

                if (!empty($opp['accountId'])) {
                    $cliente = Cliente::where('espocrm_id', $opp['accountId'])->first();
                }

                if (!empty($opp['leadId'])) {
                    $lead = Lead::where('espocrm_id', $opp['leadId'])->first();
                }

                $oportunidade = Oportunidade::updateOrCreate(
                    ['espocrm_id' => $opp['id']],
                    [
                        'cliente_id' => $cliente?->id,
                        'lead_id' => $lead?->id,
                        'nome' => $opp['name'] ?? '',
                        'estagio' => $opp['stage'] ?? 'Prospectando',
                        'valor' => $opp['amount'] ?? 0,
                        'probabilidade' => $opp['probability'] ?? 0,
                        'tipo' => $opp['type'] ?? 'Novo Negócio',
                        'data_criacao' => $opp['createdAt'] ?? now(),
                        'data_fechamento' => $opp['expectedCloseDate'] ?? null,
                        'observacoes' => $opp['description'] ?? null,
                        'metadata' => json_encode($opp)
                    ]
                );

                if ($oportunidade->wasRecentlyCreated) {
                    $imported++;
                } else {
                    $updated++;
                }
            }

            $this->logSuccess('ESPO CRM', 'Oportunidades', "Importados: {$imported}, Atualizados: {$updated}");

            return [
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'total' => count($opportunities)
            ];

        } catch (Exception $e) {
            $this->logError('ESPO CRM', 'Oportunidades', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Registrar log de sucesso
     */
    private function logSuccess($sistema, $tipo, $mensagem)
    {
        IntegrationLog::create([
            'sistema' => $sistema,
            'tipo' => $tipo,
            'status' => 'sucesso',
            'mensagem' => $mensagem
        ]);

        Log::info("[{$sistema}] {$tipo}: {$mensagem}");
    }

    /**
     * Registrar log de erro
     */
    private function logError($sistema, $tipo, $mensagem)
    {
        IntegrationLog::create([
            'sistema' => $sistema,
            'tipo' => $tipo,
            'status' => 'erro',
            'mensagem' => $mensagem
        ]);

        Log::error("[{$sistema}] {$tipo}: {$mensagem}");
    }
}
