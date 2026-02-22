<?php

namespace App\Services\Sisrh;

use Illuminate\Support\Facades\DB;

/**
 * Lê o score GDP mensal do advogado a partir de gdp_snapshots.
 * Usa score_total_original quando não nulo, senão score_total.
 */
class SisrhScoreService
{
    /**
     * Retorna o score GDP consolidado de um usuário para mês/ano.
     * Retorna null se não houver snapshot (apuração GDP não realizada).
     */
    public function scoreMensal(int $userId, int $ano, int $mes): ?float
    {
        $snapshot = DB::table('gdp_snapshots')
            ->where('user_id', $userId)
            ->where('ano', $ano)
            ->where('mes', $mes)
            ->first(['score_total', 'score_total_original']);

        if (!$snapshot) {
            return null;
        }

        $score = $snapshot->score_total_original ?? $snapshot->score_total;

        return round((float) $score, 2);
    }

    /**
     * Verifica se o advogado tem plano de trabalho vigente (acordo aceito no ciclo).
     * Inferido por existir snapshot com congelado=true no ciclo.
     */
    public function temPlanoVigente(int $userId, int $cicloId): bool
    {
        return DB::table('gdp_snapshots')
            ->where('user_id', $userId)
            ->where('ciclo_id', $cicloId)
            ->where('congelado', true)
            ->exists();
    }
}
