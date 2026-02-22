<?php

namespace App\Services\Sisrh;

use App\Models\GdpAcompanhamento;

/**
 * Verifica se o advogado entregou o Acompanhamento Bimestral do Plano de Trabalho.
 * Se não entregou, aplica redução configurável (default 10%).
 */
class SisrhAcompanhamentoService
{
    private const REDUCAO_DEFAULT = 10.00;

    /**
     * Retorna o percentual de redução por falta de acompanhamento bimestral.
     * Se o advogado já submeteu (status submitted ou validated) → 0%.
     * Se não submeteu → REDUCAO_DEFAULT.
     */
    public function reducaoAcompanhamento(int $userId, int $cicloId, int $ano, int $mes): float
    {
        $bimestre = GdpAcompanhamento::mesBimestre($mes);

        $temSubmissao = GdpAcompanhamento::where('user_id', $userId)
            ->where('ciclo_id', $cicloId)
            ->where('ano', $ano)
            ->where('bimestre', $bimestre)
            ->whereIn('status', ['submitted', 'validated'])
            ->exists();

        return $temSubmissao ? 0.00 : self::REDUCAO_DEFAULT;
    }

    /**
     * Verifica se há submissão (qualquer status) para detalhamento do snapshot.
     */
    public function statusAcompanhamento(int $userId, int $cicloId, int $ano, int $mes): ?string
    {
        $bimestre = GdpAcompanhamento::mesBimestre($mes);

        $acomp = GdpAcompanhamento::where('user_id', $userId)
            ->where('ciclo_id', $cicloId)
            ->where('ano', $ano)
            ->where('bimestre', $bimestre)
            ->first(['status', 'submitted_at']);

        if (!$acomp) {
            return 'nao_entregue';
        }

        return $acomp->status;
    }
}
