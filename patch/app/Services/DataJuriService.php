<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;
use Throwable;

class DataJuriService
{
    private string $baseUrl;
    private int $timeout;

    /**
     * Token em memória (otimiza múltiplas chamadas no mesmo request PHP).
     * Fonte da verdade continua sendo o Cache (datajuri.access_token).
     */
    private ?string $accessToken = null;

    public function __construct()
    {
        $cfg = config('services.datajuri', []);

        $this->baseUrl = rtrim((string)($cfg['base_url'] ?? 'https://api.datajuri.com.br'), '/');
        $this->timeout = (int)($cfg['timeout'] ?? 25);

        // Mantém compatibilidade: tentar autenticar ao instanciar, mas sem "derrubar" a página em caso de falha.
        try {
            $this->accessToken = $this->getAccessToken();
        } catch (Throwable $e) {
            // Nunca logar segredos.
            Log::warning('DataJuriService: falha ao inicializar token OAuth: ' . $e->getMessage());
            $this->accessToken = null;
        }
    }

    /**
     * ==========================
     *  API pública (compatível)
     * ==========================
     */
    public function getToken(): ?string
    {
        try {
            return $this->getAccessToken();
        } catch (Throwable $e) {
            return null;
        }
    }

    public function isAuthenticated(): bool
    {
        return $this->testarConexao();
    }

    /**
     * TESTE REAL: token + chamada simples autenticada (GET /v1/modulos)
     * Isso elimina "falso positivo" (token curto ou inválido).
     */
    public function testarConexao(): bool
    {
        try {
            $this->ensureAuthenticated();
            $this->getModulos(); // prova Bearer funcionando
            return true;
        } catch (Throwable $e) {
            Log::warning('DataJuriService: testarConexao falhou: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * GET /v1/modulos (prova Bearer + endpoint base)
     */
    public function getModulos(): array
    {
        return $this->getJson('/v1/modulos');
    }

    /**
     * GET /v1/entidades/{modulo}
     * Retorna: ['rows'=>[], 'listSize'=>0, 'pageSize'=>X]
     */
    public function buscarModuloPagina(string $modulo, int $pagina = 1, int $porPagina = 100, array $params = []): array
    {
        try {
            $this->ensureAuthenticated();
        } catch (Throwable $e) {
            return ['rows' => [], 'listSize' => 0, 'pageSize' => $porPagina, 'error' => 'oauth_failed'];
        }

        unset($params['page'], $params['pageSize']);

        $queryParams = array_merge($params, [
            'page'        => $pagina,
            'pageSize'    => $porPagina,
            // CORREÇÃO v2.0: removerHtml REMOVIDO - quebrava API DataJuri
        ]);

        try {
            $data = $this->getJson("/v1/entidades/{$modulo}", $queryParams);

            $rows = $data['rows'] ?? [];
            $listSize = (int)($data['listSize'] ?? (is_array($rows) ? count($rows) : 0));

            return [
                'rows' => is_array($rows) ? $rows : [],
                'listSize' => $listSize,
                'pageSize' => $porPagina,
            ];
        } catch (Throwable $e) {
            Log::warning("DataJuriService: buscarModuloPagina falhou ({$modulo}): " . $e->getMessage());
            return ['rows' => [], 'listSize' => 0, 'pageSize' => $porPagina, 'error' => 'request_failed'];
        }
    }

    /**
     * Busca todas as páginas do módulo (mantém assinatura existente).
     */
    public function buscarModulo(string $modulo, array $params = []): array
    {
        $all = [];
        $pagina = 1;
        $porPagina = 200;

        do {
            $page = $this->buscarModuloPagina($modulo, $pagina, $porPagina, $params);

            $rows = $page['rows'] ?? [];
            $all = array_merge($all, is_array($rows) ? $rows : []);

            $listSize = (int)($page['listSize'] ?? 0);
            $pageSize = (int)($page['pageSize'] ?? $porPagina);

            // Regra de paginação baseada no listSize retornado pela API.
            $totalPaginas = ($pageSize > 0 && $listSize > 0)
                ? (int)ceil($listSize / $pageSize)
                : $pagina;

            $pagina++;
        } while ($pagina <= $totalPaginas);

        return $all;
    }

    /**
     * Mantém seu método, mas corrige encoding e filtro mais robusto.
     * OBS: O ideal é filtrar no "criterio" (na API), e NÃO no PHP.
     */
    public function getContasReceber(int $pagina = 1, int $porPagina = 100): array
    {
        $params = [
            'campos'   => 'id,dataVencimento,dataCompetencia,valor,valorPago,dataPagamento,descricao,observacao,status,situacao,pessoa.nome,pessoaId,processo,classificacaoFinanceira,conta,formaPagamento,numeroParcela,quantidadeParcelas',
            'criterio' => 'dataVencimento | menor que | ' . date('d/m/Y') . ' ## prazo | diferente de | Concluído',
        ];

        $resultado = $this->buscarModuloPagina('ContasReceber', $pagina, $porPagina, $params);

        if (!empty($resultado['rows']) && is_array($resultado['rows'])) {
            $filtrados = [];
            foreach ($resultado['rows'] as $row) {
                $status = (string)($row['status'] ?? '');
                // Aceita variações: "Não lançado", "Nao lancado", caixa alta, etc.
                if (preg_match('/n[aã]o\s*lan[cç]ado/ui', $status)) {
                    $filtrados[] = $row;
                }
            }
            $resultado['rows'] = $filtrados;
            // NÃO mexe no listSize global da API aqui. Se precisar paginação correta, filtre via "criterio".
        }

        return $resultado;
    }

    /**
     * ==========================
     *  Internos (OAuth + HTTP)
     * ==========================
     */

    private function ensureAuthenticated(): void
    {
        $this->getAccessToken(); // lança exceção se falhar
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken && strlen($this->accessToken) > 30) {
            return $this->accessToken;
        }

        $ttl = now()->addMinutes(55); // evita confusão de minutos/segundos do remember()

        $token = Cache::remember('datajuri.access_token', $ttl, function () {
            [$clientId, $clientSecret, $username, $password] = $this->readCredentials();

            if (!$clientId || !$clientSecret || !$username || !$password) {
                throw new RuntimeException('Credenciais DataJuri incompletas em config/services.php (services.datajuri.*).');
            }

            $basic = base64_encode($clientId . ':' . $clientSecret);

            $resp = Http::timeout($this->timeout)
                ->asForm()
                ->withHeaders([
                    'Authorization' => 'Basic ' . $basic,
                    'Accept' => 'application/json',
                ])
                ->post($this->baseUrl . '/oauth/token', [
                    'grant_type' => 'password',
                    'username'   => $username,
                    'password'   => $password,
                ]);

            if (!$resp->successful()) {
                // Nunca logar basic/secret/password.
                $body = $resp->body();
                $snippet = mb_substr($body ?? '', 0, 1200);
                throw new RuntimeException("OAuth DataJuri HTTP {$resp->status()}: {$snippet}");
            }

            $json = $resp->json();
            $token = $json['access_token'] ?? null;

            if (!is_string($token) || strlen($token) < 30) {
                throw new RuntimeException('OAuth DataJuri retornou access_token inválido.');
            }

            return $token;
        });

        $this->accessToken = $token;

        return $token;
    }

    private function forgetToken(): void
    {
        Cache::forget('datajuri.access_token');
        $this->accessToken = null;
    }

    private function api(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->acceptJson()
            ->withToken($this->getAccessToken());
    }

    private function getJson(string $path, array $query = []): array
    {
        $url = $this->baseUrl . $path;

        $resp = $this->api()->get($url, $query);

        // Token expirou/invalidou -> renova 1x e repete
        if ($resp->status() === 401) {
            $this->forgetToken();
            $resp = $this->api()->get($url, $query);
        }

        if (!$resp->successful()) {
            $snippet = mb_substr($resp->body() ?? '', 0, 1200);
            throw new RuntimeException("DataJuri HTTP {$resp->status()} em {$path}: {$snippet}");
        }

        return $resp->json() ?? [];
    }

    /**
     * Lê credenciais aceitando tanto chaves antigas quanto novas
     * (para não quebrar seu projeto no deploy).
     */
    private function readCredentials(): array
    {
        $cfg = config('services.datajuri', []);

        $clientId = $cfg['client_id'] ?? null;

        // novo padrão: client_secret / username
        // legado: secret_id / email
        $clientSecret = $cfg['client_secret'] ?? ($cfg['secret_id'] ?? ($cfg['secret'] ?? null));
        $username = $cfg['username'] ?? ($cfg['email'] ?? ($cfg['user'] ?? null));
        $password = $cfg['password'] ?? null;

        return [
            is_string($clientId) ? $clientId : null,
            is_string($clientSecret) ? $clientSecret : null,
            is_string($username) ? $username : null,
            is_string($password) ? $password : null,
        ];
    }
}
