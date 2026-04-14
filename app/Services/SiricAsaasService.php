<?php

/**
 * ============================================================================
 * SIRIC v2 — SiricAsaasService
 * ============================================================================
 *
 * Serviço de integração com a API Asaas para consulta ao bureau de crédito (Serasa).
 *
 * Responsabilidades:
 * - Solicitar relatório de crédito via API Asaas (POST /creditBureauReport)
 * - Extrair texto do PDF Serasa retornado (base64 → PdfParser)
 * - PARSE ESTRUTURADO (v2): extrair score, pendências, protestos, cheques do texto
 * - Buscar relatório já gerado por ID (GET /creditBureauReport/{id})
 * - Tratar erros de API e logar com CPF mascarado
 *
 * v2 mudanças:
 * - Novo método parsearTextoSerasa() extrai dados estruturados do texto bruto
 * - Retorna tanto texto_bruto quanto dados_estruturados para a IA
 * - Custo por relatório: R$ 17,00
 * ============================================================================
 */

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

            // Extrair texto do PDF Serasa (reportFile = base64)
            $textoSerasa = null;
            if (!empty($data['reportFile'])) {
                try {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseContent(base64_decode($data['reportFile']));
                    $textoSerasa = $pdf->getText();
                    Log::info('SIRIC Asaas: texto Serasa extraído', ['chars' => strlen($textoSerasa)]);
                } catch (\Throwable $e) {
                    Log::warning('SIRIC Asaas: falha ao extrair texto do PDF Serasa', ['error' => $e->getMessage()]);
                }
            }

            // v2: Parse estruturado do texto extraído
            $dadosEstruturados = $textoSerasa ? $this->parsearTextoSerasa($textoSerasa) : null;

            return [
                'success' => true,
                'data' => [
                    'id' => $data['id'] ?? null,
                    'dateCreated' => $data['dateCreated'] ?? null,
                    'state' => $data['state'] ?? null,
                    'downloadUrl' => $data['downloadUrl'] ?? null,
                    'hasReportFile' => !empty($data['reportFile']),
                    'textoSerasa' => $textoSerasa,
                    'dados_estruturados' => $dadosEstruturados,
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
     * v2: Extrai dados estruturados do texto bruto do PDF Serasa.
     *
     * Tenta capturar: score, situação CPF, pendências internas/financeiras,
     * protestos, cheques sem fundo, participações societárias.
     * Retorna o que conseguir — campos não encontrados ficam null.
     */
    public function parsearTextoSerasa(string $texto): array
    {
        $dados = [
            'score' => null,
            'score_faixa' => null,
            'probabilidade_pagamento' => null,
            'situacao_cpf_cnpj' => null,
            'pendencias_internas' => ['qtd' => 0, 'valor_total' => 0.0],
            'pendencias_financeiras' => ['qtd' => 0, 'valor_total' => 0.0],
            'protestos' => ['qtd' => 0, 'valor_total' => 0.0],
            'cheques_sem_fundo' => ['qtd' => 0],
            'participacoes_societarias' => 0,
            'texto_bruto_resumido' => mb_substr($texto, 0, 3000),
        ];

        // Score Serasa (ex: "Score: 660", "SCORE 450", "Pontuação: 350")
        if (preg_match('/(?:score|pontua.+?)[:\s]*(\d{1,4})/iu', $texto, $m)) {
            $dados['score'] = (int) $m[1];
        }

        // Probabilidade de pagamento (ex: "88,50%", "64,10% de chance")
        if (preg_match('/(\d{1,3}[.,]\d{1,2})%\s*(?:de\s+)?(?:chance|probabilidade)/iu', $texto, $m)) {
            $dados['probabilidade_pagamento'] = str_replace(',', '.', $m[1]);
        }

        // Situação CPF (ex: "Situação do CPF: Regular", "CPF: Regular")
        if (preg_match('/situa.+?(?:cpf|cnpj)[:\s]*(regular|irregular|suspens\w+|cancelad\w+|pendente)/iu', $texto, $m)) {
            $dados['situacao_cpf_cnpj'] = mb_strtolower($m[1]);
        }

        // Faixa de score (ex: "Faixa 600-700")
        if (preg_match('/faixa[:\s]*(\d{1,4})\s*[-a]\s*(\d{1,4})/iu', $texto, $m)) {
            $dados['score_faixa'] = $m[1] . '-' . $m[2];
        }

        // Pendências internas (ex: "Pendências internas: 5 ocorrência(s) totalizando R$ 3.492,74")
        if (preg_match('/pend.ncias?\s+internas?[:\s]*(\d+)\s*ocorr/iu', $texto, $m)) {
            $dados['pendencias_internas']['qtd'] = (int) $m[1];
        }
        if (preg_match('/pend.ncias?\s+internas?.*?R\$\s*([\d.,]+)/iu', $texto, $m)) {
            $dados['pendencias_internas']['valor_total'] = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        }

        // Pendências financeiras
        if (preg_match('/pend.ncias?\s+financeiras?[:\s]*(\d+)\s*ocorr/iu', $texto, $m)) {
            $dados['pendencias_financeiras']['qtd'] = (int) $m[1];
        }
        if (preg_match('/pend.ncias?\s+financeiras?.*?R\$\s*([\d.,]+)/iu', $texto, $m)) {
            $dados['pendencias_financeiras']['valor_total'] = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        }

        // Protestos (ex: "Protestos: 1 ocorrência(s) totalizando R$ 2.382,80")
        if (preg_match('/protestos?[:\s]*(\d+)\s*ocorr/iu', $texto, $m)) {
            $dados['protestos']['qtd'] = (int) $m[1];
        }
        if (preg_match('/protestos?.*?R\$\s*([\d.,]+)/iu', $texto, $m)) {
            $dados['protestos']['valor_total'] = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        }

        // Cheques sem fundo
        if (preg_match('/cheques?\s+sem\s+fundo[:\s]*(\d+)/iu', $texto, $m)) {
            $dados['cheques_sem_fundo']['qtd'] = (int) $m[1];
        }
        if (preg_match('/cheques?\s+sem\s+fundo.*?nada\s+consta/iu', $texto)) {
            $dados['cheques_sem_fundo']['qtd'] = 0;
        }

        // Participações societárias
        if (preg_match('/participa.+?societ.+?(\d+)/iu', $texto, $m)) {
            $dados['participacoes_societarias'] = (int) $m[1];
        }

        return $dados;
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
