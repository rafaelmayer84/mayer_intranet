<?php

namespace App\Services;

use App\Models\Aviso;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AvisoService
{
    private const CACHE_VERSION_KEY = 'quadro_avisos:version';

    /**
     * Retorna avisos que devem ser exibidos agora (página pública /avisos).
     *
     * @param array{categoria_id?:mixed, ordenar?:mixed, busca?:mixed} $filtros
     */
    public function getAvisosAtivos(array $filtros = [])
    {
        $version = $this->getCacheVersion();

        $cacheKey = 'quadro_avisos:ativos:v' . $version . ':' . md5(json_encode([
            'categoria_id' => $filtros['categoria_id'] ?? null,
            'ordenar' => $filtros['ordenar'] ?? 'prioridade',
            'busca' => trim((string)($filtros['busca'] ?? '')),
        ]));

        return Cache::remember($cacheKey, now()->addMinutes(60), function () use ($filtros) {
            $now = now();

            $query = Aviso::query();

            // Relações (se existirem no Model)
            if (method_exists(Aviso::class, 'categoria')) {
                $query->with('categoria');
            }
            if (method_exists(Aviso::class, 'autor')) {
                $query->with('autor');
            }

            // Contagem de leituras (para "Lido por X de Y")
            try {
                if (method_exists(Aviso::class, 'usuariosLidos')) {
                    $query->withCount(['usuariosLidos as usuarios_lidos_count']);
                } elseif (method_exists(Aviso::class, 'leituras')) {
                    $query->withCount(['leituras as usuarios_lidos_count']);
                }
            } catch (Throwable $e) {
                // Não mata a página por contagem; só loga.
                Log::warning('[QUADRO_AVISOS] Falha ao aplicar withCount', [
                    'message' => $e->getMessage(),
                ]);
            }

            // Apenas avisos válidos "agora"
            $query->where(function ($q) use ($now) {
                $q->where('status', 'ativo')
                    ->orWhere(function ($q2) use ($now) {
                        $q2->where('status', 'agendado')
                            ->where(function ($q3) use ($now) {
                                $q3->whereNull('data_inicio')
                                    ->orWhere('data_inicio', '<=', $now);
                            });
                    });
            });

            $query->where(function ($q) use ($now) {
                $q->whereNull('data_inicio')
                    ->orWhere('data_inicio', '<=', $now);
            });

            $query->where(function ($q) use ($now) {
                $q->whereNull('data_fim')
                    ->orWhere('data_fim', '>=', $now);
            });

            // Filtros
            if (!empty($filtros['categoria_id'])) {
                $query->where('categoria_id', (int) $filtros['categoria_id']);
            }

            $busca = trim((string)($filtros['busca'] ?? ''));
            if ($busca !== '') {
                $query->where(function ($q) use ($busca) {
                    $q->where('titulo', 'like', '%' . $busca . '%')
                        ->orWhere('descricao', 'like', '%' . $busca . '%');
                });
            }

            // Ordenação
            $ordenar = (string)($filtros['ordenar'] ?? 'prioridade');
            if ($ordenar === 'data') {
                $query->orderByDesc('created_at');
            } elseif ($ordenar === 'validade') {
                // Expira primeiro; nulos por último
                $query->orderByRaw('CASE WHEN data_fim IS NULL THEN 1 ELSE 0 END ASC')
                    ->orderBy('data_fim', 'asc')
                    ->orderByDesc('created_at');
            } else {
                // Prioridade: critica > alta > media > baixa
                $query->orderByRaw("CASE prioridade
                    WHEN 'critica' THEN 4
                    WHEN 'alta' THEN 3
                    WHEN 'media' THEN 2
                    WHEN 'baixa' THEN 1
                    ELSE 0 END DESC")
                    ->orderByDesc('created_at');
            }

            // Evita carregar infinito
            return $query->limit(80)->get();
        });
    }

    /**
     * Total de usuários "ativos" (para % de leitura). Se não houver coluna de status,
     * retorna o total de users.
     */
    public function getTotalUsuariosAtivos(): int
    {
        try {
            if (!Schema::hasTable('users')) {
                return 0;
            }

            // Tenta detectar colunas comuns de "ativo"
            if (Schema::hasColumn('users', 'ativo')) {
                return (int) DB::table('users')->where('ativo', 1)->count();
            }
            if (Schema::hasColumn('users', 'active')) {
                return (int) DB::table('users')->where('active', 1)->count();
            }
            if (Schema::hasColumn('users', 'status')) {
                // Aceita tanto boolean-like quanto string
                $q = DB::table('users');
                return (int) $q->whereIn('status', ['ativo', 'active', 1, '1'])->count();
            }

            return (int) DB::table('users')->count();
        } catch (Throwable $e) {
            Log::error('[QUADRO_AVISOS] Erro ao contar usuários', [
                'message' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    public function usuarioJaLeu(int $avisoId, int $usuarioId): bool
    {
        try {
            if (!Schema::hasTable('avisos_lidos')) {
                return false;
            }

            return DB::table('avisos_lidos')
                ->where('aviso_id', $avisoId)
                ->where('usuario_id', $usuarioId)
                ->exists();
        } catch (Throwable $e) {
            Log::warning('[QUADRO_AVISOS] Erro ao verificar leitura', [
                'message' => $e->getMessage(),
                'aviso_id' => $avisoId,
                'usuario_id' => $usuarioId,
            ]);
            return false;
        }
    }

    public function marcarComoLido(int $avisoId, int $usuarioId): void
    {
        try {
            if (!Schema::hasTable('avisos_lidos')) {
                return;
            }

            DB::table('avisos_lidos')->insertOrIgnore([
                'aviso_id' => $avisoId,
                'usuario_id' => $usuarioId,
                'lido_em' => now(),
            ]);

            // invalida cache de listagem (para atualizar contagem "lido por")
            $this->bumpCacheVersion();
        } catch (Throwable $e) {
            Log::warning('[QUADRO_AVISOS] Erro ao marcar como lido', [
                'message' => $e->getMessage(),
                'aviso_id' => $avisoId,
                'usuario_id' => $usuarioId,
            ]);
        }
    }

    /**
     * Use quando criar/editar/remover avisos (ou categorias) para forçar a listagem pública a atualizar.
     */
    public function bumpCacheVersion(): void
    {
        try {
            $version = (int) Cache::get(self::CACHE_VERSION_KEY, 1);
            $version = ($version >= 1000000) ? 1 : ($version + 1);
            Cache::forever(self::CACHE_VERSION_KEY, $version);
        } catch (Throwable $e) {
            Log::warning('[QUADRO_AVISOS] Falha ao bump cache version', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function getCacheVersion(): int
    {
        try {
            $v = Cache::get(self::CACHE_VERSION_KEY, 1);
            return is_numeric($v) ? (int) $v : 1;
        } catch (Throwable $e) {
            return 1;
        }
    }
}
