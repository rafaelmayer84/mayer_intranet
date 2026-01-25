<?php

namespace App\Services\Integration;

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

    private function authenticate(): void
    {
        $this->accessToken = Cache::remember('datajuri_token', 3500, function () {
            $clientId = config('services.datajuri.client_id');
            $secretId = config('services.datajuri.secret_id');
            $email    = config('services.datajuri.email');
            $password = config('services.datajuri.password');

            if (!$clientId || !$secretId || !$email || !$password) return null;

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
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                return $data['access_token'] ?? null;
            }
            return null;
        });
    }

    private function ensureAuthenticated(): bool
    {
        if ($this->accessToken) return true;
        Cache::forget('datajuri_token');
        $this->authenticate();
        return (bool) $this->accessToken;
    }

    public function getToken(): ?string { return $this->accessToken; }
    public function isAuthenticated(): bool { return $this->accessToken !== null; }
    public function testarConexao(): bool { $token = $this->getToken(); return $token !== null && strlen($token) > 10; }

    public function buscarModuloPagina(string $modulo, int $pagina = 1, int $porPagina = 100, array $params = []): array
    {
        if (!$this->ensureAuthenticated()) return ['rows' => [], 'listSize' => 0];

        unset($params['page'], $params['pageSize']);
        $queryParams = array_merge($params, [
            'page'        => $pagina,
            'pageSize'    => $porPagina,
            'removerHtml' => 'true',
        ]);

        $url = "{$this->baseUrl}/v1/entidades/{$modulo}?" . http_build_query($queryParams);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$this->accessToken}", "Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $rows = $data['rows'] ?? [];
        $listSize = (int) ($data['listSize'] ?? (is_array($rows) ? count($rows) : 0));

        return ['rows' => is_array($rows) ? $rows : [], 'listSize' => $listSize, 'pageSize' => $porPagina];
    }

    public function buscarModulo(string $modulo, array $params = []): array
    {
        $all = []; $pagina = 1; $porPagina = 200;
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

    public function getContasReceber(int $pagina = 1, int $porPagina = 100): array
    {
        $params = [
            // AQUI ESTAVA O ERRO: Pedimos nominalmente todos os campos vitais + status
            'campos'   => 'id,dataVencimento,dataCompetencia,valor,valorPago,dataPagamento,descricao,observacao,status,situacao,pessoa.nome,pessoaId,processo,classificacaoFinanceira,conta,formaPagamento,numeroParcela,quantidadeParcelas',
            'criterio' => 'dataVencimento | menor que | ' . date('d/m/Y') . ' ## prazo | diferente de | Concluído',
        ];

        $resultado = $this->buscarModuloPagina('ContasReceber', $pagina, $porPagina, $params);

        if (!empty($resultado['rows'])) {
            $filtrados = [];
            foreach ($resultado['rows'] as $row) {
                $status = $row['status'] ?? '';
                // Filtro vacinado (UTF-8 e Case Insensitive)
                if (preg_match('/n.o lan.ado/ui', $status)) {
                    $filtrados[] = $row;
                }
            }
            $resultado['rows'] = $filtrados;
            // Ajusta o contador para o sistema não tentar ler paginas vazias
            $resultado['listSize'] = count($filtrados); 
        }

        return $resultado;
    }
}
