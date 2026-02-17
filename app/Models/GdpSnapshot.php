<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdpSnapshot extends Model
{
    protected $table = 'gdp_snapshots';

    protected $fillable = [
        'ciclo_id', 'user_id', 'mes', 'ano',
        'score_juridico', 'score_financeiro', 'score_desenvolvimento', 'score_atendimento',
        'score_total', 'ranking', 'congelado', 'congelado_por', 'congelado_em',
    ];

    protected $casts = [
        'score_juridico'        => 'decimal:2',
        'score_financeiro'      => 'decimal:2',
        'score_desenvolvimento' => 'decimal:2',
        'score_atendimento'     => 'decimal:2',
        'score_total'           => 'decimal:2',
        'congelado'             => 'boolean',
        'congelado_em'          => 'datetime',
    ];

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(GdpCiclo::class, 'ciclo_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
