<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiricEvidencia extends Model
{
    protected $table = 'siric_evidencias';

    protected $fillable = [
        'consulta_id', 'fonte', 'tipo', 'payload', 'impacto', 'resumo',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(SiricConsulta::class, 'consulta_id');
    }

    /**
     * Cor do badge de impacto.
     */
    public function getImpactoCorAttribute(): string
    {
        return match ($this->impacto) {
            'positivo' => 'green',
            'neutro'   => 'gray',
            'negativo' => 'orange',
            'risco'    => 'red',
            default    => 'gray',
        };
    }
}
