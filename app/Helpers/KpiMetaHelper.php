<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Helper centralizado para leitura de metas KPI.
 * Fonte: tabela kpi_monthly_targets (populada via upload XLSX).
 */
class KpiMetaHelper
{
    /**
     * Retorna meta de um KPI para ano/mes.
     */
    public static function get(string $kpiKey, int $ano, int $mes, float $default = 0): float
    {
        $cacheKey = "kpi_meta_{$kpiKey}_{$ano}_{$mes}";
        return Cache::remember($cacheKey, 3600, function () use ($kpiKey, $ano, $mes, $default) {
            $row = DB::table('kpi_monthly_targets')
                ->where('kpi_key', $kpiKey)
                ->where('year', $ano)
                ->where('month', $mes)
                ->first();
            return $row ? (float) $row->meta_valor : $default;
        });
    }

    /**
     * Retorna array [1=>val, 2=>val, ... 12=>val] para um KPI no ano.
     */
    public static function getAnual(string $kpiKey, int $ano): array
    {
        $metas = [];
        for ($m = 1; $m <= 12; $m++) {
            $metas[$m] = self::get($kpiKey, $ano, $m);
        }
        return $metas;
    }

    /**
     * Limpa cache de metas para um ano (ou tudo).
     */
    public static function clearCache(?int $ano = null): void
    {
        if ($ano) {
            $rows = DB::table('kpi_monthly_targets')
                ->where('year', $ano)
                ->select('kpi_key', 'month')
                ->get();
            foreach ($rows as $r) {
                Cache::forget("kpi_meta_{$r->kpi_key}_{$ano}_{$r->month}");
            }
        }
    }
}
