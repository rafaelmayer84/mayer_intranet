<?php

namespace App\Services\Sisrh;

use Illuminate\Support\Facades\DB;

/**
 * Busca captação (receita efetivamente recebida) para um advogado em um mês.
 * Usa a mesma lógica do indicador F1 do GdpDataAdapter:
 *   1. gdp_validacao_financeira (se existir para o mês)
 *   2. fallback: movimentos.proprietario_id = users.datajuri_proprietario_id
 */
class SisrhCaptacaoService
{
    public function captacaoMensal(int $userId, int $ano, int $mes): float
    {
        // Buscar datajuri_proprietario_id do user
        $djPropId = DB::table('users')->where('id', $userId)->value('datajuri_proprietario_id');

        if (!$djPropId) {
            return 0.00;
        }

        // Estratégia 1: gdp_validacao_financeira (movimentos validados manualmente)
        $temValidacao = DB::table('gdp_validacao_financeira')
            ->where('ano', $ano)
            ->where('mes', $mes)
            ->exists();

        if ($temValidacao) {
            $valor = DB::table('gdp_validacao_financeira')
                ->where('ano', $ano)
                ->where('mes', $mes)
                ->where(function ($q) use ($userId) {
                    $q->where('user_id_override', $userId)
                      ->orWhere(function ($q2) use ($userId) {
                          $q2->whereNull('user_id_override')
                              ->where('user_id_resolvido', $userId);
                      });
                })
                ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
                ->where('status_pontuacao', '!=', 'excluido')
                ->sum('valor');

            return round((float) $valor, 2);
        }

        // Estratégia 2: movimentos via proprietario_id (DataJuri)
        $valor = DB::table('movimentos')
            ->where('proprietario_id', $djPropId)
            ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
            ->whereRaw('CAST(valor AS DECIMAL(15,2)) > 0')
            ->where('ano', $ano)
            ->where('mes', $mes)
            ->sum('valor');

        return round((float) $valor, 2);
    }
}
