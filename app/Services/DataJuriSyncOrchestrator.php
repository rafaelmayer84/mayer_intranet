<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * DataJuriSyncOrchestrator v2.2
 *
 * Orquestrador �nico de sincroniza��o com API DataJuri.
 * Usado tanto pelo controller da UI quanto pelo comando artisan.
 *
 * CHANGELOG v2.2 (06/02/2026):
 *   - FIX CR�TICO: apiGet() agora normaliza UTF-8 antes de json_decode
 *   - removerHtml removido do fetchPage (quebrava API silenciosamente)
 *   - getNestedValue reescrito com 4 camadas de resolu��o
 *   - parseDecimal com strip_tags para HTML
 *   - parseHtmlValue novo m�todo para valorComSinal
 *   - conciliado Sim/N�o ? 1/0
 *   - classificacao_manual default 0
 *   - Empty strings ? null em campos nullable
 *   - cleanupStaleRuns para runs travadas >30min
 *   - tipo_classificacao lowercase (enum)
 *   - codigo_plano extra�do via regex
 *   - Campo 'tipo' removido do upsert Movimento (coluna n�o existe)
 */
class DataJuriSyncOrchestrator
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $username;
    protected string $password;
    protected int $timeout;
    protected int $retryAttempts;
    protected int $retryDelay;
    protected int $pageSize;
    protected ?string $accessToken = null;
    protected string $runId;
    protected array $config;

    public function __construct()
    {
        $this->config = config('datajuri');
        $this->baseUrl = $this->config['base_url'];
        $this->clientId = $this->config['client_id'];
        $this->clientSecret = $this->config['secret_id'];
        $this->timeout = $this->config['timeout'];
        $this->retryAttempts = $this->config['retry_attempts'];
        $this->retryDelay = $this->config['retry_delay'];
        $this->pageSize = $this->config['page_size'];
        $this->runId = (string) Str::uuid();

        // Credenciais OAuth Resource Owner Password (do config/services.php)
        $servicesConfig = config('services.datajuri', []);
        $this->username = $servicesConfig['email'] ?? $servicesConfig['username'] ?? '';
        $this->password = $servicesConfig['password'] ?? '';
    }

    // =========================================================================
    // AUTENTICA��O OAUTH
    // =========================================================================

    /**
     * Obter token OAuth com cache e renova��o autom�tica
     */
    public function getAccessToken(): ?string
    {
        $cacheKey = 'datajuri_access_token';

        // Tentar cache primeiro
        if (Cache::has($cacheKey)) {
            $this->accessToken = Cache::get($cacheKey);
            return $this->accessToken;
        }

        // Gerar novo token
        $token = $this->requestNewToken();

        if ($token) {
            // Cache por 55 minutos (token expira em 60)
            Cache::put($cacheKey, $token, now()->addMinutes(55));
            $this->accessToken = $token;
        }

        return $this->accessToken;
    }

    /**
     * Solicitar novo token OAuth (Resource Owner Password Grant)
     */
    protected function requestNewToken(): ?string
    {
        $url = "{$this->baseUrl}/oauth/token";

        // Basic Auth: clientId:clientSecret
        $basic = base64_encode("{$this->clientId}:{$this->clientSecret}");

        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                $response = Http::timeout($this->timeout)
                    ->asForm()
                    ->withHeaders([
                        'Authorization' => "Basic {$basic}",
                        'Accept' => 'application/json',
                    ])
                    ->post($url, [
                        'grant_type' => 'password',
                        'username' => $this->username,
                        'password' => $this->password,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $token = $data['access_token'] ?? null;

                    if (is_string($token) && strlen($token) > 30) {
                        Log::info('DataJuri OAuth: token obtido com sucesso');
                        return $token;
                    }

                    Log::warning('DataJuri OAuth: token inv�lido retornado');
                }

                Log::warning("DataJuri OAuth attempt {$attempt} failed", [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

            } catch (\Exception $e) {
                Log::error("DataJuri OAuth exception attempt {$attempt}", [
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $this->retryAttempts) {
                    sleep($this->retryDelay * $attempt);
                }
            }
        }

        return null;
    }

    // =========================================================================
    // REQUISI��ES API (COM FIX UTF-8)
    // =========================================================================

    /**
     * Fazer requisi��o GET � API com retry, backoff e normaliza��o UTF-8.
     *
     * FIX v2.2: A API DataJuri retorna ISO-8859-1 em alguns campos.
     * Usar $response->json() direto causava "Malformed UTF-8 characters".
     * Agora fazemos: body() ? detectar encoding ? converter para UTF-8 ? json_decode.
     */
    protected function apiGet(string $endpoint, array $params = []): ?array
    {
        $token = $this->getAccessToken();

        if (!$token) {
            throw new \Exception('Falha ao obter token de acesso');
        }

        $url = "{$this->baseUrl}{$endpoint}";

        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Authorization' => "Bearer {$token}",
                    ])
                    ->get($url, $params);

                if ($response->status() === 401) {
                    // Token expirado, renovar e tentar novamente
                    Cache::forget('datajuri_access_token');
                    $this->accessToken = null;
                    $token = $this->getAccessToken();
                    continue;
                }

                if ($response->successful()) {
                    // ============================================================
                    // FIX CR�TICO v2.2: Normalizar UTF-8 antes de json_decode
                    // A API DataJuri retorna ISO-8859-1 em alguns campos de texto.
                    // $response->json() usa json_decode() direto ? "Malformed UTF-8"
                    // ============================================================
                    $body = $response->body();

                    // Detectar encoding real
                    $encoding = mb_detect_encoding($body, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);

                    if ($encoding && $encoding !== 'UTF-8') {
                        $body = mb_convert_encoding($body, 'UTF-8', $encoding);
                    }

                    // Limpar poss�veis bytes inv�lidos remanescentes
                    $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');

                    $decoded = json_decode($body, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::warning("DataJuri API json_decode error", [
                            'url' => $url,
                            'json_error' => json_last_error_msg(),
                            'body_preview' => mb_substr($body, 0, 200),
                        ]);
                        return null;
                    }

                    return $decoded;
                }

                Log::warning("DataJuri API attempt {$attempt} failed", [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                ]);

            } catch (\Exception $e) {
                Log::error("DataJuri API exception attempt {$attempt}", [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $this->retryAttempts) {
                    $delay = $this->retryDelay * pow(2, $attempt - 1);
                    sleep($delay);
                }
            }
        }

        return null;
    }

    // =========================================================================
    // DISCOVERY E CONTAGENS
    // =========================================================================

    /**
     * Descobrir m�dulos dispon�veis na API
     */
    public function discoverModules(): array
    {
        $response = $this->apiGet('/v1/modulos');

        if (!$response) {
            return array_keys($this->config['modulos']);
        }

        return $response;
    }

    /**
     * Obter total de registros de um m�dulo
     */
    public function getModuleTotal(string $modulo): int
    {
        $config = $this->config['modulos'][$modulo] ?? null;

        if (!$config) {
            return 0;
        }

        $params = [];
        if (!empty($config['criterio'])) {
            $params['criterio'] = $config['criterio'];
        }

        $response = $this->apiGet("/v1/entidades/total/{$modulo}", $params);

        return $response['total'] ?? 0;
    }

    // =========================================================================
    // BUSCA PAGINADA
    // =========================================================================

    /**
     * Buscar p�gina de entidades.
     *
     * FIX v2.1: Removido 'removerHtml' => 'true' que quebrava a API silenciosamente.
     * A API retorna 0 rows quando esse par�metro est� presente.
     * HTML � tratado no cliente via strip_tags() e parseHtmlValue().
     */
    public function fetchPage(string $modulo, int $page): ?array
    {
        $config = $this->config['modulos'][$modulo] ?? null;

        if (!$config) {
            return null;
        }

        $params = [
            'page' => $page,
            'pageSize' => $this->pageSize,
            'campos' => $config['campos'],
            // REMOVIDO: 'removerHtml' => 'true' � PROIBIDO, quebra API silenciosamente
        ];

        // S� adicionar criterio se estiver definido
        if (!empty($config['criterio'])) {
            $params['criterio'] = $config['criterio'];
        }

        return $this->apiGet("/v1/entidades/{$modulo}", $params);
    }

    // =========================================================================
    // TRACKING DE EXECU��O (sync_runs)
    // =========================================================================

    /**
     * Iniciar execu��o de sincroniza��o
     */
    public function startRun(string $tipo = 'full'): string
    {
        DB::table('sync_runs')->insert([
            'run_id' => $this->runId,
            'tipo' => $tipo,
            'status' => 'running',
            'started_at' => now(),
            'modulos_processados' => json_encode([]),
            'erros_detalhados' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->runId;
    }

    /**
     * Atualizar progresso da execu��o
     */
    public function updateProgress(array $data): void
    {
        $data['updated_at'] = now();
        DB::table('sync_runs')
            ->where('run_id', $this->runId)
            ->update($data);
    }

    /**
     * Finalizar execu��o
     */
    public function finishRun(string $status = 'completed', ?string $mensagem = null): void
    {
        $update = [
            'status' => $status,
            'finished_at' => now(),
            'updated_at' => now(),
        ];

        if ($mensagem) {
            $update['mensagem'] = $mensagem;
        }

        DB::table('sync_runs')
            ->where('run_id', $this->runId)
            ->update($update);
    }

    /**
     * Auto-cancelar runs travadas >30min (FIX v2.1)
     */
    public function cleanupStaleRuns(): int
    {
        return DB::table('sync_runs')
            ->where('status', 'running')
            ->where('started_at', '<', now()->subMinutes(30))
            ->update([
                'status' => 'cancelled',
                'mensagem' => 'Auto-cancelado: timeout >30min',
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
    }

    // =========================================================================
    // SINCRONIZA��O DE M�DULOS
    // =========================================================================

    /**
     * Sincronizar um m�dulo completo (pagina��o autom�tica)
     */
    public function syncModule(string $modulo, callable $progressCallback = null): array
    {
        $config = $this->config['modulos'][$modulo] ?? null;

        if (!$config) {
            throw new \Exception("M�dulo {$modulo} n�o configurado");
        }

        $result = [
            'modulo' => $modulo,
            'processados' => 0,
            'criados' => 0,
            'atualizados' => 0,
            'erros' => 0,
            'paginas' => 0,
        ];

        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->fetchPage($modulo, $page);

            if (!$response || empty($response['rows'])) {
                $hasMore = false;
                break;
            }

            $totalPages = ceil(($response['listSize'] ?? 0) / $this->pageSize);

            $this->updateProgress([
                'pagina_atual' => $page,
                'total_paginas' => $totalPages,
            ]);

            if ($progressCallback) {
                $progressCallback($modulo, $page, $totalPages, 'processing');
            }

            // Processar p�gina em transa��o
            DB::beginTransaction();
            try {
                foreach ($response['rows'] as $row) {
                    $upsertResult = $this->upsertRecord($modulo, $row, $config);

                    if ($upsertResult === 'created') {
                        $result['criados']++;
                    } elseif ($upsertResult === 'updated') {
                        $result['atualizados']++;
                    }

                    $result['processados']++;
                }

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                $result['erros']++;
                Log::error("Erro ao processar p�gina {$page} de {$modulo}", [
                    'error' => $e->getMessage(),
                ]);
            }

            $result['paginas'] = $page;
            $page++;

            // Verificar se h� mais p�ginas
            if ($page > $totalPages || count($response['rows']) < $this->pageSize) {
                $hasMore = false;
            }
        }

        if ($progressCallback) {
            $progressCallback($modulo, $result['paginas'], $result['paginas'], 'completed');
        }

        return $result;
    }

    // =========================================================================
    // UPSERT COM TRATAMENTO ESPECIAL POR M�DULO
    // =========================================================================

    /**
     * Inserir ou atualizar registro com detec��o de mudan�as.
     *
     * FIX v2.1: Tratamento especial para m�dulo Movimento:
     *   - conciliado Sim/N�o ? 1/0
     *   - classificacao_manual default 0
     *   - empty strings ? null em campos nullable
     *   - tipo_classificacao lowercase
     *   - codigo_plano extra�do via regex
     *   - campo 'tipo' removido (coluna n�o existe na tabela movimentos)
     *   - parseHtmlValue para valorComSinal
     */
    protected function upsertRecord(string $modulo, array $row, array $config): string
    {
        $table = $config['table'];
        $mapping = $config['mapping'];
        $datajuriId = $row['id'] ?? null;

        if (!$datajuriId) {
            return 'skipped';
        }

        // Calcular hash do payload
        $payloadHash = hash('sha256', json_encode($row));

        // Verificar se registro existe
        $existing = DB::table($table)
            ->where('origem', 'datajuri')
            ->where('datajuri_id', $datajuriId)
            ->first();

        // Mapear campos
        $data = [
            'origem' => 'datajuri',
            'datajuri_id' => $datajuriId,
            'payload_hash' => $payloadHash,
            'payload_raw' => json_encode($row),
            'updated_at_api' => now(),
            'is_stale' => false,
            'updated_at' => now(),
        ];

        foreach ($mapping as $apiField => $dbField) {
            if ($dbField === 'datajuri_id') continue;

            $value = $this->getNestedValue($row, $apiField);

            // Converter valores especiais
            if (str_contains($apiField, 'data') || str_contains($apiField, 'Data')) {
                $value = $this->parseDate($value);
            } elseif (str_contains($apiField, 'valor') || str_contains($apiField, 'Valor')) {
                $value = $this->parseDecimal($value);
            }

            // FIX: campos que nao aceitam string vazia - converter para null
            if ($value === '' && (
                str_ends_with($dbField, '_datajuri_id') ||
                str_ends_with($dbField, '_id_datajuri') ||
                in_array($dbField, [
                    'proprietario_id', 'advogado_id', 'contratante_id_datajuri',
                    'hora_inicial', 'hora_final', 'total_hora_trabalhada',
                    'duracao_original', 'valor_total_original', 'data_faturado',
                ])
            )) {
                $value = null;
            }
            $data[$dbField] = $value;
        }

        // =====================================================================
        // SANITIZACAO GENERICA DE TIPOS (FIX v2.3 - 18/02/2026)
        // =====================================================================

        // 1. Campos inteiros que recebem string vazia da API -> null
        $integerNullableFields = [
            'pessoa_datajuri_id', 'processo_datajuri_id', 'adverso_datajuri_id',
            'cliente_datajuri_id', 'contrato_datajuri_id', 'contratante_id_datajuri',
            'proprietario_id', 'advogado_id',
        ];
        foreach ($integerNullableFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        // 2. Campo particular: Nao/Sim -> 0/1
        if (array_key_exists('particular', $data)) {
            $partVal = strtolower(trim((string)($data['particular'] ?? '')));
            $data['particular'] = in_array($partVal, ['sim', 's', '1', 'true']) ? 1 : 0;
        }

        // 3. Campos decimal NOT NULL: string vazia -> 0
        $decimalNotNullFields = ['valor_hora'];
        foreach ($decimalNotNullFields as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = 0;
            }
        }

        // 4. Campos texto com limite: truncar
        $textLimits = ['observacao' => 1000];
        foreach ($textLimits as $field => $maxLen) {
            if (array_key_exists($field, $data) && is_string($data[$field]) && mb_strlen($data[$field]) > $maxLen) {
                $data[$field] = mb_substr($data[$field], 0, $maxLen);
            }
        }


        // =====================================================================
        // TRATAMENTO ESPECIAL: M�dulo Movimento (FIX v2.1)
        // =====================================================================
        if ($modulo === 'Movimento') {
            // 1. Processar valorComSinal (HTML ? valor + tipo receita/despesa)
            $valorComSinal = $row['valorComSinal'] ?? '';
            if (!empty($valorComSinal)) {
                $parsed = $this->parseHtmlValue($valorComSinal);
                if ($parsed['valor'] !== null) {
                    $data['valor'] = $parsed['valor'];
                }
                if ($parsed['tipo_classificacao'] !== null) {
                    $data['tipo_classificacao'] = $parsed['tipo_classificacao']; // lowercase: 'receita'/'despesa'
                }
            }

            // 2. Extrair codigo_plano via regex do planoConta.nomeCompleto
            $nomeCompleto = $this->getNestedValue($row, 'planoConta.nomeCompleto') ?? '';
            if (!empty($nomeCompleto) && preg_match('/(\d+\.\d+\.\d+\.\d+)/', $nomeCompleto, $m)) {
                $data['codigo_plano'] = $m[1];
            }
            // FIX: Descartar movimentos sem codigo_plano (nao devem ser gravados)
            if ($table === 'movimentos' && (empty($data['codigo_plano'] ?? null) || !str_starts_with($data['codigo_plano'], '3.'))) {
                return 'skipped';
            }

            // 3. conciliado: Sim/N�o ? 1/0
            if (isset($data['conciliado'])) {
                $concVal = strtolower((string)$data['conciliado']);
                $data['conciliado'] = in_array($concVal, ['sim', 's', '1', 'true']) ? 1 : 0;
            }

            // 4. classificacao_manual: default 0
            $data['classificacao_manual'] = $data['classificacao_manual'] ?? 0;

            // 5. Remover campo 'tipo' � coluna n�o existe na tabela movimentos
            unset($data['tipo']);

            // 6. Calcular mes/ano
            if (!empty($data['data'])) {
                try {
                    $date = new \DateTime($data['data']);
                    $data['mes'] = (int) $date->format('n');
                    $data['ano'] = (int) $date->format('Y');
                } catch (\Exception $e) {
                    // Data inv�lida, ignorar
                }
            }

            // 7. Classifica��o autom�tica via ClassificacaoService (se dispon�vel)
            try {
                if (class_exists(\App\Services\ClassificacaoService::class)) {
                    $classificacaoService = app(\App\Services\ClassificacaoService::class);
                    $classificacao = $classificacaoService->classificar($data['codigo_plano'] ?? null);
                    if ($classificacao) {
                        $data['classificacao'] = $classificacao;
                    }
                }
            } catch (\Exception $e) {
                // ClassificacaoService n�o dispon�vel, continuar sem classifica��o
            }
        }

        // =====================================================================
        // SANITIZA��O FINAL: Empty strings ? null em campos nullable (FIX v2.1)
        // A API retorna "" (string vazia) para campos sem valor.
        // MySQL strict mode rejeita "" em campos INT/DECIMAL NULL.
        // =====================================================================
        $intFields = ['pessoa_id_datajuri', 'contrato_id_datajuri', 'processo_id',
                      'plano_conta_id', 'proprietario_id', 'pessoaId', 'planoContaId',
                      'processoId', 'proprietarioId'];
        foreach ($intFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        
        // =====================================================================
        // TRATAMENTO ESPECIAL: Módulo Pessoa (PATCH 07/02/2026)
        // Campos que precisam de conversão especial antes do upsert
        // =====================================================================
        if ($modulo === 'Pessoa') {
            // 1. is_cliente: API retorna "Sim"/"Não" no campo 'cliente'
            $clienteFlag = $row['cliente'] ?? '';
            $data['is_cliente'] = (mb_strtolower(trim($clienteFlag)) === 'sim') ? 1 : 0;

            // 2. tipo: limpar "Pessoa Física - Cliente" → "PF", "Pessoa Jurídica" → "PJ"
            $tipoPessoa = $data['tipo'] ?? '';
            if (stripos($tipoPessoa, 'Física') !== false || stripos($tipoPessoa, 'sica') !== false) {
                $data['tipo'] = 'PF';
            } elseif (stripos($tipoPessoa, 'Jurídica') !== false || stripos($tipoPessoa, 'dica') !== false) {
                $data['tipo'] = 'PJ';
            }

            // 3. telefone: se vazio, usar celular como fallback
            $tel = trim($data['telefone'] ?? '');
            $cel = trim($data['celular'] ?? '');
            if (empty($tel) && !empty($cel)) {
                $data['telefone'] = $cel;
            }

            // 4. telefone_normalizado: extrair apenas dígitos, adicionar DDI 55
            $fonePrincipal = !empty($tel) ? $tel : $cel;
            $data['telefone_normalizado'] = $this->normalizarTelefone($fonePrincipal);

            // 5. endereco: montar endereço completo a partir dos campos decompostos
            $partes = array_filter([
                $data['endereco_rua'] ?? '',
                !empty($data['endereco_numero'] ?? '') ? ', ' . $data['endereco_numero'] : '',
                !empty($data['endereco_complemento'] ?? '') ? ' - ' . $data['endereco_complemento'] : '',
                !empty($data['endereco_bairro'] ?? '') ? ' - ' . $data['endereco_bairro'] : '',
                !empty($data['endereco_cidade'] ?? '') ? ', ' . $data['endereco_cidade'] : '',
                !empty($data['endereco_estado'] ?? '') ? '/' . $data['endereco_estado'] : '',
                !empty($data['endereco_cep'] ?? '') ? ' - CEP: ' . $data['endereco_cep'] : '',
            ]);
            $enderecoCompleto = trim(implode('', $partes));
            if (!empty($enderecoCompleto)) {
                $data['endereco'] = $enderecoCompleto;
            }
        }
        // =====================================================================
        // TRATAMENTO ESPECIAL: Módulo Processo (FIX 07/02/2026)
        // ganho_causa: API retorna "Sim"/"Não", banco espera tinyint
        // =====================================================================
        if ($modulo === 'Processo') {
            // Campos monetarios NOT NULL: default 0.00
            $data['valor_causa'] = $data['valor_causa'] ?? 0;
            $data['valor_provisionado'] = $data['valor_provisionado'] ?? 0;
            $data['valor_sentenca'] = $data['valor_sentenca'] ?? 0;
            if (isset($data['ganho_causa'])) {
                $gcVal = strtolower(trim((string)$data['ganho_causa']));
                $data['ganho_causa'] = in_array($gcVal, ['sim', 's', '1', 'true']) ? 1 : 0;
            }
        }

if ($existing) {
            // Verificar se houve mudan�a
            if ($existing->payload_hash === $payloadHash) {
                DB::table($table)
                    ->where('id', $existing->id)
                    ->update(['is_stale' => false, 'updated_at' => now()]);
                return 'unchanged';
            }

            // Atualizar registro
            DB::table($table)
                ->where('id', $existing->id)
                ->update($data);

            $this->postUpsertHook($modulo, $table, $existing->id, $data);

            return 'updated';
        }

        // Criar novo registro
        $data['created_at'] = now();
        DB::table($table)->insert($data);

        $newId = DB::getPdo()->lastInsertId();
        $this->postUpsertHook($modulo, $table, $newId, $data);

        return 'created';
    }

    // =========================================================================
    // RESOLU��O DE CAMPOS DA API
    // =========================================================================

    /**
     * Obter valor de array com 4 camadas de resolu��o (FIX v2.1).
     *
     * A API DataJuri retorna chaves FLAT com ponto literal:
     *   "planoConta.nomeCompleto" � uma chave, N�O acesso aninhado.
     *   "pessoaId" � camelCase flat, N�O "pessoa.id".
     *
     * Camadas:
     *   1. Chave flat exata (ex: "planoConta.nomeCompleto")
     *   2. Padr�o camelCase para ".id" (ex: "pessoa.id" ? "pessoaId")
     *   3. Concatena��o camelCase (ex: "planoConta.codigo" ? "planoContaCodigo")
     *   4. Fallback acesso aninhado (�ltimo recurso)
     */
    protected function getNestedValue(array $data, string $key)
    {
        // Camada 1: Chave flat exata
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        // Se n�o cont�m ponto, retornar null
        if (!str_contains($key, '.')) {
            return null;
        }

        $parts = explode('.', $key);

        // Camada 2: Padr�o camelCase para ".id" (pessoa.id ? pessoaId)
        if (count($parts) === 2 && $parts[1] === 'id') {
            $camelKey = $parts[0] . 'Id';
            if (array_key_exists($camelKey, $data)) {
                return $data[$camelKey];
            }
        }

        // Camada 3: Concatena��o camelCase (planoConta.codigo ? planoContaCodigo)
        if (count($parts) === 2) {
            $camelKey = $parts[0] . ucfirst($parts[1]);
            if (array_key_exists($camelKey, $data)) {
                return $data[$camelKey];
            }
        }

        // Camada 4: Fallback acesso aninhado
        $value = $data;
        foreach ($parts as $k) {
            if (!is_array($value) || !isset($value[$k])) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    // =========================================================================
    // PARSING DE VALORES
    // =========================================================================

    /**
     * Converter data brasileira para formato MySQL
     */
    protected function parseDate($value): ?string
    {
        if (empty($value)) return null;

        // Formato: dd/mm/yyyy ou dd/mm/yyyy HH:ii
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $value, $m)) {
            $date = "{$m[3]}-{$m[2]}-{$m[1]}";

            if (preg_match('/(\d{2}):(\d{2})/', $value, $t)) {
                $date .= " {$t[1]}:{$t[2]}:00";
            }

            return $date;
        }

        return $value;
    }

    /**
     * Converter valor decimal brasileiro para float.
     *
     * FIX v2.1: strip_tags() antes do parsing para remover HTML que vem da API.
     */
    protected function parseDecimal($value): ?float
    {
        if (empty($value) && $value !== '0' && $value !== 0) return null;

        // FIX: Remover HTML que pode vir da API
        $value = strip_tags((string)$value);

        // Remover R$, espa�os
        $value = preg_replace('/[R$\s]/', '', $value);
        // Remover separador de milhar
        $value = str_replace('.', '', $value);
        // Converter v�rgula decimal para ponto
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Extrair valor e tipo (receita/despesa) do campo valorComSinal da API.
     *
     * API retorna: "<span class='valor-positivo'>830,09</span>"
     *           ou: "<span class='valor-negativo'>638,28</span>"
     *
     * Retorna: ['valor' => 830.09, 'tipo_classificacao' => 'receita']
     *
     * NOVO v2.1
     */
    protected function parseHtmlValue(string $html): array
    {
        $result = ['valor' => null, 'tipo_classificacao' => null];

        if (empty($html)) return $result;

        // Detectar tipo pela classe CSS
        if (str_contains($html, 'valor-positivo')) {
            $result['tipo_classificacao'] = 'receita';
        } elseif (str_contains($html, 'valor-negativo')) {
            $result['tipo_classificacao'] = 'despesa';
        }

        // Extrair valor num�rico
        $cleanValue = strip_tags($html);
        $parsed = $this->parseDecimal($cleanValue);

        if ($parsed !== null) {
            // Valor sempre positivo, o tipo indica receita/despesa
            $result['valor'] = abs($parsed);
        }

        return $result;
    }

    // =========================================================================
    // HOOKS P�S-UPSERT
    // =========================================================================

    /**
     * Processar campos derivados ap�s upsert
     */
    protected function postUpsertHook(string $modulo, string $table, int $recordId, array $data): void
    {
        if ($modulo === 'Movimento' && $table === 'movimentos') {
            $this->calculateMovimentoFields($recordId, $data);
        }
    }

    /**
     * Calcular campos derivados para movimentos
     */
    protected function calculateMovimentoFields(int $id, array $data): void
    {
        $updates = [];

        // Calcular mes e ano a partir de data
        if (!empty($data['data'])) {
            try {
                $date = new \DateTime($data['data']);
                $updates['mes'] = (int) $date->format('n');
                $updates['ano'] = (int) $date->format('Y');
            } catch (\Exception $e) {
                // Data inv�lida, ignorar
            }
        }

        if (!empty($updates)) {
            DB::table('movimentos')
                ->where('id', $id)
                ->update($updates);
        }
    }

    // =========================================================================
    // REPROCESSAMENTO FINANCEIRO
    // =========================================================================

    /**
     * Reprocessar financeiro (full refresh com stale marking)
     */
    public function reprocessarFinanceiro(callable $progressCallback = null): array
    {
        $this->startRun('reprocessar_financeiro');

        $result = [
            'run_id' => $this->runId,
            'processados' => 0,
            'criados' => 0,
            'atualizados' => 0,
            'deletados' => 0,
            'erros' => 0,
        ];

        try {
            // Passo 1: Marcar todos os movimentos datajuri como stale
            $this->updateProgress(['mensagem' => 'Marcando registros como stale...']);

            DB::table('movimentos')
                ->where('origem', 'datajuri')
                ->update(['is_stale' => true]);

            if ($progressCallback) {
                $progressCallback('Movimento', 0, 0, 'marking_stale');
            }

            // Passo 2: Sincronizar todos os movimentos (upsert remove flag stale)
            $syncResult = $this->syncModule('Movimento', $progressCallback);

            $result['processados'] = $syncResult['processados'];
            $result['criados'] = $syncResult['criados'];
            $result['atualizados'] = $syncResult['atualizados'];
            $result['erros'] = $syncResult['erros'];

            // Passo 3: Contar e opcionalmente deletar stale
            $staleCount = DB::table('movimentos')
                ->where('origem', 'datajuri')
                ->where('is_stale', true)
                ->count();

            $result['deletados'] = $staleCount;

            // Limpar cache do dashboard para refletir novos dados
            \Illuminate\Support\Facades\Cache::flush();

            $this->finishRun('completed', "Reprocessamento conclu�do: {$result['processados']} processados, {$staleCount} marcados como removidos");

        } catch (\Exception $e) {
            // SAFETY NET: Reverter TODOS os stale para nao bloquear dashboard
            try {
                $reverted = DB::table('movimentos')
                    ->where('is_stale', true)
                    ->update(['is_stale' => false]);
                Log::warning("Reprocessar FALHOU - revertidos {$reverted} registros stale", [
                    'error' => $e->getMessage()
                ]);
            } catch (\Exception $revertEx) {
                Log::critical("Reprocessar: falha ao reverter stale!", [
                    'original_error' => $e->getMessage(),
                    'revert_error' => $revertEx->getMessage()
                ]);
            }

            // Limpar cache mesmo em caso de falha
            try { \Illuminate\Support\Facades\Cache::flush(); } catch (\Exception $ce) {}

            $this->finishRun('failed', 'Falha: ' . substr($e->getMessage(), 0, 200));
            Log::error('Reprocessamento financeiro falhou', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $result;
    }

    // =========================================================================
    // SMOKE TEST
    // =========================================================================

    /**
     * Testar conectividade com a API DataJuri
     */
    public function smokeTest(): array
    {
        $results = [
            'token' => false,
            'modulos' => false,
            'pessoa' => false,
            'movimento' => false,
            'pessoa_count' => 0,
            'movimento_count' => 0,
            'errors' => [],
        ];

        try {
            // Teste 1: Token
            $token = $this->getAccessToken();
            $results['token'] = !empty($token);

            if (!$results['token']) {
                $results['errors'][] = 'Falha ao obter token OAuth';
                return $results;
            }

            // Teste 2: M�dulos
            $modulos = $this->apiGet('/v1/modulos');
            $results['modulos'] = is_array($modulos);

            // Teste 3: Pessoa (1 registro)
            $pessoa = $this->fetchPage('Pessoa', 1);
            $results['pessoa'] = isset($pessoa['rows']) && count($pessoa['rows']) > 0;
            $results['pessoa_count'] = $pessoa['listSize'] ?? 0;

            // Teste 4: Movimento (1 registro)
            $movimento = $this->fetchPage('Movimento', 1);
            $results['movimento'] = isset($movimento['rows']) && count($movimento['rows']) > 0;
            $results['movimento_count'] = $movimento['listSize'] ?? 0;

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Normalizar telefone para formato E.164 sem o +
     * Ex: "(47) 99999-0001" → "5547999990001"
     * Ex: "+55 47 99999-0001" → "5547999990001"
     * Ex: "47999990001" → "5547999990001"
     */
    protected function normalizarTelefone(?string $telefone): ?string
    {
        if (empty($telefone)) {
            return null;
        }

        // Extrair apenas dígitos
        $digits = preg_replace('/\D/', '', $telefone);

        if (empty($digits)) {
            return null;
        }

        // Se começa com 55 e tem 12-13 dígitos → já tem DDI
        if (preg_match('/^55\d{10,11}$/', $digits)) {
            return $digits;
        }

        // Se tem 10-11 dígitos → DDD + número, adicionar DDI 55
        if (preg_match('/^\d{10,11}$/', $digits)) {
            return '55' . $digits;
        }

        // Se tem 8-9 dígitos → só número sem DDD, não conseguimos normalizar com confiança
        return null;
    }

}