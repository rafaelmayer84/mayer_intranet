<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdpRemuneracaoFaixa extends Model
{
    protected $table = 'gdp_remuneracao_faixas';

    protected $fillable = [
        'ciclo_id', 'score_min', 'score_max', 'percentual_remuneracao', 'label',
    ];

    protected $casts = [
        'score_min' => 'float',
        'score_max' => 'float',
        'percentual_remuneracao' => 'float',
    ];

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\GdpCiclo::class, 'ciclo_id');
    }

    /**
     * Dado um score e ciclo_id, retorna o percentual da faixa correspondente.
     */
    public static function percentualParaScore(int $cicloId, float $score): float
    {
        $faixa = self::where('ciclo_id', $cicloId)
            ->where('score_min', '<=', $score)
            ->where('score_max', '>', $score)
            ->first();

        // Se não encontrou, tenta a última faixa (score_max pode ser o teto)
        if (!$faixa) {
            $faixa = self::where('ciclo_id', $cicloId)
                ->where('score_min', '<=', $score)
                ->orderByDesc('score_min')
                ->first();
        }

        return $faixa ? $faixa->percentual_remuneracao : 0.00;
    }

    /**
     * Dado um score e ciclo_id, retorna a faixa (model) correspondente.
     */
    public static function faixaParaScore(int $cicloId, float $score): ?self
    {
        $faixa = self::where('ciclo_id', $cicloId)
            ->where('score_min', '<=', $score)
            ->where('score_max', '>', $score)
            ->first();

        if (!$faixa) {
            $faixa = self::where('ciclo_id', $cicloId)
                ->where('score_min', '<=', $score)
                ->orderByDesc('score_min')
                ->first();
        }

        return $faixa;
    }
}
