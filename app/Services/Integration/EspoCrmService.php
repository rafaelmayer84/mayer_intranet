<?php
namespace App\Services\Integration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EspoCrmService extends ApiClientBase
{
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.espocrm.base_url');
        $this->apiKey = config('services.espocrm.api_key');
        $this->timeout = 30;
        $this->authenticate();
    }

    protected function authenticate(): ?string
    {
        $this->accessToken = Cache::remember('espocrm_token', 3500, function () {
            if (empty($this->apiKey)) {
                Log::error('ESPO CRM API Key não configurada');
                return null;
            }
            return $this->apiKey;
        });
        
        if ($this->accessToken) {
            $this->headers = [
                'X-Api-Key: ' . $this->accessToken,
                'Content-Type: application/json',
                'Accept: application/json'
            ];
        }
        
        return $this->accessToken;
    }

    protected function getAuthCacheKey(): ?string
    {
        return 'espocrm_token';
    }

    public function getLeads(array $filters = []): array
    {
        if (!$this->ensureAuthenticated()) {
            return ['error' => 'Não autenticado', 'data' => []];
        }

        $params = array_merge([
            'offset' => 0,
            'maxSize' => 200,
            'orderBy' => 'createdAt',
            'order' => 'desc'
        ], $filters);

        $response = $this->makeRequest('GET', '/api/v1/Lead', $params);
        
        return [
            'success' => $response['success'],
            'data' => $response['data']['list'] ?? [],
            'total' => $response['data']['total'] ?? 0
        ];
    }

    public function getOportunidades(array $filters = []): array
    {
        if (!$this->ensureAuthenticated()) {
            return ['error' => 'Não autenticado', 'data' => []];
        }

        $params = array_merge([
            'offset' => 0,
            'maxSize' => 200,
            'orderBy' => 'createdAt',
            'order' => 'desc'
        ], $filters);

        $response = $this->makeRequest('GET', '/api/v1/Opportunity', $params);
        
        return [
            'success' => $response['success'],
            'data' => $response['data']['list'] ?? [],
            'total' => $response['data']['total'] ?? 0
        ];
    }

    public function getContas(array $filters = []): array
    {
        if (!$this->ensureAuthenticated()) {
            return ['error' => 'Não autenticado', 'data' => []];
        }

        $params = array_merge([
            'offset' => 0,
            'maxSize' => 200,
            'orderBy' => 'name',
            'order' => 'asc'
        ], $filters);

        $response = $this->makeRequest('GET', '/api/v1/Account', $params);
        
        return [
            'success' => $response['success'],
            'data' => $response['data']['list'] ?? [],
            'total' => $response['data']['total'] ?? 0
        ];
    }

    public function getAllEntities(string $entity, array $filters = []): array
    {
        $allData = [];
        $offset = 0;
        $maxSize = 200;
        $hasMore = true;

        while ($hasMore) {
            $params = array_merge($filters, [
                'offset' => $offset,
                'maxSize' => $maxSize
            ]);

            $response = $this->makeRequest('GET', "/api/v1/{$entity}", $params);
            
            if (!$response['success']) {
                break;
            }

            $list = $response['data']['list'] ?? [];
            $total = $response['data']['total'] ?? 0;
            
            $allData = array_merge($allData, $list);
            
            $offset += $maxSize;
            $hasMore = $offset < $total;
        }

        return [
            'success' => true,
            'data' => $allData,
            'total' => count($allData)
        ];
    }

    public function testarConexao(): bool
    {
        if (!$this->ensureAuthenticated()) {
            return false;
        }

        $response = $this->makeRequest('GET', '/api/v1/Account', ['maxSize' => 1]);
        
        return $response['success'] ?? false;
    }
}
