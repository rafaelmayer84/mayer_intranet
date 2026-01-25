<?php
namespace App\Services\Integration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

abstract class ApiClientBase
{
    protected string $baseUrl;
    protected ?string $accessToken = null;
    protected int $timeout = 30;
    protected int $maxRetries = 3;
    protected array $headers = [];

    abstract protected function authenticate(): ?string;

    protected function makeRequest(string $method, string $endpoint, array $data = [], array $customHeaders = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        
        $headers = array_merge($this->headers, $customHeaders);
        if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'GET':
            default:
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $this->logRequest($method, $endpoint, $data, $httpCode, $response);
        
        if ($error) {
            Log::error("cURL Error: {$error}");
            return ['error' => $error, 'http_code' => $httpCode];
        }
        
        $decoded = json_decode($response, true);
        return [
            'http_code' => $httpCode,
            'data' => $decoded ?? [],
            'raw' => $response,
            'success' => $httpCode >= 200 && $httpCode < 300
        ];
    }

    protected function retryRequest(callable $callback, int $maxRetries = null): mixed
    {
        $maxRetries = $maxRetries ?? $this->maxRetries;
        $attempt = 0;
        while ($attempt < $maxRetries) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $attempt++;
                if ($attempt >= $maxRetries) throw $e;
                $waitTime = pow(2, $attempt - 1);
                Log::warning("Retry attempt {$attempt}/{$maxRetries} after {$waitTime}s: {$e->getMessage()}");
                sleep($waitTime);
            }
        }
        return null;
    }

    protected function logRequest(string $method, string $endpoint, array $data, int $httpCode, $response): void
    {
        $logData = [
            'method' => $method,
            'endpoint' => $endpoint,
            'http_code' => $httpCode,
            'timestamp' => now()->toDateTimeString()
        ];
        if ($httpCode >= 400) {
            $logData['request_data'] = $data;
            $logData['response'] = $response;
            Log::error("API Request Failed", $logData);
        } else {
            Log::info("API Request Success", $logData);
        }
    }

    protected function ensureAuthenticated(): bool
    {
        if ($this->accessToken) return true;
        $this->accessToken = $this->authenticate();
        return $this->accessToken !== null;
    }

    public function clearAuthCache(): void
    {
        $cacheKey = $this->getAuthCacheKey();
        if ($cacheKey) Cache::forget($cacheKey);
        $this->accessToken = null;
    }

    protected function getAuthCacheKey(): ?string { return null; }
    public function testarConexao(): bool { return $this->ensureAuthenticated(); }
    public function getToken(): ?string { return $this->accessToken; }
}
