<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdpRemuneracaoFaixa extends Model
{
    protected $table = 'gdp_remuneracao_faixas';

    protected $fillable = [
        'ciclo_id','score_min','score_max','percentual_remuneracao','label',
    ];

    protected $casts = [
        'score_min' => 'integer',
        'score_max' => 'integer',
        'percentual_remuneracao' => 'integer',
    ];

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(GdpCiclo::class, 'ciclo_id');
    }

    public static function faixaParaScore(int $cicloId, float $score): ?self
    {
        $scoreInt = (int) round($score);
        return static::where('ciclo_id', $cicloId)
            ->where('score_min', '<=', $scoreInt)
            ->where('score_max', '>=', $scoreInt)
            ->first();
    }

    public static function percentualParaScore(int $cicloId, float $score): int
    {
        $faixa = static::faixaParaScore($cicloId, $score);
        return $faixa ? $faixa->percentual_remuneracao : 0;
    }
}
