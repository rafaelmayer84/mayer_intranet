<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdpResultadoMensal extends Model
{
    protected $table = 'gdp_resultados_mensais';

    protected $fillable = [
        'ciclo_id', 'indicador_id', 'user_id', 'mes', 'ano',
        'valor_apurado', 'apurado_em', 'valor_override', 'justificativa_override',
        'override_por', 'atribuicao_aproximada', 'validado',
        'percentual_atingimento', 'score_ponderado',
    ];

    protected $casts = [
        'valor_apurado'          => 'decimal:2',
        'valor_override'         => 'decimal:2',
        'percentual_atingimento' => 'decimal:2',
        'score_ponderado'        => 'decimal:4',
        'atribuicao_aproximada'  => 'boolean',
        'validado'               => 'boolean',
        'apurado_em'             => 'datetime',
    ];

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(GdpCiclo::class, 'ciclo_id');
    }

    public function indicador(): BelongsTo
    {
        return $this->belongsTo(GdpIndicador::class, 'indicador_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getValorEfetivoAttribute(): ?float
    {
        return $this->valor_override ?? $this->valor_apurado;
    }
}
