<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Processo;
use App\Models\Movimento;
use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Exception;

/**
 * DataJuriSyncService - Versão corrigida
 * 
 * CORREÇÕES APLICADAS (patch 2026-02-05):
 * 1. ensureAuthenticated() em TODOS os métodos sync - não depende mais de syncAll()
 * 2. Token persistido via Cache (50 min) + refresh automático em 401
 * 3. Retry com reautenticação em caso de 401
 * 4. Campos int nullable tratados (plano_conta_id, pessoa_id, etc.)
 * 5. ClassificacaoService é opcional (não quebra se não existir)
 * 6. Credenciais via env() mantidas como no backup funcional
 */
class DataJuriSyncService
{
    private $baseUrl = 'https://api.datajuri.com.br';
    private $token;
    private $clientId;
    private $secretId;
    private $username;
    private $password;
    private $perPage = 100;
    private $classificacaoService;

    public function __construct()
    {
        $this->clientId = env('DATAJURI_CLIENT_ID');
        $this->secretId = env('DATAJURI_SECRET_ID');
        $this->username = env('DATAJURI_EMAIL');
        $this->password = env('DATAJURI_PASSWORD');

        // ClassificacaoService opcional - não quebra se não existir
        try {
            if (class_exists(\App\Services\ClassificacaoService::class)) {
                $this->classificacaoService = app(\App\Services\ClassificacaoService::class);
            }
        } catch (Exception $e) {
            $this->classificacaoService = null;
        }
    }

    // =========================================================================
    // AUTENTICAÇÃO (com Cache persistente + retry)
    // =========================================================================

    /**
     * Autenticar na API DataJuri
     * Persiste token em Cache por 50 min para compartilhar entre requests
     */
    public function authenticate(): bool
    {
        try {
            // Verificar cache primeiro
            $cachedToken = Cache::get('datajuri_access_token');
            if ($cachedToken) {
                $this->token = $cachedToken;
                Log::debug('DataJuri: Token recuperado do cache');
                return true;
            }

            $credentials = base64_encode("{$this->clientId}:{$this->secretId}");
            
            $response = Http::withHeaders([
                'Authorization' => "Basic {$credentials}",
                'Content-Type' => 'application/x-www-form-urlencoded'
            ])->asForm()->post("{$this->baseUrl}/oauth/token", [
                'grant_type' => 'password',
                'username' => $this->username,
                'password' => $this->password
            ]);

            if ($response->successful()) {
                $this->token = $response->json()['access_token'];
                // Cachear por 50 min (token expira em ~60 min)
                Cache::put('datajuri_access_token', $this->token, now()->addMinutes(50));
                Log::info('DataJuri: Autenticação OK, token cacheado');
                return true;
            }

            $this->logError('DataJuri', 'Autenticação', 'Falha na autenticação: ' . $response->body());
            return false;

        } catch (Exception $e) {
            $this->logError('DataJuri', 'Autenticação', $e->getMessage());
            return false;
        }
    }

    /**
     * Garante que há um token válido antes de qualquer chamada à API.
     * Se o token está nulo, tenta autenticar.
     * 
     * @throws Exception se não conseguir autenticar
     */
    private function ensureAuthenticated(): void
    {
        if (!empty($this->token)) {
            return;
        }

        // Tentar recuperar do cache
        $cachedToken = Cache::get('datajuri_access_token');
        if ($cachedToken) {
            $this->token = $cachedToken;
            return;
        }

        // Autenticar do zero
        if (!$this->authenticate()) {
            throw new Exception('DataJuri: Não foi possível autenticar. Verifique credenciais no .env');
        }
    }

    /**
     * Força reautenticação (limpa cache e token em memória)
     */
    private function forceReauthenticate(): bool
    {
        Cache::forget('datajuri_access_token');
        $this->token = null;
        Log::info('DataJuri: Forçando reautenticação...');
        return $this->authenticate();
    }

    /**
     * Faz GET com retry automático em 401 (reautentica e tenta de novo)
     */
    private function authenticatedGet(string $url, array $params = [], int $retries = 1): \Illuminate\Http\Client\Response
    {
        $this->ensureAuthenticated();

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'Content-Type' => 'application/json'
        ])->timeout(120)->get($url, $params);

        // Se 401 e ainda tem retries, reautentica e tenta novamente
        if ($response->status() === 401 && $retries > 0) {
            Log::warning("DataJuri: 401 recebido, reautenticando... (retries restantes: {$retries})");
            if ($this->forceReauthenticate()) {
                return $this->authenticatedGet($url, $params, $retries - 1);
            }
        }

        return $response;
    }

    // =========================================================================
    // SYNC ALL
    // =========================================================================

    /**
     * Sincronizar todas as entidades
     */
    public function syncAll(): array
    {
        if (!$this->authenticate()) {
            return [
                'success' => false,
                'message' => 'Falha na autenticação com DataJuri'
            ];
        }

        $results = [
            'pessoas' => $this->syncPessoasComPaginacao(),
            'processos' => $this->syncProcessosComPaginacao(),
            'movimentos' => $this->syncMovimentosComPaginacao(),
        ];

        return [
            'success' => true,
            'results' => $results
        ];
    }

    // =========================================================================
    // SYNC PESSOAS
    // =========================================================================

    public function syncPessoasComPaginacao(): array
    {
        try {
            $this->ensureAuthenticated();

            $offset = 0;
            $totalSincronizadas = 0;
            $totalErros = 0;
            $listSize = null;

            do {
                try {
                    $response = $this->authenticatedGet(
                        "{$this->baseUrl}/v1/entidades/Pessoa",
                        ['offset' => $offset, 'maxResults' => $this->perPage]
                    );

                    if (!$response->successful()) {
                        throw new Exception('Falha ao buscar pessoas: ' . $response->body());
                    }

                    $data = $response->json();
                    $pessoas = $data['rows'] ?? [];
                    $listSize = $data['listSize'] ?? 0;

                    if (empty($pessoas)) {
                        break;
                    }

                    foreach ($pessoas as $pessoa) {
                        try {
                            Cliente::updateOrCreate(
                                ['datajuri_id' => $pessoa['id'] ?? null],
                                [
                                    'nome' => substr($pessoa['nome'] ?? '', 0, 255),
                                    'email' => substr($pessoa['email'] ?? '', 0, 255),
                                    'telefone' => substr($pessoa['telefone'] ?? '', 0, 20),
                                    'tipo' => substr($pessoa['tipo'] ?? 'PF', 0, 50),
                                ]
                            );
                            $totalSincronizadas++;
                        } catch (Exception $e) {
                            Log::warning("Erro ao sincronizar pessoa {$pessoa['id']}: " . $e->getMessage());
                            $totalErros++;
                        }
                    }

                    $offset += count($pessoas);
                } catch (Exception $e) {
                    Log::error("Erro no offset {$offset} de pessoas: " . $e->getMessage());
                    break;
                }
            } while ($offset < $listSize && $offset < 10000);

            $this->logSuccess('DataJuri', 'Pessoas', "Sincronizadas {$totalSincronizadas} pessoas ({$totalErros} erros)");
            return ['success' => true, 'count' => $totalSincronizadas, 'errors' => $totalErros];

        } catch (Exception $e) {
            $this->logError('DataJuri', 'Pessoas', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'count' => 0];
        }
    }

    // =========================================================================
    // SYNC PROCESSOS
    // =========================================================================

    public function syncProcessosComPaginacao(): array
    {
        try {
            $this->ensureAuthenticated();

            $offset = 0;
            $totalSincronizadas = 0;
            $totalErros = 0;
            $listSize = null;

            do {
                try {
                    $response = $this->authenticatedGet(
                        "{$this->baseUrl}/v1/entidades/Processo",
                        ['offset' => $offset, 'maxResults' => $this->perPage]
                    );

                    if (!$response->successful()) {
                        throw new Exception('Falha ao buscar processos: ' . $response->body());
                    }

                    $data = $response->json();
                    $processos = $data['rows'] ?? [];
                    $listSize = $data['listSize'] ?? 0;

                    if (empty($processos)) {
                        break;
                    }

                    foreach ($processos as $processo) {
                        try {
                            $clienteId = null;
                            $clienteNome = $processo['cliente.nome'] ?? $processo['cliente'] ?? null;
                            if ($clienteNome && is_string($clienteNome)) {
                                $cliente = Cliente::where('nome', 'LIKE', '%' . substr($clienteNome, 0, 50) . '%')->first();
                                $clienteId = $cliente ? $cliente->id : null;
                            }

                            Processo::updateOrCreate(
                                ['datajuri_id' => $processo['id'] ?? null],
                                [
                                    'pasta' => substr($processo['pasta'] ?? '', 0, 50),
                                    'numero' => substr($processo['numero'] ?? '', 0, 100),
                                    'descricao' => substr($processo['descricao'] ?? '', 0, 500),
                                    'status' => substr($processo['status'] ?? $processo['situacao'] ?? 'Ativo', 0, 50),
                                    'cliente_id' => $clienteId,
                                ]
                            );
                            $totalSincronizadas++;
                        } catch (Exception $e) {
                            Log::warning("Erro ao sincronizar processo {$processo['id']}: " . $e->getMessage());
                            $totalErros++;
                        }
                    }

                    $offset += count($processos);
                } catch (Exception $e) {
                    Log::error("Erro no offset {$offset} de processos: " . $e->getMessage());
                    break;
                }
            } while ($offset < $listSize && $offset < 10000);

            $this->logSuccess('DataJuri', 'Processos', "Sincronizados {$totalSincronizadas} processos ({$totalErros} erros)");
            return ['success' => true, 'count' => $totalSincronizadas, 'errors' => $totalErros];

        } catch (Exception $e) {
            $this->logError('DataJuri', 'Processos', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'count' => 0];
        }
    }

    // =========================================================================
    // SYNC MOVIMENTOS
    // =========================================================================

    /**
     * Sincronizar Movimentos com paginação
     * 
     * IMPORTANTE: 
     * - NÃO usar 'criterio' ou 'removerHtml' - quebra a API
     * - O campo 'codigo_plano' NÃO existe na API, deve ser extraído de 'planoConta.nomeCompleto'
     * - O valor vem com HTML (ex: <span class='valor-positivo'>830,09</span>)
     * - A data vem no formato brasileiro DD/MM/YYYY
     */
    public function syncMovimentosComPaginacao(): array
    {
        try {
            $this->ensureAuthenticated();

            $offset = 0;
            $totalSincronizadas = 0;
            $totalErros = 0;
            $listSize = null;

            do {
                try {
                    $response = $this->authenticatedGet(
                        "{$this->baseUrl}/v1/entidades/Movimento",
                        ['offset' => $offset, 'maxResults' => $this->perPage]
                    );

                    if (!$response->successful()) {
                        throw new Exception('Falha ao buscar movimentos (HTTP ' . $response->status() . '): ' . $response->body());
                    }

                    $data = $response->json();
                    $movimentos = $data['rows'] ?? [];
                    $listSize = $data['listSize'] ?? 0;

                    Log::info("DataJuri Movimentos: offset={$offset}, recebidos=" . count($movimentos) . ", listSize={$listSize}");

                    if (empty($movimentos)) {
                        break;
                    }

                    foreach ($movimentos as $movimento) {
                        try {
                            $planoContaCompleto = $movimento['planoConta.nomeCompleto'] ?? $movimento['planoConta'] ?? null;
                            $codigoPlano = $this->extrairCodigoPlano($planoContaCompleto);
                            
                            $valorBruto = $movimento['valorComSinal'] ?? $movimento['valor'] ?? 0;
                            $valor = $this->parseValorBrasileiro($valorBruto);
                            
                            $dataBruta = $movimento['data'] ?? null;
                            $dataFormatada = $this->parseDataBrasileira($dataBruta);
                            
                            $ano = null;
                            $mes = null;
                            if ($dataFormatada) {
                                $ano = (int) substr($dataFormatada, 0, 4);
                                $mes = (int) substr($dataFormatada, 5, 2);
                            }
                            
                            $classificacao = $this->classificarMovimento($codigoPlano, $valor);
                            
                            Movimento::updateOrCreate(
                                ['datajuri_id' => $movimento['id'] ?? null],
                                [
                                    'descricao' => substr($movimento['descricao'] ?? '', 0, 500),
                                    'observacao' => substr($movimento['observacao'] ?? '', 0, 1000),
                                    'data' => $dataFormatada,
                                    'ano' => $ano,
                                    'mes' => $mes,
                                    'valor' => $valor,
                                    'plano_contas' => substr($planoContaCompleto ?? '', 0, 500),
                                    'codigo_plano' => $codigoPlano,
                                    'classificacao' => $classificacao,
                                    'pessoa' => substr($movimento['pessoa.nome'] ?? $movimento['pessoa'] ?? '', 0, 255),
                                    'conta' => substr($movimento['contaId'] ?? '', 0, 100),
                                    'conciliado' => ($movimento['conciliado'] ?? '') === 'Sim',
                                    'plano_conta_id' => $this->toNullableInt($movimento['planoContaId'] ?? null),
                                    'pessoa_id_datajuri' => $this->toNullableInt($movimento['pessoaId'] ?? null),
                                    'contrato_id_datajuri' => $this->toNullableInt($movimento['contratoId'] ?? null),
                                    'proprietario_id' => $this->toNullableInt($movimento['proprietarioId'] ?? null),
                                ]
                            );
                            $totalSincronizadas++;
                        } catch (Exception $e) {
                            Log::warning("Erro ao sincronizar movimento {$movimento['id']}: " . $e->getMessage());
                            $totalErros++;
                        }
                    }

                    $offset += count($movimentos);
                } catch (Exception $e) {
                    Log::error("Erro no offset {$offset} de movimentos: " . $e->getMessage());
                    break;
                }
            } while ($offset < $listSize && $offset < 50000);

            $this->logSuccess('DataJuri', 'Movimentos', "Sincronizados {$totalSincronizadas} movimentos ({$totalErros} erros)");
            return ['success' => true, 'count' => $totalSincronizadas, 'errors' => $totalErros];

        } catch (Exception $e) {
            $this->logError('DataJuri', 'Movimentos', $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'count' => 0];
        }
    }

    // =========================================================================
    // ALIASES (compatibilidade)
    // =========================================================================

    public function syncPessoas(): array
    {
        return $this->syncPessoasComPaginacao();
    }

    public function syncProcessos(): array
    {
        return $this->syncProcessosComPaginacao();
    }

    public function syncMovimentos(): array
    {
        return $this->syncMovimentosComPaginacao();
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Converte valor para int nullable - resolve "Incorrect integer value: ''"
     */
    private function toNullableInt($value): ?int
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        return null;
    }

    /**
     * Extrai o código do plano de contas de uma string completa
     */
    private function extrairCodigoPlano(?string $nomeCompleto): ?string
    {
        if (empty($nomeCompleto)) {
            return null;
        }

        // Padrão 1: "3.01.01.01 - " (4 níveis com hífen)
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)\s*-/', $nomeCompleto, $m)) {
            return $m[1];
        }
        
        // Padrão 2: "3.01.01.01" (4 níveis sem hífen, no final da string antes de :)
        if (preg_match('/:(\d+\.\d+\.\d+\.\d+)/', $nomeCompleto, $m)) {
            return $m[1];
        }
        
        // Padrão 3: Qualquer 4 níveis na string
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $nomeCompleto, $m)) {
            return $m[1];
        }
        
        // Fallback: 3 níveis
        if (preg_match('/(\d+\.\d+\.\d+)\s*-/', $nomeCompleto, $m)) {
            return $m[1];
        }
        
        // Fallback: 2 níveis
        if (preg_match('/(\d+\.\d+)\s*-/', $nomeCompleto, $m)) {
            return $m[1];
        }
        
        return null;
    }

    /**
     * Parseia valor brasileiro que pode conter HTML
     */
    private function parseValorBrasileiro($valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }
        
        if (!is_string($valor)) {
            return 0.0;
        }
        
        $negativo = (stripos($valor, 'valor-negativo') !== false);
        $valor = strip_tags($valor);
        
        if (!$negativo && strpos($valor, '-') !== false) {
            $negativo = true;
        }
        
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
        $valor = preg_replace('/[^0-9.\-]/', '', $valor);
        
        $float = (float) $valor;
        
        if ($negativo && $float > 0) {
            $float = -$float;
        }
        
        return $float;
    }

    /**
     * Converte data brasileira DD/MM/YYYY para formato MySQL Y-m-d
     */
    private function parseDataBrasileira(?string $data): ?string
    {
        if (empty($data)) {
            return null;
        }
        
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $data, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return $data;
        }
        
        return null;
    }

    /**
     * Classifica um movimento usando ClassificacaoService ou inferência
     */
    private function classificarMovimento(?string $codigoPlano, float $valor): string
    {
        if (!empty($codigoPlano)) {
            if ($this->classificacaoService) {
                try {
                    $tipo = $valor < 0 ? 'DESPESA' : 'RECEITA';
                    return $this->classificacaoService->classificar($codigoPlano, $tipo);
                } catch (Exception $e) {
                    // Fallback se ClassificacaoService falhar
                }
            }
            
            return $this->inferirPorPadraoContabil($codigoPlano, $valor);
        }
        
        return $valor < 0 ? 'DESPESA' : 'PENDENTE_CLASSIFICACAO';
    }

    /**
     * Infere classificação baseada em padrões contábeis conhecidos
     */
    private function inferirPorPadraoContabil(string $codigo, float $valor): string
    {
        if (in_array($codigo, ['3.01.01.01', '3.01.01.03'])) return 'RECEITA_PF';
        if (in_array($codigo, ['3.01.01.02', '3.01.01.05'])) return 'RECEITA_PJ';
        if (str_starts_with($codigo, '3.01.02') || str_starts_with($codigo, '3.01.03')) return 'DEDUCAO';
        if (str_starts_with($codigo, '3.02')) return 'DESPESA';
        if (str_starts_with($codigo, '3.03')) return 'RECEITA_FINANCEIRA';
        if (str_starts_with($codigo, '3.04')) return 'DESPESA_FINANCEIRA';
        return $valor < 0 ? 'DESPESA' : 'PENDENTE_CLASSIFICACAO';
    }

    // =========================================================================
    // LOGGING
    // =========================================================================

    private function logSuccess($sistema, $tipo, $mensagem)
    {
        try {
            IntegrationLog::create([
                'sync_id' => Str::uuid(),
                'tipo' => $tipo,
                'fonte' => 'datajuri',
                'status' => 'concluido',
                'registros_processados' => 0
            ]);
        } catch (Exception $e) {
            Log::error("Erro ao registrar log de sucesso: " . $e->getMessage());
        }

        Log::info("[{$sistema}] {$tipo}: {$mensagem}");
    }

    private function logError($sistema, $tipo, $mensagem)
    {
        try {
            IntegrationLog::create([
                'sync_id' => Str::uuid(),
                'tipo' => $tipo,
                'fonte' => 'datajuri',
                'status' => 'erro',
                'mensagem_erro' => $mensagem
            ]);
        } catch (Exception $e) {
            Log::error("Erro ao registrar log de erro: " . $e->getMessage());
        }

        Log::error("[{$sistema}] {$tipo}: {$mensagem}");
    }
}