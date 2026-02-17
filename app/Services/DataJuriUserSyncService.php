<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sincroniza usuários do DataJuri para tabela users.
 * 100% ISOLADO — não usa Orchestrator, não toca em syncModule/fetchPage.
 * Faz sua própria autenticação e chamada HTTP.
 */
class DataJuriUserSyncService
{
    /**
     * Sincroniza usuários do DataJuri → tabela users da Intranet.
     * - Ativos no DJ e já existem → atualiza vínculo datajuri_proprietario_id + perfil
     * - Ativos no DJ e NÃO existem → cria com role=advogado, senha aleatória
     * - Inativos no DJ e existem → marca datajuri_ativo=false (não deleta)
     *
     * Match por EMAIL (case-insensitive) como chave de vínculo.
     */
    public function sincronizar(): array
    {
        $stats = [
            'total_dj'    => 0,
            'vinculados'  => 0,
            'criados'     => 0,
            'desativados' => 0,
            'ignorados'   => 0,
            'erros'       => 0,
            'detalhes'    => [],
        ];

        try {
            // ── Auth própria (mesmo padrão do Orchestrator, mas isolado) ──
            $token = $this->obterToken();

            if (!$token) {
                Log::error('[UserSync] Falha ao obter token DataJuri');
                $stats['erros']++;
                $stats['detalhes'][] = 'Falha na autenticação DataJuri';
                return $stats;
            }

            // ── Buscar usuários do DataJuri ──
            $rows = $this->buscarUsuariosDJ($token);

            if ($rows === null) {
                $stats['erros']++;
                $stats['detalhes'][] = 'Falha ao buscar usuários da API';
                return $stats;
            }

            $stats['total_dj'] = count($rows);

            // ── Processar cada usuário ──
            foreach ($rows as $djUser) {
                try {
                    $this->processarUsuario($djUser, $stats);
                } catch (\Throwable $e) {
                    $djId = $djUser['id'] ?? '?';
                    Log::error("[UserSync] Erro processando DJ#{$djId}: " . $e->getMessage());
                    $stats['erros']++;
                    $stats['detalhes'][] = "Erro DJ#{$djId}: " . $e->getMessage();
                }
            }

            Log::info('[UserSync] Concluído: ' . json_encode($stats));

        } catch (\Throwable $e) {
            Log::error('[UserSync] Erro geral: ' . $e->getMessage());
            $stats['erros']++;
            $stats['detalhes'][] = 'Erro geral: ' . $e->getMessage();
        }

        return $stats;
    }

    /**
     * Obter token OAuth — isolado, não usa Orchestrator
     */
    private function obterToken(): ?string
    {
        $cacheKey = 'datajuri_access_token';

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $baseUrl      = config('datajuri.base_url', env('DATAJURI_BASE_URL'));
        $clientId     = config('datajuri.client_id', env('DATAJURI_CLIENT_ID'));
        $clientSecret = config('datajuri.client_secret', env('DATAJURI_SECRET_ID'));
        $username     = config('datajuri.username', env('DATAJURI_USERNAME'));
        $password     = config('datajuri.password', env('DATAJURI_PASSWORD'));

        $url   = "{$baseUrl}/oauth/token";
        $basic = base64_encode("{$clientId}:{$clientSecret}");

        try {
            $response = Http::timeout(30)
                ->asForm()
                ->withHeaders([
                    'Authorization' => "Basic {$basic}",
                    'Accept'        => 'application/json',
                ])
                ->post($url, [
                    'grant_type' => 'password',
                    'username'   => $username,
                    'password'   => $password,
                ]);

            if ($response->successful()) {
                $token = $response->json('access_token');
                if ($token) {
                    Cache::put($cacheKey, $token, now()->addMinutes(55));
                    return $token;
                }
            }

            Log::error('[UserSync] Token request failed: ' . $response->status());
        } catch (\Throwable $e) {
            Log::error('[UserSync] Token exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Buscar todos os usuários do DataJuri via API
     */
    private function buscarUsuariosDJ(string $token): ?array
    {
        $baseUrl = config('datajuri.base_url', env('DATAJURI_BASE_URL'));
        $url     = "{$baseUrl}/v1/entidades/Usuario";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                ])
                ->get($url, [
                    'campos'   => 'id,nome,email,telefone,celular,ativo,perfilAcessoId',
                    'pageSize' => 200,
                    'page'     => 1,
                ]);

            if ($response->status() === 401) {
                // Token expirado, limpar cache
                Cache::forget('datajuri_access_token');
                Log::warning('[UserSync] Token expirado, tente novamente');
                return null;
            }

            if ($response->successful()) {
                // Normalizar encoding (mesmo padrão do Orchestrator)
                $body     = $response->body();
                $encoding = mb_detect_encoding($body, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
                if ($encoding && $encoding !== 'UTF-8') {
                    $body = mb_convert_encoding($body, 'UTF-8', $encoding);
                }
                $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');

                $decoded = json_decode($body, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error('[UserSync] JSON decode error: ' . json_last_error_msg());
                    return null;
                }

                return $decoded['rows'] ?? [];
            }

            Log::error('[UserSync] API error: HTTP ' . $response->status());

        } catch (\Throwable $e) {
            Log::error('[UserSync] API exception: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Processar um usuário individual do DataJuri
     */
    private function processarUsuario(array $djUser, array &$stats): void
    {
        $djId    = $djUser['id'] ?? null;
        $nome    = trim($djUser['nome'] ?? '');
        $email   = strtolower(trim($djUser['email'] ?? ''));
        $celular = $djUser['celular'] ?? ($djUser['telefone'] ?? null);
        $ativo   = ($djUser['ativo'] ?? '') === 'Sim';
        $perfil  = $djUser['perfilAcessoId'] ?? null;

        if (!$djId || !$email) {
            $stats['ignorados']++;
            return;
        }

        // Buscar por datajuri_proprietario_id OU email
        $userExistente = DB::table('users')
            ->where('datajuri_proprietario_id', $djId)
            ->orWhere(DB::raw('LOWER(email)'), $email)
            ->first();

        if ($userExistente) {
            // Atualizar vínculo e dados DataJuri
            DB::table('users')->where('id', $userExistente->id)->update([
                'datajuri_proprietario_id' => $djId,
                'datajuri_perfil'          => $perfil,
                'datajuri_ativo'           => $ativo,
                'celular'                  => $celular ?: ($userExistente->celular ?? null),
                'updated_at'               => now(),
            ]);
            $stats['vinculados']++;
            $stats['detalhes'][] = "Vinculado: {$nome} ({$email}) → DJ#{$djId}" . ($ativo ? '' : ' [INATIVO]');

        } elseif ($ativo) {
            // Criar novo usuário (só ativos)
            DB::table('users')->insert([
                'name'                     => $nome,
                'email'                    => $email,
                'password'                 => Hash::make(Str::random(16)),
                'role'                     => 'advogado',
                'datajuri_proprietario_id' => $djId,
                'datajuri_perfil'          => $perfil,
                'datajuri_ativo'           => true,
                'celular'                  => $celular,
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);
            $stats['criados']++;
            $stats['detalhes'][] = "Criado: {$nome} ({$email}) DJ#{$djId}";
            Log::info("[UserSync] Criado: {$nome} ({$email}) DJ#{$djId}");

        } else {
            // Inativo e não existe na Intranet — ignora
            $stats['ignorados']++;
        }
    }
}
