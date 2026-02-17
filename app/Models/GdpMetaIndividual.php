<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdpMetaIndividual extends Model
{
    protected $table = 'gdp_metas_individuais';

    protected $fillable = [
        'ciclo_id', 'indicador_id', 'user_id', 'mes', 'ano',
        'valor_meta', 'justificativa', 'definido_por',
    ];

    protected $casts = ['valor_meta' => 'decimal:2'];

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
}
