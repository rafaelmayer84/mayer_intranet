<?php

namespace App\Services\Sisrh;

use Illuminate\Support\Facades\DB;

/**
 * Calcula a redução percentual de RV baseada em ocorrências de conformidade/penalidades.
 *
 * Regras (AD002):
 * - Leve: -5%
 * - Média: -15%
 * - Grave: -30%
 * - Cap máximo de redução por conformidade no mês: 40%
 *
 * NUNCA zera a RV completamente (cap de 40% se aplica ao total conformidade + acompanhamento).
 */
class SisrhConformidadeImpactService
{
    private const IMPACTO_POR_GRAVIDADE = [
        'leve' => 5.00,
        'media' => 15.00,
        'grave' => 30.00,
    ];

    private const CAP_MAXIMO_CONFORMIDADE = 40.00;

    /**
     * Retorna o percentual total de redução por conformidade para um advogado no mês.
     * Busca ocorrências ativas na tabela gdp_penalizacoes.
     */
    public function reducaoConformidade(int $userId, int $ano, int $mes): float
    {
        // Busca penalizações ativas (não contestadas com sucesso) no período
        $penalizacoes = DB::table('gdp_penalizacoes')
            ->where('user_id', $userId)
            ->whereYear('created_at', $ano)
            ->whereMonth('created_at', $mes)
            ->where(function ($q) {
                $q->whereNull('contestacao_status')
                  ->orWhere('contestacao_status', '!=', 'aceita');
            })
            ->get(['gravidade']);

        if ($penalizacoes->isEmpty()) {
            return 0.00;
        }

        $totalReducao = 0.00;

        foreach ($penalizacoes as $p) {
            $gravidade = strtolower($p->gravidade ?? '');
            $totalReducao += self::IMPACTO_POR_GRAVIDADE[$gravidade] ?? 0.00;
        }

        return min($totalReducao, self::CAP_MAXIMO_CONFORMIDADE);
    }

    /**
     * Retorna detalhamento das penalidades para o snapshot.
     */
    public function detalhamento(int $userId, int $ano, int $mes): array
    {
        return DB::table('gdp_penalizacoes')
            ->where('user_id', $userId)
            ->whereYear('created_at', $ano)
            ->whereMonth('created_at', $mes)
            ->where(function ($q) {
                $q->whereNull('contestacao_status')
                  ->orWhere('contestacao_status', '!=', 'aceita');
            })
            ->get(['id', 'gravidade', 'descricao', 'created_at'])
            ->toArray();
    }
}
