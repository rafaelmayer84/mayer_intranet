<?php

namespace App\Services\Sisrh;

use Illuminate\Support\Facades\DB;

/**
 * Busca captação (receita efetivamente recebida) para um advogado em um mês.
 * Usa a mesma lógica do indicador F1 do GdpDataAdapter:
 *   movimentos com classificacao IN (RECEITA_PF, RECEITA_PJ), valor > 0,
 *   filtrado por mês/ano, usando gdp_validacao_financeira quando disponível,
 *   ou fallback para processo→advogado responsável.
 */
class SisrhCaptacaoService
{
    /**
     * Retorna o valor total de captação de um usuário no mês/ano.
     *
     * Estratégia (mesma do F1 GDP):
     * 1. Se existirem registros em gdp_validacao_financeira para o user/mês → usa soma de lá.
     * 2. Senão, filtra movimentos via responsavel_id (se populado) ou processo→advogado.
     */
    public function captacaoMensal(int $userId, int $ano, int $mes): float
    {
        // Estratégia 1: gdp_validacao_financeira (fonte validada manualmente)
        $validado = DB::table('gdp_validacao_financeira')
            ->where('user_id', $userId)
            ->where('ano', $ano)
            ->where('mes', $mes)
            ->where('indicador_codigo', 'F1')
            ->value('valor_validado');

        if ($validado !== null) {
            return round((float) $validado, 2);
        }

        // Estratégia 2: movimentos via responsavel_id
        $temResponsavel = DB::table('movimentos')
            ->whereNotNull('responsavel_id')
            ->where('responsavel_id', '>', 0)
            ->exists();

        if ($temResponsavel) {
            $valor = DB::table('movimentos')
                ->where('responsavel_id', $userId)
                ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
                ->where('valor', '>', 0)
                ->whereYear('data_movimento', $ano)
                ->whereMonth('data_movimento', $mes)
                ->sum('valor');

            return round((float) $valor, 2);
        }

        // Estratégia 3: fallback via processos do advogado
        // Busca processos onde o advogado é responsável, depois soma movimentos desses processos
        $processoDjIds = DB::table('processos')
            ->where('advogado_responsavel_id', $userId)
            ->pluck('datajuri_id')
            ->toArray();

        if (empty($processoDjIds)) {
            return 0.00;
        }

        $valor = DB::table('movimentos')
            ->whereIn('processo_datajuri_id', $processoDjIds)
            ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
            ->where('valor', '>', 0)
            ->whereYear('data_movimento', $ano)
            ->whereMonth('data_movimento', $mes)
            ->sum('valor');

        return round((float) $valor, 2);
    }
}
