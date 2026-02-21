<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdpPenalizacaoConfig extends Model
{
    protected $table = 'gdp_penalizacao_config';

    protected $fillable = [
        'ciclo_id','tipo_id','threshold_valor','pontos_desconto','ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'pontos_desconto' => 'integer',
        'threshold_valor' => 'integer',
    ];

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(GdpCiclo::class, 'ciclo_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(GdpPenalizacaoTipo::class, 'tipo_id');
    }
}
