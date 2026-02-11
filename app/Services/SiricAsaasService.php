<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SiricAsaasService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.asaas.api_key', env('ASAAS_API_KEY', ''));
        $environment = config('services.asaas.environment', env('ASAAS_ENVIRONMENT', 'production'));
        $this->baseUrl = $environment === 'sandbox'
            ? 'https://sandbox.asaas.com/api/v3'
            : 'https://api.asaas.com/v3';
    }

    /**
     * Solicita relatório de bureau de crédito (Serasa) para um CPF/CNPJ.
     * Nome do método alinhado com o SiricController.
     *
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    public function solicitarRelatorio(string $cpfCnpj): array
    {
        return $this->consultarCreditBureau($cpfCnpj);
    }

    /**
     * Implementação da consulta ao bureau de crédito via Asaas.
     *
     * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
     */
    public function consultarCreditBureau(string $cpfCnpj): array
    {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'ASAAS_API_KEY não configurada no .env',
            ];
        }

        $docLimpo = preg_replace('/\D/', '', $cpfCnpj);

        if (strlen($docLimpo) !== 11 && strlen($docLimpo) !== 14) {
            return [
                'success' => false,
                'data' => null,
                'error' => 'CPF/CNPJ inválido: deve ter 11 ou 14 dígitos',
            ];
        }

        try {
            $response = Http::withHeaders([
                'access_token' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post("{$this->baseUrl}/creditBureauReport", [
                'cpfCnpj' => $docLimpo,
            ]);

            if (!$response->successful()) {
                $errorBody = $response->json();
                $errorMsg = $errorBody['errors'][0]['description'] ?? $response->body();

                Log::warning('SIRIC Asaas: erro na consulta Serasa', [
                    'cpf_cnpj' => substr($docLimpo, 0, 3) . '***',
                    'status' => $response->status(),
                    'error' => $errorMsg,
                ]);

                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Asaas API ({$response->status()}): {$errorMsg}",
                ];
            }

            $data = $response->json();

            Log::info('SIRIC Asaas: consulta Serasa OK', [
                'report_id' => $data['id'] ?? 'N/A',
                'cpf_cnpj' => substr($docLimpo, 0, 3) . '***',
            ]);

            return [
                'success' => true,
                'data' => [
                    'id' => $data['id'] ?? null,
                    'dateCreated' => $data['dateCreated'] ?? null,
                    'state' => $data['state'] ?? null,
                    'downloadUrl' => $data['downloadUrl'] ?? null,
                    'hasReportFile' => !empty($data['reportFile']),
                ],
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('SIRIC Asaas: exceção na consulta Serasa', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'error' => 'Erro de comunicação com Asaas: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Busca detalhes de um relatório já gerado.
     */
    public function getReport(string $reportId): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'data' => null, 'error' => 'ASAAS_API_KEY não configurada'];
        }

        try {
            $response = Http::withHeaders([
                'access_token' => $this->apiKey,
            ])
            ->timeout(30)
            ->get("{$this->baseUrl}/creditBureauReport/{$reportId}");

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => "Asaas API ({$response->status()}): {$response->body()}",
                ];
            }

            return [
                'success' => true,
                'data' => $response->json(),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
