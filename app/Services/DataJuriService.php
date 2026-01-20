<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DataJuriService
{
    private string $baseUrl = 'https://api.datajuri.com.br';
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->authenticate();
    }

    /**
     * Autenticação OAuth (Password Grant) conforme documentação oficial.
     * Gera access_token via POST /oauth/token com Authorization: Basic base64(client_id:secret_id)
     * e body x-www-form-urlencoded: grant_type=password, username, password.
     */
    private function authenticate(): void
    {
        $this->accessToken = Cache::remember('datajuri_token', 3500, function () {
            $clientId = config('services.datajuri.client_id');
            $secretId = config('services.datajuri.secret_id');
            $email    = config('services.datajuri.email');
            $password = config('services.datajuri.password');

            if (!$clientId || !$secretId || !$email || !$password) {
                Log::error('DataJuri: Credenciais não configuradas (services.datajuri.*)');
                return null;
            }

            $credentials = base64_encode("{$clientId}:{$secretId}");

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "{$this->baseUrl}/oauth/token");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'grant_type' => 'password',
                'username'   => $email,
                'password'   => $password,
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Basic {$credentials}",
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['access_token']) && is_string($data['access_token']) && strlen($data['access_token']) > 10) {
                    Log::info('DataJuri: Autenticação bem-sucedida');
                    return $data['access_token'];
                }
            }

            Log::error('DataJuri: Falha na autenticação', [
                'http_code' => $httpCode,
                'error'     => $error,
                'response'  => is_string($response) ? substr($response, 0, 1000) : null,
            ]);

            return null;
        });
    }

    private function ensureAuthenticated(): bool
    {
        if ($this->accessToken) return true;

        // tenta renovar sem depender de cache anterior
        Cache::forget('datajuri_token');
        $this->authenticate();

        return (bool) $this->accessToken;
    }

    public function getToken(): ?string
    {
        return $this->accessToken;
    }

    public function isAuthenticated(): bool
    {
        return $this->accessToken !== null;
    }

    /**
     * Teste simples de conexão (token válido carregado).
     */
    public function testarConexao(): bool
    {
        $token = $this->getToken();
        return $token !== null && strlen($token) > 10;
    }

    /**
     * Busca UMA página de um módulo (para batch).
     * ATENÇÃO: mantém os parâmetros já usados no projeto: pagina / porPagina.
     *
     * Retorno padronizado:
     * [
     *   'rows' => [...],
     *   'listSize' => int,
     *   'pageSize' => int,
     *   'page' => int,
     *   'http_code' => int|null,
     *   'error' => string|null
     * ]
     */
    public function buscarModuloPagina(string $modulo, int $pagina = 1, int $porPagina = 100, array $params = []): array
    {
        if ($pagina < 1) $pagina = 1;
        if ($porPagina < 10) $porPagina = 10;
        if ($porPagina > 500) $porPagina = 500;

        if (!$this->ensureAuthenticated()) {
            Log::error("DataJuri: Sem token para buscar módulo {$modulo}");
            return [
                'rows' => [],
                'listSize' => 0,
                'pageSize' => $porPagina,
                'page' => $pagina,
                'http_code' => null,
                'error' => 'SEM_TOKEN',
            ];
        }

        unset($params['page'], $params['pageSize']);
        $queryParams = array_merge($params, [
            'page'     => $pagina,
            'pageSize' => $porPagina,
        ]);

        $url = "{$this->baseUrl}/v1/entidades/{$modulo}?" . http_build_query($queryParams);

        $debugFile = storage_path('logs/sync_debug.log');
        @file_put_contents($debugFile, "\n[".date('c')."] REQUEST modulo={$modulo} pagina={$pagina} porPagina={$porPagina} url={$url}\n", FILE_APPEND);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->accessToken}",
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errCurl  = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            Log::error("DataJuri: Erro ao buscar módulo {$modulo}", [
                'http_code' => $httpCode,
                'pagina'    => $pagina,
                'porPagina' => $porPagina,
                'curl_error'=> $errCurl,
                'response'  => is_string($response) ? substr($response, 0, 800) : null,
                'url'       => $url,
            ]);

            // 401 costuma ser token expirado/inválido: tenta uma renovação 1x e repete
            if ($httpCode === 401) {
                Cache::forget('datajuri_token');
                $this->authenticate();
                if ($this->accessToken) {
                    return $this->buscarModuloPagina($modulo, $pagina, $porPagina, $params);
                }
            }

            return [
                'rows' => [],
                'listSize' => 0,
                'pageSize' => $porPagina,
                'page' => $pagina,
                'http_code' => $httpCode,
                'error' => $errCurl ?: 'HTTP_' . $httpCode,
            ];
        }

        $data = json_decode($response, true);
        $rows = $data['rows'] ?? [];
        $listSize = (int) ($data['listSize'] ?? 0);
        $pageSize = (int) ($data['pageSize'] ?? $porPagina);

        // alguns módulos retornam listSize vazio; cai para contagem como fallback
        if ($listSize <= 0 && is_array($rows)) {
            $listSize = count($rows);
        }

        $rowsCount = is_array($rows) ? count($rows) : 0;
        @file_put_contents($debugFile, "[".date('c')."] RESPONSE http={$httpCode} rows={$rowsCount} listSize={$listSize} pageSize={$pageSize}\n", FILE_APPEND);

        return [
            'rows' => is_array($rows) ? $rows : [],
            'listSize' => $listSize,
            'pageSize' => $pageSize > 0 ? $pageSize : $porPagina,
            'page' => $pagina,
            'http_code' => $httpCode,
            'error' => null,
        ];
    }

    /**
     * Busca TODAS as páginas de um módulo (uso legado do sistema).
     * Internamente chama buscarModuloPagina() e concatena as rows.
     */
    public function buscarModulo(string $modulo, array $params = []): array
    {
        $all = [];
        $pagina = 1;
        $porPagina = 200;

        do {
            $page = $this->buscarModuloPagina($modulo, $pagina, $porPagina, $params);
            $rows = $page['rows'] ?? [];
            $all = array_merge($all, $rows);

            $listSize = (int) ($page['listSize'] ?? 0);
            $pageSize = (int) ($page['pageSize'] ?? $porPagina);
            $totalPaginas = ($pageSize > 0 && $listSize > 0) ? (int) ceil($listSize / $pageSize) : (($rows && count($rows) === $porPagina) ? $pagina + 1 : $pagina);

            $pagina++;
        } while ($pagina <= $totalPaginas);

        return $all;
    }

    /**
     * Buscar contas a receber do DataJuri
     * @param int $pagina
     * @param int $porPagina
     * @return array
     */
    public function getContasReceber(int $pagina = 1, int $porPagina = 100): array
    {
        return $this->buscarModuloPagina('ContasReceber', $pagina, $porPagina);
    }
}
